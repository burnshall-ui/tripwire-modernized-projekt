<?php

namespace Tripwire\Services;

use InvalidArgumentException;

class Container {
    private array $services = [];
    private array $factories = [];
    private array $parameters = [];
    private array $singletons = [];

    public function __construct(array $parameters = []) {
        $this->parameters = $parameters;
    }

    /**
     * Register a service factory (creates new instance each time)
     */
    public function set(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
        // Remove from singletons if it was registered as one
        unset($this->singletons[$id]);
        unset($this->services[$id]);
    }

    /**
     * Register a singleton service (same instance every time)
     */
    public function singleton(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
        $this->singletons[$id] = true;
    }

    /**
     * Get a service from the container
     */
    public function get(string $id) {
        // Return cached singleton instance
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // Check if factory exists
        if (!isset($this->factories[$id])) {
            throw new InvalidArgumentException("Service '{$id}' not found in container");
        }

        // Instantiate the service
        $service = $this->factories[$id]($this);

        // Cache if singleton
        if (isset($this->singletons[$id])) {
            $this->services[$id] = $service;
        }

        return $service;
    }

    /**
     * Check if service exists
     */
    public function has(string $id): bool {
        return isset($this->factories[$id]) || isset($this->services[$id]);
    }

    /**
     * Set a parameter
     */
    public function setParameter(string $key, $value): void {
        $this->parameters[$key] = $value;
    }

    /**
     * Get a parameter
     */
    public function getParameter(string $key) {
        if (!isset($this->parameters[$key])) {
            throw new InvalidArgumentException("Parameter '{$key}' not found in container");
        }
        return $this->parameters[$key];
    }

    /**
     * Check if parameter exists
     */
    public function hasParameter(string $key): bool {
        return isset($this->parameters[$key]);
    }

    /**
     * Get all registered service IDs
     */
    public function getServiceIds(): array {
        return array_keys($this->factories);
    }

    /**
     * Clear all cached singletons (useful for testing)
     */
    public function clearCache(): void {
        $this->services = [];
    }
}

/**
 * Initialize the container with core services
 *
 * @return Container
 */
function createContainer(): Container {
    global $mysql;

    $container = new Container([
        'app_name' => defined('APP_NAME') ? APP_NAME : 'Tripwire',
        'version' => defined('VERSION') ? VERSION : '1.0',
        'debug' => defined('DEBUG') ? DEBUG : false
    ]);

    // Core database service (singleton)
    $container->singleton('db', function($c) use ($mysql) {
        return $mysql;
    });

    // Redis Cache Service (singleton)
    $container->singleton('redis', function($c) {
        return new RedisService();
    });

    // Services (singletons)
    $container->singleton('userService', function($c) {
        return new UserService($c->get('db'));
    });

    $container->singleton('signatureService', function($c) {
        return new SignatureService($c->get('db'), $c->get('redis'));
    });

    $container->singleton('wormholeService', function($c) {
        return new WormholeService($c->get('db'));
    });

    // Controllers (singletons)
    $container->singleton('systemController', function($c) {
        return new \Tripwire\Controllers\SystemController($c->get('db'));
    });

    // Views (factory - new instance each time)
    $container->set('systemView', function($c) {
        $view = new \Tripwire\Views\SystemView();
        $view->setContainer($c);
        return $view;
    });

    return $container;
}

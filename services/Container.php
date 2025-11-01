<?php

class Container {
    private array $services = [];
    private array $parameters = [];

    public function __construct(array $parameters = []) {
        $this->parameters = $parameters;
    }

    public function set(string $id, callable $factory): void {
        $this->services[$id] = $factory;
    }

    public function get(string $id) {
        if (!isset($this->services[$id])) {
            throw new InvalidArgumentException("Service '{$id}' not found in container");
        }

        // If it's already instantiated, return it
        if (!is_callable($this->services[$id])) {
            return $this->services[$id];
        }

        // Instantiate the service
        $service = $this->services[$id]($this);

        // Cache the instantiated service
        $this->services[$id] = $service;

        return $service;
    }

    public function has(string $id): bool {
        return isset($this->services[$id]);
    }

    public function setParameter(string $key, $value): void {
        $this->parameters[$key] = $value;
    }

    public function getParameter(string $key) {
        if (!isset($this->parameters[$key])) {
            throw new InvalidArgumentException("Parameter '{$key}' not found in container");
        }
        return $this->parameters[$key];
    }
}

// Initialize the container with core services
function createContainer(): Container {
    global $mysql;

    $container = new Container();

    // Core database service
    $container->set('db', function($c) use ($mysql) {
        return $mysql;
    });

    // Controllers
    $container->set('systemController', function($c) {
        return new SystemController($c->get('db'));
    });

    // Services
    $container->set('userService', function($c) {
        return new UserService($c->get('db'));
    });

    $container->set('signatureService', function($c) {
        return new SignatureService($c->get('db'));
    });

    $container->set('wormholeService', function($c) {
        return new WormholeService($c->get('db'));
    });

    // Redis Cache Service
    $container->set('redis', function($c) {
        return new RedisService();
    });

    // Views
    $container->set('systemView', function($c) {
        return new SystemView();
    });

    return $container;
}

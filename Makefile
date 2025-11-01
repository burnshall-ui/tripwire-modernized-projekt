# ================================
# Tripwire Docker Management
# ================================

.PHONY: help build up down restart logs clean dev prod test security

# Default target
help: ## Show this help message
	@echo "Tripwire Docker Management"
	@echo ""
	@echo "Available commands:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ================================
# Development Commands
# ================================

dev-build: ## Build development environment
	docker-compose -f docker-compose.dev.yml build

dev-up: ## Start development environment
	docker-compose -f docker-compose.dev.yml up -d

dev-down: ## Stop development environment
	docker-compose -f docker-compose.dev.yml down

dev-logs: ## Show development logs
	docker-compose -f docker-compose.dev.yml logs -f

dev-restart: ## Restart development environment
	docker-compose -f docker-compose.dev.yml restart

# ================================
# Production Commands
# ================================

prod-build: ## Build production environment
	docker-compose -f docker-compose.prod.yml build

prod-up: ## Start production environment
	docker-compose -f docker-compose.prod.yml up -d

prod-down: ## Stop production environment
	docker-compose -f docker-compose.prod.yml down

prod-logs: ## Show production logs
	docker-compose -f docker-compose.prod.yml logs -f

prod-restart: ## Restart production environment
	docker-compose -f docker-compose.prod.yml restart

# ================================
# General Commands
# ================================

build: ## Build all environments
	@echo "Building development environment..."
	docker-compose -f docker-compose.dev.yml build
	@echo "Building production environment..."
	docker-compose -f docker-compose.prod.yml build

up: ## Start production environment (default)
	docker-compose -f docker-compose.prod.yml up -d

down: ## Stop all environments
	docker-compose -f docker-compose.dev.yml down
	docker-compose -f docker-compose.prod.yml down

restart: ## Restart production environment
	docker-compose -f docker-compose.prod.yml restart

logs: ## Show production logs
	docker-compose -f docker-compose.prod.yml logs -f

# ================================
# Utility Commands
# ================================

clean: ## Clean up Docker resources
	docker system prune -f
	docker volume prune -f

status: ## Show status of all containers
	docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

health: ## Check health of all services
	@echo "Checking NGINX health..."
	curl -f http://localhost/health || echo "NGINX unhealthy"
	@echo "Checking PHP-FPM health..."
	docker-compose -f docker-compose.prod.yml exec php-fpm php-fpm -t || echo "PHP-FPM unhealthy"
	@echo "Checking MySQL health..."
	docker-compose -f docker-compose.prod.yml exec mysql mysqladmin ping -h localhost || echo "MySQL unhealthy"

# ================================
# Development Tools
# ================================

shell-php: ## Open shell in PHP container
	docker-compose -f docker-compose.dev.yml exec php-fpm sh

shell-mysql: ## Open MySQL shell
	docker-compose -f docker-compose.dev.yml exec mysql mysql -u tripwire_dev -p tripwire_dev

shell-nginx: ## Open shell in NGINX container
	docker-compose -f docker-compose.dev.yml exec nginx sh

# ================================
# Security & Testing
# ================================

security-scan: ## Run security scan on images
	@echo "Scanning PHP-FPM image..."
	docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
		goodwithtech/dockle:latest \
		docker.io/library/tripwire-php-fpm:latest || true
	@echo "Scanning NGINX image..."
	docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
		goodwithtech/dockle:latest \
		docker.io/library/tripwire-nginx:latest || true

test: ## Run tests (when implemented)
	@echo "Tests not yet implemented"
	@echo "Run: make shell-php"
	@echo "Then: cd /opt/app && phpunit"

# ================================
# Deployment
# ================================

deploy: ## Deploy to production (use with caution)
	@echo "🚨 DEPLOYMENT CHECKLIST 🚨"
	@echo "1. Run tests: make test"
	@echo "2. Security scan: make security-scan"
	@echo "3. Backup database"
	@echo "4. Pull latest changes"
	@echo ""
	@echo "Continue? (y/N)"
	@read -p "" confirm; \
	if [ "$$confirm" = "y" ] || [ "$$confirm" = "Y" ]; then \
		echo "Deploying to production..."; \
		make prod-down; \
		git pull; \
		make prod-build; \
		make prod-up; \
		make health; \
	else \
		echo "Deployment cancelled."; \
	fi

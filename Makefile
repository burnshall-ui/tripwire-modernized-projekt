# Quick Start Scripts for Tripwire

# 🚀 Production Setup (empfohlen für normale Nutzung)
.PHONY: up
up:
	@echo "🚀 Starting Tripwire (Production)..."
	docker-compose up -d
	@echo "✅ Tripwire läuft auf http://localhost"
	@echo "📊 WebSocket auf ws://localhost:8080"

# 🔧 Development Setup
.PHONY: dev
dev:
	@echo "🔧 Starting Tripwire (Development)..."
	docker-compose -f docker-compose.dev.yml up -d
	@echo "✅ Tripwire läuft auf http://localhost:8080"
	@echo "🗄️ Adminer läuft auf http://localhost:8081"

# 📊 Production + Monitoring
.PHONY: prod
prod:
	@echo "📊 Starting Tripwire (Production + Monitoring)..."
	docker-compose -f docker-compose.prod.yml up -d
	@echo "✅ Tripwire läuft auf http://localhost"

# 🛑 Stop all containers
.PHONY: down
down:
	@echo "🛑 Stopping Tripwire..."
	docker-compose down
	docker-compose -f docker-compose.dev.yml down 2>/dev/null || true
	docker-compose -f docker-compose.prod.yml down 2>/dev/null || true
	@echo "✅ Alle Container gestoppt"

# 🧹 Clean everything (inkl. Volumes)
.PHONY: clean
clean:
	@echo "🧹 Cleaning up..."
	docker-compose down -v
	docker-compose -f docker-compose.dev.yml down -v 2>/dev/null || true
	docker-compose -f docker-compose.prod.yml down -v 2>/dev/null || true
	@echo "✅ Cleanup abgeschlossen"

# 📊 Status anzeigen
.PHONY: status
status:
	@echo "📊 Tripwire Status:"
	@docker-compose ps

# 📝 Logs anzeigen
.PHONY: logs
logs:
	docker-compose logs -f

# 🔄 Restart
.PHONY: restart
restart: down up

# 💾 Backup
.PHONY: backup
backup:
	@echo "💾 Creating backup..."
	@mkdir -p backups
	docker-compose exec mysql mysqldump -u root tripwire > backups/tripwire_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "✅ Backup erstellt in backups/"

# 🏗️ Build containers
.PHONY: build
build:
	@echo "🏗️ Building containers..."
	docker-compose build

# 🔍 Health Check
.PHONY: health
health:
	@echo "🔍 Health Check:"
	@docker-compose ps
	@echo ""
	@echo "📊 Container Stats:"
	@docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}"

# 📦 Install Composer Dependencies
.PHONY: composer-install
composer-install:
	@echo "📦 Installing Composer dependencies..."
	docker-compose exec php-fpm composer install
	@echo "✅ Composer dependencies installed"

# 🗄️ Database Setup
.PHONY: db-setup
db-setup:
	@echo "🗄️ Setting up database..."
	docker-compose exec mysql mysql -u root -e "CREATE DATABASE IF NOT EXISTS tripwire;"
	docker-compose exec mysql mysql -u root tripwire < .docker/mysql/tripwire.sql
	@echo "✅ Database setup complete"

# 🆘 Help
.PHONY: help
help:
	@echo "🚀 Tripwire Docker Management"
	@echo ""
	@echo "Verfügbare Commands:"
	@echo "  make up              - Start Production (Standard)"
	@echo "  make dev             - Start Development (mit Adminer)"
	@echo "  make prod            - Start Production + Monitoring"
	@echo "  make down            - Stop alle Container"
	@echo "  make clean           - Stop + Delete alle Volumes"
	@echo "  make restart         - Restart alle Container"
	@echo "  make logs            - Zeige Logs"
	@echo "  make status          - Zeige Container-Status"
	@echo "  make health          - Health Check + Stats"
	@echo "  make backup          - MySQL Backup erstellen"
	@echo "  make build           - Container neu bauen"
	@echo "  make composer-install - Composer Dependencies installieren"
	@echo "  make db-setup        - Datenbank initialisieren"
	@echo ""
	@echo "Beispiele:"
	@echo "  make up              # Normale Nutzung"
	@echo "  make dev             # Für Entwicklung"
	@echo "  make logs            # Logs folgen"
	@echo "  make down            # Alles stoppen"

# Default target
.DEFAULT_GOAL := help

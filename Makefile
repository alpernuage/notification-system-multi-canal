.PHONY: help setup install start stop restart worker scheduler test clean db-reset db-migrate fixtures stats

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

##@ Help
help: ## Display this help message
	@echo "$(BLUE)Multi-Canal Notification System - Makefile$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "Usage:\n  make $(GREEN)<target>$(NC)\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2 } /^##@/ { printf "\n$(YELLOW)%s$(NC)\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Setup
setup: ## Complete project setup (first time)
	@echo "$(BLUE)Setting up the project...$(NC)"
	@make install
	@make start
	@make db-migrate
	@make fixtures
	@echo "$(GREEN)✓ Setup complete!$(NC)"
	@echo "$(YELLOW)Dashboard: http://localhost:8000/dashboard$(NC)"
	@echo "$(YELLOW)Mailpit: http://localhost:8025$(NC)"

install: ## Install dependencies
	@echo "$(BLUE)Installing dependencies...$(NC)"
	composer install
	@echo "$(GREEN)✓ Dependencies installed$(NC)"

##@ Docker
start: ## Start Docker containers
	@echo "$(BLUE)Starting Docker containers...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)✓ Containers started$(NC)"
	@docker-compose ps

stop: ## Stop Docker containers
	@echo "$(BLUE)Stopping Docker containers...$(NC)"
	docker-compose down
	@echo "$(GREEN)✓ Containers stopped$(NC)"

restart: ## Restart Docker containers
	@make stop
	@make start

##@ Database
db-reset: ## Reset database (drop, create, migrate)
	@echo "$(BLUE)Resetting database...$(NC)"
	php bin/console doctrine:database:drop --force --if-exists
	php bin/console doctrine:database:create
	@make db-migrate
	@echo "$(GREEN)✓ Database reset$(NC)"

db-migrate: ## Run database migrations
	@echo "$(BLUE)Running migrations...$(NC)"
	php bin/console doctrine:migrations:migrate -n
	@echo "$(GREEN)✓ Migrations complete$(NC)"

fixtures: ## Load fixtures
	@echo "$(BLUE)Loading fixtures...$(NC)"
	php bin/console doctrine:fixtures:load -n
	@echo "$(GREEN)✓ Fixtures loaded$(NC)"

##@ Messenger
worker: ## Start Messenger worker (async queue)
	@echo "$(BLUE)Starting Messenger worker...$(NC)"
	php bin/console messenger:consume async -vv

scheduler: ## Start Scheduler worker
	@echo "$(BLUE)Starting Scheduler worker...$(NC)"
	php bin/console messenger:consume scheduler_default -vv

worker-stop: ## Stop all Messenger workers
	@echo "$(BLUE)Stopping workers...$(NC)"
	php bin/console messenger:stop-workers
	@echo "$(GREEN)✓ Workers stopped$(NC)"

##@ Testing
test: ## Run all tests
	@echo "$(BLUE)Running tests...$(NC)"
	php bin/phpunit
	@echo "$(GREEN)✓ Tests complete$(NC)"

test-unit: ## Run unit tests only
	@echo "$(BLUE)Running unit tests...$(NC)"
	php bin/phpunit tests/Unit

test-integration: ## Run integration tests only
	@echo "$(BLUE)Running integration tests...$(NC)"
	php bin/phpunit tests/Integration

test-coverage: ## Run tests with coverage
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	php bin/phpunit --coverage-html var/coverage
	@echo "$(GREEN)✓ Coverage report: var/coverage/index.html$(NC)"

##@ Code Quality
cs-fix: ## Fix code style
	@echo "$(BLUE)Fixing code style...$(NC)"
	vendor/bin/php-cs-fixer fix || true
	@echo "$(GREEN)✓ Code style fixed$(NC)"

cs-check: ## Check code style
	@echo "$(BLUE)Checking code style...$(NC)"
	vendor/bin/php-cs-fixer fix --dry-run --diff || true

stan: ## Run PHPStan
	@echo "$(BLUE)Running PHPStan...$(NC)"
	vendor/bin/phpstan analyse || true

##@ Notifications
stats: ## Show notification statistics
	@echo "$(BLUE)Notification Statistics:$(NC)"
	php bin/console notification:stats

send-test: ## Send a test notification
	@echo "$(BLUE)Sending test notification...$(NC)"
	php bin/console notification:send --channel=email --recipient=test@example.com --message="Test notification"

cleanup: ## Clean old notifications
	@echo "$(BLUE)Cleaning old notifications...$(NC)"
	php bin/console notification:cleanup

##@ Maintenance
clean: ## Clean cache and logs
	@echo "$(BLUE)Cleaning cache and logs...$(NC)"
	php bin/console cache:clear
	rm -rf var/log/*.log
	@echo "$(GREEN)✓ Cache and logs cleaned$(NC)"

clean-all: ## Clean everything (cache, logs, notifications, messages)
	@make clean
	@echo "$(BLUE)Cleaning notifications and messages...$(NC)"
	php bin/console doctrine:query:sql "DELETE FROM notification" || true
	php bin/console doctrine:query:sql "DELETE FROM messenger_messages" || true
	@echo "$(GREEN)✓ Everything cleaned$(NC)"

logs: ## Show application logs
	tail -f var/log/dev.log

##@ Development
serve: ## Start Symfony server
	@echo "$(BLUE)Starting Symfony server...$(NC)"
	symfony server:start -d || php -S localhost:8000 -t public/
	@echo "$(GREEN)✓ Server started at http://localhost:8000$(NC)"

serve-stop: ## Stop Symfony server
	symfony server:stop || true

redis-cli: ## Connect to Redis CLI
	docker-compose exec redis redis-cli

db-cli: ## Connect to PostgreSQL CLI
	docker-compose exec database psql -U app -d app

mailpit: ## Open Mailpit in browser
	@echo "$(BLUE)Opening Mailpit...$(NC)"
	xdg-open http://localhost:8025 2>/dev/null || open http://localhost:8025 2>/dev/null || echo "Open http://localhost:8025 in your browser"

dashboard: ## Open Dashboard in browser
	@echo "$(BLUE)Opening Dashboard...$(NC)"
	xdg-open http://localhost:8000/dashboard 2>/dev/null || open http://localhost:8000/dashboard 2>/dev/null || echo "Open http://localhost:8000/dashboard in your browser"

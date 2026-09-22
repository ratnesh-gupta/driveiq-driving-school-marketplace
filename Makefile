# DriveIQ — Docker Compose helpers
# Default stack: docker-compose.yaml (local)
# Production:    docker-compose.prod.yaml via prod-* targets

COMPOSE       ?= docker compose
COMPOSE_FILE  ?= docker-compose.yaml
PROD_FILE     ?= docker-compose.prod.yaml

DC            := $(COMPOSE) -f $(COMPOSE_FILE)
DC_PROD       := $(COMPOSE) -f $(PROD_FILE)

BACKEND       := $(DC) exec -T backend
BACKEND_TTY   := $(DC) exec backend
PROD_BACKEND  := $(DC_PROD) exec -T backend

.PHONY: help up down restart ps logs logs-backend logs-frontend \
	up-infra \
	migrate migrate-fresh seed migrate-seed fresh \
	artisan tinker shell \
	test test-filter frontend-check \
	prod-up prod-down prod-logs prod-migrate prod-seed

help:
	@echo "DriveIQ make targets"
	@echo ""
	@echo "  Stack"
	@echo "    make up              Build & start full local stack"
	@echo "    make down            Stop containers"
	@echo "    make restart         Restart services"
	@echo "    make ps              Compose status"
	@echo "    make logs            Follow all logs"
	@echo "    make logs-backend    Backend logs"
	@echo "    make logs-frontend   Frontend logs"
	@echo "    make up-infra        Postgres + Redis only"
	@echo ""
	@echo "  Database (runs inside backend container)"
	@echo "    make migrate         php artisan migrate --force"
	@echo "    make migrate-fresh   migrate:fresh (DESTROYS DATA)"
	@echo "    make seed            php artisan db:seed"
	@echo "    make migrate-seed    migrate then seed"
	@echo "    make fresh           migrate:fresh --seed (DESTROYS DATA)"
	@echo "    make artisan CMD=\"...\"   any artisan command"
	@echo "    make tinker          Interactive tinker"
	@echo "    make shell           Bash in backend container"
	@echo ""
	@echo "  Quality"
	@echo "    make test            PHPUnit in container"
	@echo "    make test-filter FILTER=Name"
	@echo "    make frontend-check  pnpm typecheck + build (host)"
	@echo ""
	@echo "  Production compose"
	@echo "    make prod-up | prod-down | prod-logs"
	@echo "    make prod-migrate | prod-seed"

# ── Local stack ─────────────────────────────────────────────

up:
	$(DC) up -d --build

up-infra:
	$(DC) up -d --build postgres redis

down:
	$(DC) down

restart:
	$(DC) restart

ps:
	$(DC) ps

logs:
	$(DC) logs -f --tail=200

logs-backend:
	$(DC) logs -f --tail=200 backend

logs-frontend:
	$(DC) logs -f --tail=200 frontend

# ── Artisan / DB (backend container) ────────────────────────

migrate:
	$(BACKEND) php artisan migrate --force

migrate-fresh:
	$(BACKEND) php artisan migrate:fresh --force

seed:
	$(BACKEND) php artisan db:seed --force

migrate-seed: migrate seed

fresh:
	$(BACKEND) php artisan migrate:fresh --seed --force

# Usage: make artisan CMD="route:list"
artisan:
	@test -n "$(CMD)" || (echo 'Usage: make artisan CMD="route:list"' && exit 1)
	$(BACKEND) php artisan $(CMD)

tinker:
	$(BACKEND_TTY) php artisan tinker

shell:
	$(BACKEND_TTY) sh

# ── Tests ───────────────────────────────────────────────────

test:
	$(BACKEND) php artisan test

test-filter:
	@test -n "$(FILTER)" || (echo 'Usage: make test-filter FILTER=PaymentTest' && exit 1)
	$(BACKEND) php artisan test --filter=$(FILTER)

frontend-check:
	cd frontend && pnpm typecheck && pnpm build

# ── Production compose ──────────────────────────────────────

prod-up:
	$(DC_PROD) up -d --build

prod-down:
	$(DC_PROD) down

prod-logs:
	$(DC_PROD) logs -f --tail=200

prod-migrate:
	$(PROD_BACKEND) php artisan migrate --force

prod-seed:
	$(PROD_BACKEND) php artisan db:seed --force

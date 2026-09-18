.PHONY: up down logs test backend-test frontend-check migrate seed

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f --tail=200

backend-test:
	cd backend && php artisan test

frontend-check:
	cd frontend && pnpm typecheck && pnpm build

test: backend-test frontend-check

migrate:
	cd backend && php artisan migrate

seed:
	cd backend && php artisan db:seed

prod-up:
	docker compose -f docker-compose.prod.yaml up -d --build

prod-down:
	docker compose -f docker-compose.prod.yaml down

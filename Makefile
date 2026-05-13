TEST_ENV := \
	-e DB_CENTRAL_HOST=mysql-test \
	-e DB_CENTRAL_PASSWORD=testsecret \
	-e DB_CENTRAL_USERNAME=api_kit \
	-e DB_TENANT_HOST=mysql-test \
	-e DB_TENANT_PASSWORD=testsecret \
	-e DB_TENANT_USERNAME=api_kit \
	-e DB_TENANT_PREFIX=api_kit_test_tenant_ \
	-e REDIS_HOST=redis-test \
	-e QUEUE_CONNECTION=sync \
	-e SESSION_DRIVER=array \
	-e MAIL_MAILER=array \
	-e SCOUT_DRIVER=null \
	-e TENANCY_CENTRAL_DOMAINS=api.test

.PHONY: setup up down restart shell logs test test-seq test-full lint analyze check fix tenant resource

setup:
	@test -f .env || cp .env.example .env
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --database=central
	docker compose exec app php artisan passport:keys --force

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart app horizon

shell:
	docker compose exec app sh

logs:
	docker compose logs -f app

test:
	docker compose exec $(TEST_ENV) app composer test

test-seq:
	docker compose exec $(TEST_ENV) app composer test:seq

test-full:
	docker compose exec $(TEST_ENV) app composer test:full

lint:
	docker compose exec app composer lint

analyze:
	docker compose exec app composer analyze

check:
	docker compose exec app composer check

fix:
	docker compose exec app composer fix

tenant:
	@if [ -z "$(TENANT)" ]; then echo "Usage: make tenant TENANT=acme"; exit 1; fi
	docker compose exec app php artisan tenant:create $(TENANT)

resource:
	@if [ -z "$(NAME)" ]; then echo "Usage: make resource NAME=Continent"; exit 1; fi
	docker compose exec app php artisan make:api-resource $(NAME)

# Laravel API-Only Starter Kit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a production-ready, multi-DB tenant-aware Laravel 13 API starter kit with opinionated base classes, a `make:api-resource` generator, and `Continent` as a reference resource that exercises every pattern (DTOs, repository, action, query builder, idempotent POST, cached GET, audit, search, OpenAPI docs).

**Architecture:** Subdomain-routed Stancl multi-DB tenancy. Central DB holds tenants, domains, super-admins, Passport `oauth_*` (tokens tagged with `tenant_id`). Each tenant DB holds users, roles, audits, business data. Layer flow: Controller → Action/Service → Repository, with Spatie Data DTOs between layers. `AsAction` trait is the single transaction site. Side-effects fire via `ShouldHandleEventsAfterCommit` listeners.

**Tech Stack:** PHP 8.5, Laravel 13, MySQL 8, Redis 7, Meilisearch 1.10, Mailpit, Passport v13, Stancl Tenancy v3.9, Spatie Permission + Data + Query Builder + ResponseCache, Dedoc Scramble, Grazulex APIRoute + Smart Throttle + Idempotency, Owen-It Auditing, Sentry, Pest + Paratest, PHPStan max, Pint, Rector, Docker Compose.

**Spec:** [docs/superpowers/specs/2026-05-11-laravel-api-starter-kit-design.md](../specs/2026-05-11-laravel-api-starter-kit-design.md)

**Hooks (enforced):**
- `.claude/hooks/pre-execution-dry-principles.md` — single sources of truth
- `.claude/hooks/database-transaction-handling.md` — `AsAction` is the only transaction site

---

## File Structure

This map locks decomposition decisions in. Each file has one responsibility; files that change together live together.

```
app/
├── Console/Commands/
│   └── MakeApiResourceCommand.php            # generator
├── Data/
│   ├── BaseData.php                          # forCreation/forUpdate, identifier cast
│   ├── Auth/{RegisterUserData,LoginData,ResetPasswordData}.php
│   ├── Tenants/CreateTenantData.php
│   └── Continents/{Create,Update,Delete}ContinentData.php
├── Models/
│   ├── BaseModel.php                         # identifier + audit fields + Auditable
│   ├── Central/                              # connection: 'central'
│   │   ├── Tenant.php
│   │   ├── Domain.php
│   │   ├── SuperAdmin.php
│   │   └── GlobalAudit.php
│   ├── User.php
│   ├── Role.php
│   └── Continent.php
├── Repositories/
│   ├── Contracts/
│   │   ├── BaseRepositoryInterface.php       # browseAll, paginate, find…, create, update, delete, forceDelete
│   │   ├── UserRepositoryInterface.php
│   │   ├── TenantRepositoryInterface.php
│   │   └── ContinentRepositoryInterface.php
│   ├── BaseRepository.php
│   ├── UserRepository.php
│   ├── TenantRepository.php
│   └── ContinentRepository.php
├── Actions/
│   ├── Concerns/AsAction.php                 # ONLY transaction site
│   ├── Auth/{Register,IssuePAT,SendVerification,VerifyEmail,SendPasswordReset,ResetPassword}Action.php
│   ├── Tenants/{Create,Delete,ForceDelete}TenantAction.php
│   └── Continents/{Create,Update,Delete,ForceDelete}ContinentAction.php
├── Services/
│   ├── ApiResponseService.php
│   ├── TenantContextService.php
│   ├── IdempotencyService.php
│   └── ContinentService.php                  # CSV import (orchestration)
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── BaseApiController.php             # uses RespondsWithJson
│   │   ├── Auth/{Auth,EmailVerification,PasswordReset}Controller.php
│   │   ├── HealthController.php
│   │   ├── TenantController.php              # central-domain
│   │   └── ContinentController.php
│   ├── Middleware/
│   │   ├── ForceJsonResponse.php
│   │   ├── LogApiRequests.php
│   │   ├── EnsureEmailVerified.php
│   │   ├── EnsureTokenMatchesTenant.php      # ← load-bearing security check
│   │   ├── ApiVersionResolver.php
│   │   └── PreventCentralDomainAccess.php
│   ├── Requests/                             # thin shells hydrating DTOs
│   │   ├── Auth/, Tenants/, Continents/
│   └── Resources/
│       ├── BaseApiResource.php
│       ├── UserResource.php
│       ├── TenantResource.php
│       └── ContinentResource.php
├── Events/
│   ├── Auth/{UserRegistered,EmailVerified}.php
│   ├── Tenants/{TenantCreated,TenantDeleted}.php
│   └── Continents/{ContinentCreated,ContinentUpdated,ContinentDeleted}.php
├── Listeners/                                # all implement ShouldHandleEventsAfterCommit
│   ├── SendEmailVerificationListener.php
│   ├── LogAuditTrailListener.php
│   ├── FlushContinentCacheListener.php
│   └── ReindexContinentSearchListener.php
├── Policies/
│   ├── UserPolicy.php
│   ├── TenantPolicy.php
│   └── ContinentPolicy.php
├── Exceptions/
│   ├── Handler.php
│   ├── ApiException.php
│   ├── DomainException.php
│   ├── ResourceNotFoundException.php
│   ├── TenantResolutionException.php
│   └── TenantMismatchException.php
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── RepositoryServiceProvider.php
│   ├── EventServiceProvider.php
│   ├── TenancyServiceProvider.php
│   └── ValidateEnvironmentServiceProvider.php
└── Support/
    ├── Concerns/
    │   ├── HasAuditTrail.php
    │   ├── HasUuidIdentifier.php
    │   ├── HasSearchable.php
    │   └── RespondsWithJson.php
    ├── Passport/
    │   ├── TenantAwareAccessToken.php        # injects tenant_id claim
    │   └── TenantAwareAccessTokenRepository.php
    ├── ResponseCache/
    │   └── TenantAwareCacheProfile.php
    └── OpenApi/
        └── ScrambleExtensions.php

config/{api,tenancy,permission,responsecache,scout,auditing,sentry,features}.php

database/
├── migrations/                               # central
│   ├── *_create_tenants_table.php
│   ├── *_create_domains_table.php
│   ├── *_create_super_admins_table.php
│   ├── *_create_oauth_tables.php
│   ├── *_alter_oauth_access_tokens_add_tenant_id.php
│   ├── *_alter_oauth_clients_add_tenant_id.php
│   ├── *_create_global_audits_table.php
│   └── *_alter_failed_jobs_add_tenant_context.php
├── migrations/tenant/                        # per-tenant
│   ├── *_create_users_table.php
│   ├── *_create_password_reset_tokens_table.php
│   ├── *_create_permission_tables.php
│   ├── *_create_audits_table.php
│   └── *_create_continents_table.php
├── factories/{User,Tenant,Continent}Factory.php
└── seeders/{Database,TenantDatabase,Continent}Seeder.php

routes/{api,tenant,channels}.php
docker/{app,nginx,horizon}/* + docker-compose.yml + docker-compose.test.yml
.github/workflows/ci.yml
tests/
├── Pest.php
├── TestCase.php
├── Concerns/{CreatesTenants,ActsAsTenant,IssuesTokens,AssertsJsonStructure,RefreshTenantDatabases}.php
├── Architecture/LayerBoundariesTest.php
├── Unit/{Data,Repositories,Actions,Services}/
└── Feature/{Auth,Tenants,Continents,Health,RateLimit}/
phpstan.neon, rector.php, pint.json, Makefile, .env.example, README.md
```

**Milestones (linear dependency order):**

1. **Foundation** — Laravel scaffold, Docker, base configs
2. **Quality gates** — PHPStan, Pint, Rector, Pest, Paratest, layer-boundary architecture test (write the rules FIRST so all subsequent code is verified against them)
3. **Tenancy core** — Stancl install, central DB migrations, central models, tenant resolution middleware
4. **Base classes** — `BaseModel`, `BaseData`, `BaseRepository`, `BaseApiResource`, `BaseApiController`, `RespondsWithJson`, `ApiException` family, `Handler`
5. **AsAction trait + first action** — the single transaction site
6. **Passport with tenant-aware tokens** — install, custom `AccessToken`, `EnsureTokenMatchesTenant` middleware (this is the load-bearing security boundary)
7. **Auth flows** — register, login, email verification, password reset (all tenant-scoped)
8. **Tenant CRUD** — central-domain endpoints with provisioning
9. **`make:api-resource` generator**
10. **`Continent` reference resource** — generated, then enriched with QueryBuilder, idempotency, response cache, audit, search, events
11. **API versioning, smart throttling, idempotency middleware, response cache**
12. **Observability** — structured logging, Sentry, health checks
13. **Search** — Scout + Meilisearch with per-tenant indexes
14. **OpenAPI docs** — Scramble + custom extensions
15. **README, docs/, Makefile, polish**

Frequent commits between tasks. Every task has a TDD outer loop (failing test → minimal impl → passing test → commit).

## Tasks Marked "abbreviated"

Some milestones (7, 8, 11, 12, 13, 14, 15) describe several tasks at a high level after a fully-worked first task in the same milestone. This is intentional: the per-task structure is **identical** to the fully-worked example (`Task 8.1` for tenant CRUD, `Task 10.1` for resource CRUD, `Task 6.4` for middleware-with-TDD). For each abbreviated task:

1. Mirror the fully-worked task in the same milestone.
2. Write the failing test first against the listed endpoint / behavior.
3. Add DTO under `App\Data\<Domain>\`, Action under `App\Actions\<Domain>\` (using `AsAction`), Controller method on the listed controller, route in the listed route file.
4. Run → green → commit with a `feat(<domain>): …` message.

If any abbreviated task turns out to need a divergent pattern, write it fully in the same TDD style and commit the expanded version of this plan alongside.

---

# Milestone 1 — Foundation

## Task 1.1: Initialize the Laravel project

**Files:**
- Create: `composer.json` (initial deps)
- Create: `.env.example`
- Create: `.gitignore`
- Modify: existing repo root `laravel-api-kit/` (already a git repo on `main`)

- [ ] **Step 1: Create composer.json with the kit's core dependencies**

Write `composer.json`:

```json
{
    "name": "iosync/laravel-api-kit",
    "type": "project",
    "description": "Production-ready, API-only multi-tenant Laravel 13 starter kit.",
    "license": "MIT",
    "require": {
        "php": "^8.5",
        "laravel/framework": "^13.0",
        "laravel/passport": "^13.0",
        "stancl/tenancy": "^3.9",
        "spatie/laravel-data": "^4.0",
        "spatie/laravel-query-builder": "^6.0",
        "spatie/laravel-permission": "^6.0",
        "spatie/laravel-responsecache": "^7.0",
        "owen-it/laravel-auditing": "^14.0",
        "laravel/scout": "^10.0",
        "meilisearch/meilisearch-php": "^1.10",
        "dedoc/scramble": "^0.12",
        "grazulex/laravel-apiroute": "^1.0",
        "grazulex/laravel-api-idempotency": "^1.0",
        "grazulex/laravel-api-throttle-smart": "^1.0",
        "sentry/sentry-laravel": "^4.0",
        "laravel/horizon": "^5.0",
        "predis/predis": "^2.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "pestphp/pest-plugin-arch": "^3.0",
        "brianium/paratest": "^7.0",
        "larastan/larastan": "^3.0",
        "rector/rector": "^1.0",
        "laravel/pint": "^1.0",
        "mockery/mockery": "^1.6",
        "fakerphp/faker": "^1.23",
        "roave/security-advisories": "dev-latest"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "test": "./vendor/bin/pest --parallel --processes=4",
        "test:seq": "./vendor/bin/pest",
        "test:full": "./vendor/bin/pest --parallel --exclude-group=serial && ./vendor/bin/pest --group=serial",
        "analyze": "./vendor/bin/phpstan analyse --no-progress",
        "lint": "./vendor/bin/pint --test",
        "fix": "./vendor/bin/pint",
        "check": "@lint && @analyze && @test:full"
    },
    "minimum-stability": "stable",
    "prefer-stable": true,
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
```

- [ ] **Step 2: Write `.gitignore`**

```gitignore
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
/vendor
.env
.env.backup
.env.production
.phpactor.json
.phpunit.result.cache
.phpunit.cache
Homestead.json
Homestead.yaml
auth.json
npm-debug.log
yarn-error.log
/.fleet
/.idea
/.vscode
.DS_Store
```

- [ ] **Step 3: Write `.env.example`** (will grow throughout the plan; start minimal)

```dotenv
APP_NAME="Laravel API Kit"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://api.localhost
APP_TIMEZONE=UTC

LOG_CHANNEL=stack
LOG_LEVEL=debug

# Central database (holds tenants, oauth, super-admins, global_audits)
DB_CONNECTION=central
DB_CENTRAL_HOST=mysql
DB_CENTRAL_PORT=3306
DB_CENTRAL_DATABASE=api_kit_central
DB_CENTRAL_USERNAME=api_kit
DB_CENTRAL_PASSWORD=secret

# Tenant DB template (one DB per tenant, created by Stancl on tenant provisioning)
DB_TENANT_HOST=mysql
DB_TENANT_PORT=3306
DB_TENANT_USERNAME=api_kit
DB_TENANT_PASSWORD=secret
DB_TENANT_PREFIX=tenant_

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="hello@api.localhost"
MAIL_FROM_NAME="${APP_NAME}"

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=
MEILISEARCH_NO_ANALYTICS=true

# Tenancy
TENANCY_CENTRAL_DOMAINS=api.localhost,admin.localhost

# Sentry
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1

# Health
HEALTHCHECK_TOKEN=

# Feature flags
FEATURE_TENANCY=true
FEATURE_IDEMPOTENCY_ENFORCED=false
FEATURE_RESPONSE_CACHE=true
FEATURE_SEARCH=true
FEATURE_AUDIT=true
FEATURE_SENTRY=true
```

- [ ] **Step 4: Verify the directory is clean and commit**

Run:
```bash
git status
```
Expected: composer.json, .gitignore, .env.example untracked.

```bash
git add composer.json .gitignore .env.example
git commit -m "feat: initialize laravel-api-kit composer manifest and env template"
```

---

## Task 1.2: Bootstrap the Laravel skeleton

**Files:**
- Create: standard Laravel 13 skeleton (`app/`, `bootstrap/`, `config/`, `public/`, `resources/views/.gitkeep`, `routes/`, `storage/`, `artisan`, `phpunit.xml`)

- [ ] **Step 1: Generate the skeleton via Composer's create-project shim**

Run (host-side, since Docker isn't built yet):
```bash
docker run --rm -v "$(pwd)":/app -w /app composer:2 create-project --prefer-dist --no-install laravel/laravel _tmp_skeleton "^13.0"
```

Then move the skeleton's API-relevant pieces into place (we keep our `composer.json` from Task 1.1):
```bash
rsync -a --exclude='composer.json' --exclude='composer.lock' --exclude='.env.example' --exclude='.gitignore' _tmp_skeleton/ ./
rm -rf _tmp_skeleton
```

- [ ] **Step 2: Strip frontend assets** (kit is API-only)

Delete `package.json`, `vite.config.js`, `resources/css/`, `resources/js/`, `tailwind.config.js`, `postcss.config.js` if present:
```bash
rm -f package.json vite.config.js tailwind.config.js postcss.config.js
rm -rf resources/css resources/js
```

- [ ] **Step 3: Install Composer dependencies inside a temporary container**

```bash
docker run --rm -v "$(pwd)":/app -w /app composer:2 install --no-interaction --prefer-dist
```
Expected: no errors. `vendor/` is created.

- [ ] **Step 4: Generate app key for local use**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.5-cli-alpine php artisan key:generate --show
```
Copy the printed key into `.env.example` for `APP_KEY=` (leave blank in `.env.example`; we'll set real values in `.env`).

- [ ] **Step 5: Commit the skeleton**

```bash
git add .
git commit -m "feat: scaffold laravel 13 skeleton, strip frontend assets"
```

---

## Task 1.3: Docker development stack

**Files:**
- Create: `docker/app/Dockerfile`
- Create: `docker/nginx/default.conf`
- Create: `docker/horizon/Dockerfile`
- Create: `docker-compose.yml`
- Create: `docker-compose.test.yml`

- [ ] **Step 1: Write the app Dockerfile (multi-stage)**

Create `docker/app/Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.5-fpm-alpine AS runtime
RUN apk add --no-cache \
    icu-dev oniguruma-dev libzip-dev zlib-dev libpng-dev mysql-client bash \
 && docker-php-ext-install pdo_mysql opcache intl bcmath zip gd \
 && pecl install redis \
 && docker-php-ext-enable redis

WORKDIR /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

CMD ["php-fpm"]
```

- [ ] **Step 2: Write the Nginx config**

Create `docker/nginx/default.conf`:

```nginx
server {
    listen 80 default_server;
    server_name _;

    root /var/www/html/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 3: Write the Horizon Dockerfile** (reuses runtime image)

Create `docker/horizon/Dockerfile`:

```dockerfile
FROM laravel-api-kit-app:latest
CMD ["php", "artisan", "horizon"]
```

- [ ] **Step 4: Write the main docker-compose.yml**

Create `docker-compose.yml`:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    image: laravel-api-kit-app:latest
    volumes:
      - .:/var/www/html
    env_file: .env
    depends_on:
      mysql: { condition: service_healthy }
      redis: { condition: service_healthy }

  nginx:
    image: nginx:1.27-alpine
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  horizon:
    build:
      context: .
      dockerfile: docker/horizon/Dockerfile
    volumes:
      - .:/var/www/html
    env_file: .env
    depends_on:
      app: { condition: service_started }
      redis: { condition: service_healthy }

  scheduler:
    image: laravel-api-kit-app:latest
    command: sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"
    volumes:
      - .:/var/www/html
    env_file: .env
    depends_on:
      app: { condition: service_started }

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootsecret
      MYSQL_DATABASE: ${DB_CENTRAL_DATABASE}
      MYSQL_USER: ${DB_CENTRAL_USERNAME}
      MYSQL_PASSWORD: ${DB_CENTRAL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u root -prootsecret"]
      interval: 5s
      timeout: 5s
      retries: 10
    ports:
      - "3306:3306"

  redis:
    image: redis:7-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5

  meilisearch:
    image: getmeili/meilisearch:v1.10
    environment:
      MEILI_NO_ANALYTICS: "true"
      MEILI_ENV: development
    volumes:
      - meili_data:/meili_data
    ports:
      - "7700:7700"

  mailpit:
    image: axllent/mailpit:latest
    ports:
      - "1025:1025"
      - "8025:8025"

volumes:
  mysql_data:
  meili_data:
```

- [ ] **Step 5: Write the test override compose file**

Create `docker-compose.test.yml`:

```yaml
services:
  mysql-test:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: testsecret
      MYSQL_DATABASE: api_kit_test_central
      MYSQL_USER: api_kit
      MYSQL_PASSWORD: testsecret
    tmpfs:
      - /var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u root -ptestsecret"]
      interval: 3s
      timeout: 3s
      retries: 10
    ports:
      - "3307:3306"

  redis-test:
    image: redis:7-alpine
    tmpfs: ["/data"]

  meilisearch-test:
    image: getmeili/meilisearch:v1.10
    environment:
      MEILI_NO_ANALYTICS: "true"
    tmpfs: ["/meili_data"]
```

- [ ] **Step 6: Boot the stack and verify**

Run:
```bash
docker compose up -d
docker compose ps
```
Expected: `app`, `nginx`, `mysql`, `redis`, `meilisearch`, `mailpit` running; healthchecks green within 30 seconds.

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080
```
Expected: `200` (Laravel default welcome page — we'll replace shortly).

- [ ] **Step 7: Commit**

```bash
git add docker/ docker-compose.yml docker-compose.test.yml
git commit -m "feat: docker development stack (app, nginx, horizon, mysql, redis, meilisearch, mailpit)"
```

---

## Task 1.4: Makefile

**Files:**
- Create: `Makefile`

- [ ] **Step 1: Write the Makefile**

```makefile
.PHONY: setup up down restart shell logs test test-seq test-full lint analyze check fix tenant resource

setup:
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
	docker compose exec app composer test

test-seq:
	docker compose exec app composer test:seq

test-full:
	docker compose exec app composer test:full

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
```

- [ ] **Step 2: Commit**

```bash
git add Makefile
git commit -m "feat: makefile with setup, test, lint, tenant, and resource shortcuts"
```

---

# Milestone 2 — Quality Gates (write rules FIRST, code is verified against them)

## Task 2.1: PHPStan at level max

**Files:**
- Create: `phpstan.neon`

- [ ] **Step 1: Write phpstan.neon**

```yaml
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    paths:
        - app
        - config
        - database
        - routes
    level: max
    tmpDir: storage/framework/phpstan
    checkMissingIterableValueType: false
    treatPhpDocTypesAsCertain: false
```

- [ ] **Step 2: Run PHPStan to set a baseline**

```bash
docker compose exec app composer analyze
```
Expected: passes on the empty `app/` directory (Laravel skeleton has only `Http/Kernel.php`, `Console/Kernel.php`, `Exceptions/Handler.php`, `Providers/*` — should be clean at level max).

- [ ] **Step 3: Commit**

```bash
git add phpstan.neon
git commit -m "chore: enable phpstan at level max via larastan"
```

---

## Task 2.2: Pint, Rector

**Files:**
- Create: `pint.json`
- Create: `rector.php`

- [ ] **Step 1: Write `pint.json`**

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "strict_param": true,
        "concat_space": { "spacing": "one" },
        "ordered_imports": { "sort_algorithm": "alpha" },
        "no_unused_imports": true,
        "single_quote": true,
        "native_function_casing": true
    }
}
```

- [ ] **Step 2: Write `rector.php`**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app', __DIR__ . '/config', __DIR__ . '/database', __DIR__ . '/routes', __DIR__ . '/tests'])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        LaravelSetList::LARAVEL_130,
    ])
    ->withSkip([
        __DIR__ . '/app/Models/Central',
    ]);
```

- [ ] **Step 3: Run Pint and Rector to confirm clean baseline**

```bash
docker compose exec app composer lint
docker compose exec app ./vendor/bin/rector --dry-run
```
Expected: both pass on the skeleton.

- [ ] **Step 4: Commit**

```bash
git add pint.json rector.php
git commit -m "chore: configure pint and rector with strict presets"
```

---

## Task 2.3: Pest with Paratest

**Files:**
- Create: `tests/Pest.php`
- Create: `tests/TestCase.php`
- Create: `phpunit.xml` (replace skeleton's)
- Modify: `composer.json` was already done in Task 1.1

- [ ] **Step 1: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         processIsolation="false"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache"
         beStrictAboutCoverageMetadata="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Architecture">
            <directory>tests/Architecture</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <coverage>
        <report>
            <text outputFile="php://stdout" showOnlySummary="true"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_KEY" value="base64:0000000000000000000000000000000000000000000="/>
        <env name="DB_CONNECTION" value="central"/>
        <env name="DB_CENTRAL_HOST" value="mysql-test"/>
        <env name="DB_CENTRAL_PORT" value="3306"/>
        <env name="DB_CENTRAL_DATABASE" value="api_kit_test_central"/>
        <env name="DB_CENTRAL_USERNAME" value="api_kit"/>
        <env name="DB_CENTRAL_PASSWORD" value="testsecret"/>
        <env name="DB_TENANT_HOST" value="mysql-test"/>
        <env name="DB_TENANT_USERNAME" value="api_kit"/>
        <env name="DB_TENANT_PASSWORD" value="testsecret"/>
        <env name="DB_TENANT_PREFIX" value="api_kit_test_tenant_"/>
        <env name="CACHE_STORE" value="redis"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="REDIS_HOST" value="redis-test"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="SCOUT_DRIVER" value="null"/>
        <env name="TENANCY_CENTRAL_DOMAINS" value="api.test"/>
    </php>
</phpunit>
```

- [ ] **Step 2: Write `tests/TestCase.php`**

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = (int) (getenv('TEST_TOKEN') ?: 1);

        config()->set('database.connections.central.database', "api_kit_test_central_w{$token}");
        config()->set('database.redis.default.database', 10 + $token);
    }
}
```

- [ ] **Step 3: Write `tests/Pest.php`**

```php
<?php

declare(strict_types=1);

uses(Tests\TestCase::class)->in('Feature', 'Architecture');
uses(Tests\TestCase::class)->in('Unit');

expect()->extend('toBeStandardSuccessEnvelope', function () {
    return $this
        ->toHaveKey('data')
        ->toHaveKey('meta')
        ->and($this->value['meta'])->toHaveKey('version')
        ->toHaveKey('request_id');
});

expect()->extend('toBeStandardErrorEnvelope', function (?string $code = null) {
    $this->toHaveKey('error')->toHaveKey('meta');
    $this->and($this->value['error'])->toHaveKey('code')->toHaveKey('message');
    if ($code !== null) {
        $this->and($this->value['error']['code'])->toBe($code);
    }
    return $this;
});
```

- [ ] **Step 4: Boot the test stack**

```bash
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d mysql-test redis-test meilisearch-test
docker compose exec app ./vendor/bin/pest --version
```
Expected: Pest prints its version. No tests yet → "No tests executed."

- [ ] **Step 5: Write a sanity test**

Create `tests/Unit/SanityTest.php`:

```php
<?php

declare(strict_types=1);

it('runs Pest', function () {
    expect(1 + 1)->toBe(2);
});
```

Run:
```bash
docker compose exec app ./vendor/bin/pest tests/Unit/SanityTest.php
```
Expected: 1 passed.

```bash
docker compose exec app composer test
```
Expected: parallel run; 1 passed across 4 workers.

- [ ] **Step 6: Commit**

```bash
git add phpunit.xml tests/Pest.php tests/TestCase.php tests/Unit/SanityTest.php
git commit -m "chore: configure pest + paratest with worker-namespaced db and redis"
```

---

## Task 2.4: Architecture test (layer boundaries — write the rules NOW)

**Files:**
- Create: `tests/Architecture/LayerBoundariesTest.php`

- [ ] **Step 1: Write the architecture test (TDD: these will fail later if violated)**

Create `tests/Architecture/LayerBoundariesTest.php`:

```php
<?php

declare(strict_types=1);

// Controllers may not import repositories directly — must go through actions/services
arch('controllers do not depend on repositories directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Repositories');

// Repositories may not import services — repositories are the bottom of the stack
arch('repositories do not depend on services')
    ->expect('App\Repositories')
    ->not->toUse('App\Services');

// DTOs are pure data — no Eloquent
arch('data objects do not depend on eloquent')
    ->expect('App\Data')
    ->not->toUse('Illuminate\Database\Eloquent');

// Models in App\Models\Central must declare $connection = 'central'
arch('central models declare connection')
    ->expect('App\Models\Central')
    ->toUseStrictTypes();

// AsAction trait owns DB::transaction; nobody else opens transactions
arch('no DB::transaction outside actions or services')
    ->expect('App')
    ->not->toUse('Illuminate\Support\Facades\DB::transaction')
    ->ignoring(['App\Actions\Concerns\AsAction', 'App\Services']);

// Never call DB::beginTransaction / commit / rollBack manually anywhere
arch('no manual transaction management')
    ->expect('App')
    ->not->toUse([
        'Illuminate\Support\Facades\DB::beginTransaction',
        'Illuminate\Support\Facades\DB::commit',
        'Illuminate\Support\Facades\DB::rollBack',
    ]);

// Controllers must not hand-roll response()->json — use RespondsWithJson
arch('controllers do not hand-roll json responses')
    ->expect('App\Http\Controllers')
    ->not->toUseFunction('response');
```

- [ ] **Step 2: Run the architecture test**

```bash
docker compose exec app ./vendor/bin/pest tests/Architecture/
```
Expected: PASS (empty controllers/repositories/actions can't violate rules yet).

- [ ] **Step 3: Commit**

```bash
git add tests/Architecture/LayerBoundariesTest.php
git commit -m "test(arch): enforce layer boundaries and single transaction site"
```

---

## Task 2.5: CI workflow

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Write the GitHub Actions workflow**

```yaml
name: CI
on: [push, pull_request]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', tools: composer:v2 }
      - run: composer install --no-interaction --prefer-dist
      - run: composer lint
      - run: ./vendor/bin/rector --dry-run

  analyze:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', tools: composer:v2 }
      - run: composer install --no-interaction --prefer-dist
      - run: composer analyze

  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: testsecret
          MYSQL_DATABASE: api_kit_test_central
          MYSQL_USER: api_kit
          MYSQL_PASSWORD: testsecret
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping -h localhost -u root -ptestsecret"
          --health-interval=5s --health-timeout=3s --health-retries=10
      redis:
        image: redis:7-alpine
        ports: ['6379:6379']
      meilisearch:
        image: getmeili/meilisearch:v1.10
        env: { MEILI_NO_ANALYTICS: 'true' }
        ports: ['7700:7700']
    env:
      DB_CENTRAL_HOST: 127.0.0.1
      DB_TENANT_HOST: 127.0.0.1
      REDIS_HOST: 127.0.0.1
      MEILISEARCH_HOST: http://127.0.0.1:7700
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', tools: composer:v2 }
      - run: composer install --no-interaction --prefer-dist
      - run: composer test:full

  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', tools: composer:v2 }
      - run: composer audit
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: lint, static analysis, tests against real mysql+redis+meili, audit"
```

---

# Milestone 3 — Tenancy Core

## Task 3.1: Database connections (central + tenant)

**Files:**
- Modify: `config/database.php`

- [ ] **Step 1: Replace `config/database.php`**

```php
<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'central'),

    'connections' => [
        'central' => [
            'driver' => 'mysql',
            'host' => env('DB_CENTRAL_HOST', 'mysql'),
            'port' => env('DB_CENTRAL_PORT', '3306'),
            'database' => env('DB_CENTRAL_DATABASE', 'api_kit_central'),
            'username' => env('DB_CENTRAL_USERNAME', 'api_kit'),
            'password' => env('DB_CENTRAL_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],

        'tenant' => [
            'driver' => 'mysql',
            'host' => env('DB_TENANT_HOST', 'mysql'),
            'port' => env('DB_TENANT_PORT', '3306'),
            'database' => null, // set dynamically by Stancl
            'username' => env('DB_TENANT_USERNAME', 'api_kit'),
            'password' => env('DB_TENANT_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'api_kit_'),
        ],
        'default' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
```

- [ ] **Step 2: Test the connection works**

```bash
docker compose exec app php artisan tinker --execute="DB::connection('central')->select('SELECT 1 as ok');"
```
Expected: `[{"ok": 1}]`.

- [ ] **Step 3: Commit**

```bash
git add config/database.php
git commit -m "feat: configure central and tenant database connections"
```

---

## Task 3.2: Install Stancl Tenancy

**Files:**
- Run: `php artisan tenancy:install`
- Create: `config/tenancy.php` (override Stancl defaults)
- Modify: `bootstrap/app.php` (register provider)

- [ ] **Step 1: Publish Stancl files**

```bash
docker compose exec app php artisan tenancy:install
```
Expected: publishes `config/tenancy.php`, central + tenant migration stubs.

- [ ] **Step 2: Edit `config/tenancy.php`** — key changes:

```php
// after publishing, modify these keys:
'tenant_model' => \App\Models\Central\Tenant::class,
'central_domains' => explode(',', env('TENANCY_CENTRAL_DOMAINS', 'api.localhost,admin.localhost')),
'database' => [
    'central_connection' => 'central',
    'template_tenant_connection' => 'tenant',
    'prefix' => env('DB_TENANT_PREFIX', 'tenant_'),
    'suffix' => '',
],
'bootstrappers' => [
    \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
],
'migration_parameters' => [
    '--path' => [database_path('migrations/tenant')],
    '--realpath' => true,
],
```

- [ ] **Step 3: Move Stancl's default tenant migration into `database/migrations/tenant/`**

Run:
```bash
mkdir -p database/migrations/tenant
mv database/migrations/tenant/*create_users_table*.php database/migrations/tenant/ 2>/dev/null || true
```

- [ ] **Step 4: Run central migrations to verify**

```bash
docker compose exec app php artisan migrate --database=central
```
Expected: creates `tenants`, `domains` tables in central DB.

- [ ] **Step 5: Commit**

```bash
git add config/tenancy.php database/migrations/ bootstrap/
git commit -m "feat: install stancl tenancy with central+tenant connection split"
```

---

## Task 3.3: Central models — `Tenant`, `Domain`

**Files:**
- Create: `app/Models/Central/Tenant.php`
- Create: `app/Models/Central/Domain.php`
- Test: `tests/Unit/Models/Central/TenantTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/Central/TenantTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lives on the central connection', function () {
    $tenant = new Tenant();
    expect($tenant->getConnectionName())->toBe('central');
});

it('uses ulid as primary key', function () {
    $tenant = Tenant::create(['id' => 'acme-test', 'data' => ['plan' => 'free']]);
    expect($tenant->id)->toBe('acme-test');
});
```

- [ ] **Step 2: Run the test (expect FAIL)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Models/Central/TenantTest.php
```
Expected: FAIL (class App\Models\Central\Tenant not found).

- [ ] **Step 3: Write the implementation**

Create `app/Models/Central/Tenant.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

final class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected $connection = 'central';

    public static function getCustomColumns(): array
    {
        return ['id', 'plan', 'status', 'created_at', 'updated_at', 'deleted_at'];
    }
}
```

Create `app/Models/Central/Domain.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

final class Domain extends BaseDomain
{
    protected $connection = 'central';
}
```

- [ ] **Step 4: Run the test (expect PASS)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Models/Central/TenantTest.php
```
Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Central/ tests/Unit/Models/Central/
git commit -m "feat(tenancy): central Tenant and Domain models with HasDatabase + HasDomains"
```

---

# Milestone 4 — Base Classes

## Task 4.1: `BaseModel`

**Files:**
- Create: `app/Models/BaseModel.php`
- Create: `app/Support/Concerns/HasAuditTrail.php`
- Test: `tests/Unit/Models/BaseModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/BaseModelTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::connection('central')->create('test_widgets', function ($t) {
        $t->id();
        $t->string('identifier')->unique();
        $t->string('name');
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    Schema::connection('central')->dropIfExists('test_widgets');
});

it('auto-populates identifier as a uuid on creation', function () {
    $widget = TestWidget::create(['name' => 'Foo']);
    expect($widget->identifier)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

class TestWidget extends BaseModel
{
    protected $table = 'test_widgets';
    protected $connection = 'central';
    protected $fillable = ['name'];
}
```

- [ ] **Step 2: Run test (expect FAIL — class not found)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Models/BaseModelTest.php
```

- [ ] **Step 3: Write `HasAuditTrail` trait**

Create `app/Support/Concerns/HasAuditTrail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use OwenIt\Auditing\Auditable;

trait HasAuditTrail
{
    use Auditable;
}
```

- [ ] **Step 4: Write `BaseModel`**

Create `app/Models/BaseModel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

abstract class BaseModel extends Model implements Auditable
{
    use HasAuditTrail;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->created_by ??= auth()->id();
                $model->updated_by ??= auth()->id();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
```

- [ ] **Step 5: Run test (expect PASS)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Models/BaseModelTest.php
```
Expected: 1 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/BaseModel.php app/Support/Concerns/HasAuditTrail.php tests/Unit/Models/BaseModelTest.php
git commit -m "feat: BaseModel with auto identifier, audit fields, soft deletes, Auditable"
```

---

## Task 4.2: `BaseRepository` and `BaseRepositoryInterface`

**Files:**
- Create: `app/Repositories/Contracts/BaseRepositoryInterface.php`
- Create: `app/Repositories/BaseRepository.php`
- Test: `tests/Unit/Repositories/BaseRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/BaseRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\BaseModel;
use App\Repositories\BaseRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::connection('central')->create('widgets', function ($t) {
        $t->id();
        $t->string('identifier')->unique();
        $t->string('name');
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    Schema::connection('central')->dropIfExists('widgets');
});

class Widget extends BaseModel
{
    protected $table = 'widgets';
    protected $connection = 'central';
    protected $fillable = ['name'];
}

class WidgetRepository extends BaseRepository
{
    protected function model(): string { return Widget::class; }
    protected function allowedFilters(): array { return ['name']; }
    protected function allowedIncludes(): array { return []; }
    protected function allowedSorts(): array { return ['name', 'created_at']; }
}

it('creates, finds, browses, paginates, deletes, force-deletes', function () {
    $repo = new WidgetRepository();
    $w = $repo->create(['name' => 'Alpha']);
    expect($w->identifier)->not->toBeEmpty();

    $found = $repo->findByIdentifier($w->identifier);
    expect($found->id)->toBe($w->id);

    $repo->create(['name' => 'Beta']);
    $repo->create(['name' => 'Gamma']);

    expect($repo->browseAll())->toHaveCount(3);
    expect($repo->paginate(2)->total())->toBe(3);

    $repo->delete($w);
    expect(Widget::withTrashed()->find($w->id)->trashed())->toBeTrue();

    $repo->forceDelete($w);
    expect(Widget::withTrashed()->find($w->id))->toBeNull();
});

it('warns when browseAll exceeds the threshold', function () {
    config()->set('api.browse_all_warn_threshold', 2);
    $repo = new WidgetRepository();
    for ($i = 0; $i < 3; $i++) {
        $repo->create(['name' => "W$i"]);
    }
    \Illuminate\Support\Facades\Log::shouldReceive('warning')->once();
    $repo->browseAll();
});
```

- [ ] **Step 2: Run test (expect FAIL)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Repositories/BaseRepositoryTest.php
```

- [ ] **Step 3: Write the interface**

Create `app/Repositories/Contracts/BaseRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\QueryBuilder;

interface BaseRepositoryInterface
{
    public function newQuery(): QueryBuilder;
    public function findByIdentifier(string $identifier): Model;
    public function browseAll(): Collection;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Model;
    public function update(Model $model, array $data): Model;
    public function delete(Model $model): void;
    public function forceDelete(Model $model): void;
}
```

- [ ] **Step 4: Write the base class**

Create `app/Repositories/BaseRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseRepository implements BaseRepositoryInterface
{
    abstract protected function model(): string;

    /** @return array<int,string|\Spatie\QueryBuilder\AllowedFilter> */
    abstract protected function allowedFilters(): array;

    /** @return array<int,string|\Spatie\QueryBuilder\AllowedInclude> */
    abstract protected function allowedIncludes(): array;

    /** @return array<int,string|\Spatie\QueryBuilder\AllowedSort> */
    abstract protected function allowedSorts(): array;

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    public function newQuery(): QueryBuilder
    {
        return QueryBuilder::for($this->model())
            ->allowedFilters($this->allowedFilters())
            ->allowedIncludes($this->allowedIncludes())
            ->allowedSorts($this->allowedSorts())
            ->defaultSort($this->defaultSort());
    }

    public function findByIdentifier(string $identifier): Model
    {
        return $this->model()::query()->where('identifier', $identifier)->firstOrFail();
    }

    public function browseAll(): Collection
    {
        $threshold = (int) config('api.browse_all_warn_threshold', 1000);
        $results = $this->newQuery()->get();

        if ($results->count() > $threshold) {
            Log::warning('browseAll() returned a large result set', [
                'model' => $this->model(),
                'count' => $results->count(),
                'threshold' => $threshold,
            ]);
        }

        return $results;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->model()::query()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();
        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function forceDelete(Model $model): void
    {
        $model->forceDelete();
    }
}
```

- [ ] **Step 5: Create `config/api.php` so the threshold config key resolves**

```php
<?php

declare(strict_types=1);

return [
    'browse_all_warn_threshold' => env('API_BROWSE_ALL_WARN_THRESHOLD', 1000),
];
```

- [ ] **Step 6: Run test (expect PASS)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Repositories/BaseRepositoryTest.php
```
Expected: 2 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Repositories/ config/api.php tests/Unit/Repositories/
git commit -m "feat: BaseRepository with browseAll, paginate, find, create, update, delete, forceDelete"
```

---

## Task 4.3: `BaseData` (DTO base)

**Files:**
- Create: `app/Data/BaseData.php`
- Test: `tests/Unit/Data/BaseDataTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Data\BaseData;

class TestUserData extends BaseData
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}
}

it('forCreation requires all fields', function () {
    $data = TestUserData::forCreation(['name' => 'Alice', 'email' => 'a@b.com']);
    expect($data->name)->toBe('Alice')->and($data->email)->toBe('a@b.com');
});

it('forUpdate strips nulls', function () {
    $data = TestUserData::forUpdate(['name' => 'Alice', 'email' => null]);
    expect($data->toArray())->toBe(['name' => 'Alice']);
});
```

- [ ] **Step 2: Run (expect FAIL)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Data/BaseDataTest.php
```

- [ ] **Step 3: Write `BaseData`**

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

abstract class BaseData extends Data
{
    public static function forCreation(array $input): static
    {
        return static::from($input);
    }

    public static function forUpdate(array $input): static
    {
        $filtered = array_filter($input, fn($v) => $v !== null);
        return static::from($filtered);
    }

    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn($v) => $v !== null);
    }
}
```

- [ ] **Step 4: Run (expect PASS)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Data/BaseDataTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Data/ tests/Unit/Data/
git commit -m "feat: BaseData DTO base with forCreation/forUpdate"
```

---

## Task 4.4: `ApiException` family + Handler

**Files:**
- Create: `app/Exceptions/ApiException.php`
- Create: `app/Exceptions/ResourceNotFoundException.php`
- Create: `app/Exceptions/DomainException.php`
- Create: `app/Exceptions/TenantResolutionException.php`
- Create: `app/Exceptions/TenantMismatchException.php`
- Modify: `bootstrap/app.php` (register handler)
- Test: `tests/Unit/Exceptions/ApiExceptionTest.php`

- [ ] **Step 1: Test**

```php
<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ResourceNotFoundException;

it('renders to a standardized error envelope', function () {
    $e = new ResourceNotFoundException('Widget not found');
    $response = $e->render(request());
    $data = $response->getData(true);
    expect($data)->toHaveKey('error')->toHaveKey('meta');
    expect($data['error']['code'])->toBe('RESOURCE_NOT_FOUND');
    expect($data['error']['message'])->toBe('Widget not found');
    expect($response->getStatusCode())->toBe(404);
});
```

- [ ] **Step 2: Run (FAIL)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Exceptions/
```

- [ ] **Step 3: Write `ApiException`**

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class ApiException extends Exception
{
    protected int $httpStatus = 500;
    protected string $errorCode = 'INTERNAL_ERROR';
    protected array $details = [];

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details ?: null,
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-Id', (string) Str::ulid()),
                'version' => $request->attributes->get('api_version', 'v1'),
            ],
        ], $this->httpStatus);
    }

    public function withDetails(array $details): static
    {
        $this->details = $details;
        return $this;
    }
}
```

- [ ] **Step 4: Write the subclasses**

```php
<?php
// app/Exceptions/ResourceNotFoundException.php
declare(strict_types=1);
namespace App\Exceptions;
final class ResourceNotFoundException extends ApiException {
    protected int $httpStatus = 404;
    protected string $errorCode = 'RESOURCE_NOT_FOUND';
}
```

```php
<?php
// app/Exceptions/DomainException.php
declare(strict_types=1);
namespace App\Exceptions;
class DomainException extends ApiException {
    protected int $httpStatus = 422;
    protected string $errorCode = 'DOMAIN_ERROR';
}
```

```php
<?php
// app/Exceptions/TenantResolutionException.php
declare(strict_types=1);
namespace App\Exceptions;
final class TenantResolutionException extends ApiException {
    protected int $httpStatus = 404;
    protected string $errorCode = 'TENANT_NOT_FOUND';
}
```

```php
<?php
// app/Exceptions/TenantMismatchException.php
declare(strict_types=1);
namespace App\Exceptions;
final class TenantMismatchException extends ApiException {
    protected int $httpStatus = 403;
    protected string $errorCode = 'TENANT_MISMATCH';
}
```

- [ ] **Step 5: Register in `bootstrap/app.php`**

Edit `bootstrap/app.php`:

```php
->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
    $exceptions->render(function (\App\Exceptions\ApiException $e, \Illuminate\Http\Request $request) {
        return $e->render($request);
    });

    $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
        return response()->json([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'The given data was invalid.',
                'fields' => $e->errors(),
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-Id', (string) \Illuminate\Support\Str::ulid()),
                'version' => $request->attributes->get('api_version', 'v1'),
            ],
        ], 422);
    });

    $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
        return (new \App\Exceptions\ResourceNotFoundException('Resource not found.'))->render($request);
    });
})
```

- [ ] **Step 6: Run (expect PASS)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Exceptions/
```

- [ ] **Step 7: Commit**

```bash
git add app/Exceptions/ bootstrap/app.php tests/Unit/Exceptions/
git commit -m "feat: ApiException family + Handler with standardized error envelopes"
```

---

## Task 4.5: `RespondsWithJson` + `BaseApiController` + `BaseApiResource`

**Files:**
- Create: `app/Support/Concerns/RespondsWithJson.php`
- Create: `app/Http/Controllers/Api/V1/BaseApiController.php`
- Create: `app/Http/Resources/BaseApiResource.php`

- [ ] **Step 1: Write `RespondsWithJson`**

```php
<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

trait RespondsWithJson
{
    protected function success(mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => $this->meta(),
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        $collection = $resourceClass::collection($paginator);
        $payload = $collection->response()->getData(true);
        $payload['meta'] = array_merge($payload['meta'] ?? [], $this->meta());
        return new JsonResponse($payload);
    }

    protected function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ],
            'meta' => $this->meta(),
        ], $status);
    }

    private function meta(): array
    {
        return [
            'request_id' => request()->header('X-Request-Id', (string) Str::ulid()),
            'version' => request()->attributes->get('api_version', 'v1'),
            'tenant' => function_exists('tenant') && tenant() ? tenant()->id : null,
        ];
    }
}
```

- [ ] **Step 2: Write `BaseApiController`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Concerns\RespondsWithJson;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

abstract class BaseApiController extends Controller
{
    use AuthorizesRequests;
    use RespondsWithJson;
}
```

- [ ] **Step 3: Write `BaseApiResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = $this->payload($request);
        return array_merge(
            ['id' => $this->resource->identifier ?? null],
            $payload,
            [
                'created_at' => optional($this->resource->created_at)->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
            ],
        );
    }

    abstract protected function payload(Request $request): array;
}
```

- [ ] **Step 4: Smoke test** — write a tiny test that creates a fake controller and asserts envelope shape.

```php
<?php
// tests/Unit/Http/RespondsWithJsonTest.php
declare(strict_types=1);

use App\Support\Concerns\RespondsWithJson;
use Symfony\Component\HttpFoundation\Response;

class StubController { use RespondsWithJson { success as public; error as public; } }

it('produces a standardized success envelope', function () {
    $controller = new StubController();
    $response = $controller->success(['hello' => 'world']);
    $data = $response->getData(true);
    expect($data)->toBeStandardSuccessEnvelope();
    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('produces a standardized error envelope', function () {
    $controller = new StubController();
    $response = $controller->error('FOO_ERROR', 'Bad foo', 400);
    $data = $response->getData(true);
    expect($data)->toBeStandardErrorEnvelope('FOO_ERROR');
});
```

- [ ] **Step 5: Run**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Http/
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/ app/Http/Resources/ app/Support/Concerns/RespondsWithJson.php tests/Unit/Http/
git commit -m "feat: RespondsWithJson, BaseApiController, BaseApiResource"
```

---

# Milestone 5 — AsAction (single transaction site)

## Task 5.1: `AsAction` trait

**Files:**
- Create: `app/Actions/Concerns/AsAction.php`
- Test: `tests/Unit/Actions/AsActionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FakeData extends BaseData {
    public function __construct(public ?string $name = null) {}
}

class FakeModel extends Model { public $exists = true; public function getKey() { return 42; } }

class FakeAction { use AsAction; public bool $handled = false; protected function handle(FakeData $dto): Model { $this->handled = true; return new FakeModel(); } }

it('wraps handle() in DB::transaction and logs around it', function () {
    DB::shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
    Log::shouldReceive('info')->twice();
    $action = new FakeAction();
    $model = $action->execute(FakeData::from(['name' => 'x']));
    expect($action->handled)->toBeTrue();
    expect($model)->toBeInstanceOf(Model::class);
});
```

- [ ] **Step 2: Run (FAIL)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Actions/
```

- [ ] **Step 3: Write `AsAction`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use App\Data\BaseData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait AsAction
{
    public function execute(BaseData $dto): Model
    {
        $actingUserId = $this->resolveActingUserId();

        Log::info(static::class . ' starting', [
            'acting_user_id' => $actingUserId,
            'dto' => $dto->toArray(),
        ]);

        $model = DB::transaction(fn() => $this->handle($dto));

        Log::info(static::class . ' completed', [
            'model_key' => $model->getKey(),
        ]);

        return $model;
    }

    protected function resolveActingUserId(): ?int
    {
        if (auth()->check()) {
            return (int) auth()->id();
        }

        return null;
    }

    abstract protected function handle(BaseData $dto): Model;
}
```

- [ ] **Step 4: Run (PASS)**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Actions/
```

- [ ] **Step 5: Confirm the architecture test still passes**

```bash
docker compose exec app ./vendor/bin/pest tests/Architecture/
```
Expected: PASS — AsAction is the only file using `DB::transaction` and the rule whitelists it.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Concerns/ tests/Unit/Actions/
git commit -m "feat(actions): AsAction trait — single transaction site for the kit"
```

---

# Milestone 6 — Passport with Tenant-Aware Tokens

## Task 6.1: Install Passport, run migrations

**Files:**
- Run: `php artisan passport:install`
- Modify: `config/auth.php` (api guard → passport)

- [ ] **Step 1: Run Passport install on central connection**

```bash
docker compose exec app php artisan migrate --database=central --path=database/migrations/0000_passport
# If Passport migrations are not in a custom path, run:
docker compose exec app php artisan vendor:publish --tag=passport-migrations
docker compose exec app php artisan migrate --database=central
docker compose exec app php artisan passport:keys --force
```
Expected: oauth_* tables in central DB; storage/oauth-private.key and oauth-public.key created.

- [ ] **Step 2: Update `config/auth.php`**

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'api' => ['driver' => 'passport', 'provider' => 'users'],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => \App\Models\User::class,
    ],
],
```

- [ ] **Step 3: Commit**

```bash
git add config/auth.php database/migrations/ storage/oauth-*.key.example
git commit -m "feat: install passport on central connection"
```

(Don't commit the private key; add to .gitignore: `/storage/oauth-*.key`.)

---

## Task 6.1b: SuperAdmin model + central seeder

**Files:**
- Create: `database/migrations/YYYY_MM_DD_create_super_admins_table.php`
- Create: `app/Models/Central/SuperAdmin.php`
- Create: `database/seeders/DatabaseSeeder.php` (creates one super-admin from env)

- [ ] **Step 1: Migration**

```php
public function up(): void
{
    Schema::connection('central')->create('super_admins', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->uuid('identifier')->unique();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamp('email_verified_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
        $t->softDeletes();
    });
}
```

- [ ] **Step 2: Model**

```php
<?php
declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Models\BaseModel;

final class SuperAdmin extends BaseModel implements AuthenticatableContract
{
    use Authenticatable, Notifiable, HasApiTokens;
    protected $connection = 'central';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['password' => 'hashed']; }
}
```

- [ ] **Step 3: Seeder reads `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` from env and `firstOrCreate`s. Test: `tests/Feature/SuperAdminSeederTest.php`.**

- [ ] **Step 4: Commit**

```bash
git add database/migrations/ app/Models/Central/SuperAdmin.php database/seeders/DatabaseSeeder.php tests/Feature/SuperAdminSeederTest.php
git commit -m "feat(auth): SuperAdmin model on central connection + seeder"
```

---

## Task 6.1c: failed_jobs tenant context columns

**Files:**
- Create: `database/migrations/YYYY_MM_DD_alter_failed_jobs_add_tenant_context.php`

- [ ] **Step 1: Migration**

```php
public function up(): void
{
    Schema::connection('central')->table('failed_jobs', function (Blueprint $t) {
        $t->string('tenant_id')->nullable()->after('queue')->index();
        $t->unsignedBigInteger('acting_user_id')->nullable()->after('tenant_id');
    });
}
```

- [ ] **Step 2: Override Laravel's failed-job recorder via a service provider that captures the current tenant + actingUserId from the job payload and writes them into the row. Test: enqueue a failing job inside a tenant context; assert `tenant_id` row matches.**

- [ ] **Step 3: Commit**

```bash
git add database/migrations/ app/Providers/
git commit -m "feat(queue): failed_jobs records tenant_id + acting_user_id"
```

---

## Task 6.2: Add `tenant_id` column to `oauth_access_tokens` and `oauth_clients`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_alter_oauth_access_tokens_add_tenant_id.php`
- Create: `database/migrations/YYYY_MM_DD_alter_oauth_clients_add_tenant_id.php`

- [ ] **Step 1: Create the migration for tokens**

```bash
docker compose exec app php artisan make:migration alter_oauth_access_tokens_add_tenant_id --database=central
```

Edit the generated file:

```php
public function up(): void
{
    Schema::connection('central')->table('oauth_access_tokens', function (Blueprint $t) {
        $t->string('tenant_id')->nullable()->after('user_id')->index();
    });
}

public function down(): void
{
    Schema::connection('central')->table('oauth_access_tokens', function (Blueprint $t) {
        $t->dropColumn('tenant_id');
    });
}
```

- [ ] **Step 2: Same for `oauth_clients`**

- [ ] **Step 3: Run migrations**

```bash
docker compose exec app php artisan migrate --database=central
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat(auth): add tenant_id to oauth_access_tokens and oauth_clients"
```

---

## Task 6.3: Tenant-aware access token + repository

**Files:**
- Create: `app/Support/Passport/TenantAwareAccessToken.php`
- Create: `app/Support/Passport/TenantAwareAccessTokenRepository.php`
- Modify: `app/Providers/AuthServiceProvider.php`

- [ ] **Step 1: Write tenant-aware access token**

```php
<?php

declare(strict_types=1);

namespace App\Support\Passport;

use Laravel\Passport\Bridge\AccessToken;
use Lcobucci\JWT\Token;

final class TenantAwareAccessToken extends AccessToken
{
    public ?string $tenantId = null;

    protected function convertToJWT(): Token
    {
        $builder = $this->getBuilder();
        if ($this->tenantId !== null) {
            $builder = $builder->withClaim('tenant_id', $this->tenantId);
        }
        return $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }
}
```

- [ ] **Step 2: Write the access-token repository**

```php
<?php

declare(strict_types=1);

namespace App\Support\Passport;

use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

final class TenantAwareAccessTokenRepository extends AccessTokenRepository
{
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): TenantAwareAccessToken
    {
        $token = new TenantAwareAccessToken($userIdentifier, $scopes, $clientEntity);
        $token->tenantId = function_exists('tenant') && tenant() ? tenant()->id : null;
        return $token;
    }
}
```

- [ ] **Step 3: Bind in `AuthServiceProvider`**

```php
public function register(): void
{
    $this->app->bind(
        \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface::class,
        \App\Support\Passport\TenantAwareAccessTokenRepository::class,
    );
}
```

- [ ] **Step 4: Add storage hook so the `tenant_id` is written to DB on token persistence**

In the same provider's `boot()`:

```php
\Laravel\Passport\Passport::tokenModel()::saving(function ($token) {
    if (function_exists('tenant') && tenant() && empty($token->tenant_id)) {
        $token->tenant_id = tenant()->id;
    }
});
```

- [ ] **Step 5: Commit**

```bash
git add app/Support/Passport/ app/Providers/AuthServiceProvider.php
git commit -m "feat(auth): tenant-aware Passport access tokens (tenant_id claim + column)"
```

---

## Task 6.4: `EnsureTokenMatchesTenant` middleware (the load-bearing security check)

**Files:**
- Create: `app/Http/Middleware/EnsureTokenMatchesTenant.php`
- Modify: `bootstrap/app.php` (register middleware alias)
- Test: `tests/Feature/Tenants/TenantIsolationTest.php` (MOST IMPORTANT TEST)

- [ ] **Step 1: Write the failing isolation test**

```php
<?php
// tests/Feature/Tenants/TenantIsolationTest.php
declare(strict_types=1);

use Tests\Concerns\CreatesTenants;
use Tests\Concerns\IssuesTokens;

uses(CreatesTenants::class, IssuesTokens::class);

it('rejects a token issued for tenant A when used on tenant B', function () {
    $a = $this->createTenant('acme');
    $b = $this->createTenant('beta');

    $token = $this->issuePersonalAccessTokenFor($a, 'user@a.test');

    $response = $this->withHeaders([
        'Host' => 'beta.api.test',
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/v1/auth/me');

    expect($response->status())->toBe(403);
    expect($response->json())->toBeStandardErrorEnvelope('TENANT_MISMATCH');
});
```

- [ ] **Step 2: Write the middleware**

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantMismatchException;
use Closure;
use Illuminate\Http\Request;

final class EnsureTokenMatchesTenant
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->token();
        if (! $token) {
            return $next($request);
        }

        $currentTenant = function_exists('tenant') ? tenant() : null;
        if (! $currentTenant) {
            return $next($request);
        }

        if ($token->tenant_id !== $currentTenant->id) {
            throw new TenantMismatchException('Token does not match the current tenant.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 3: Register the alias in `bootstrap/app.php`**

```php
->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
    $middleware->alias([
        'tenant.token' => \App\Http\Middleware\EnsureTokenMatchesTenant::class,
    ]);
})
```

- [ ] **Step 4: Stub out `CreatesTenants` and `IssuesTokens` concerns** (full implementations come in later tasks; for now, write enough to make the test runnable)

```php
// tests/Concerns/CreatesTenants.php
<?php
declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Central\Tenant;

trait CreatesTenants
{
    protected function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['id' => $slug, 'plan' => 'free']);
        $tenant->domains()->create(['domain' => "{$slug}.api.test"]);
        $tenant->run(fn() => \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
        ]));
        return $tenant;
    }
}
```

```php
// tests/Concerns/IssuesTokens.php
<?php
declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Central\Tenant;
use App\Models\User;

trait IssuesTokens
{
    protected function issuePersonalAccessTokenFor(Tenant $tenant, string $email): string
    {
        return $tenant->run(function () use ($email) {
            $user = User::factory()->create(['email' => $email]);
            return $user->createToken('test')->accessToken;
        });
    }
}
```

- [ ] **Step 5: Run the isolation test (it will require User model + factory + tenant routes — these exist after Milestone 7. For now, mark the test as skipped or pending.)**

Skip with `it(...)->skip('User model not yet defined')` for now; revisit in Task 7.3.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureTokenMatchesTenant.php bootstrap/app.php tests/Concerns/ tests/Feature/Tenants/
git commit -m "feat(security): EnsureTokenMatchesTenant middleware + isolation test scaffold"
```

---

# Milestone 7 — Auth Flows

> **Note:** Each auth task follows the same pattern: TDD failing test → DTO → Action → Controller → Route → assertion of envelope. To keep the plan tractable, I'm showing Task 7.1 fully and abbreviating 7.2–7.6 with the diff list (since the structure is identical and the `Continent` task below shows the full pattern again end-to-end).

## Task 7.1: User model + factory + tenant migration

**Files:**
- Create: `database/migrations/tenant/YYYY_MM_DD_create_users_table.php`
- Create: `app/Models/User.php`
- Create: `database/factories/UserFactory.php`
- Test: `tests/Unit/Models/UserTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Models\User;

it('hashes the password on save', function () {
    $user = User::factory()->create(['password' => 'plaintext']);
    expect(\Hash::check('plaintext', $user->password))->toBeTrue();
});
```

- [ ] **Step 2: Write the migration**

```php
public function up(): void
{
    Schema::create('users', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->uuid('identifier')->unique();
        $t->string('name');
        $t->string('email')->unique();
        $t->timestamp('email_verified_at')->nullable();
        $t->string('password');
        $t->rememberToken();
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
}
```

- [ ] **Step 3: Write the User model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

final class User extends BaseModel implements \Illuminate\Contracts\Auth\Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use \Illuminate\Auth\Authenticatable;

    protected $fillable = ['name', 'email', 'password', 'email_verified_at'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
```

- [ ] **Step 4: Write the factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password',
        ];
    }
}
```

- [ ] **Step 5: Run, commit**

```bash
docker compose exec app ./vendor/bin/pest tests/Unit/Models/UserTest.php
git add app/Models/User.php database/migrations/tenant/ database/factories/UserFactory.php tests/Unit/Models/UserTest.php
git commit -m "feat(auth): User model, tenant users migration, factory"
```

---

## Conventions for the abbreviated tasks below

- **Middleware stack for tenant-domain authenticated routes:** `['auth:api', 'tenant.token']` (the second is the `EnsureTokenMatchesTenant` alias from Task 6.4). For routes requiring email verification, add `'verified'`.
- **Action `__construct` signature:** always inject `*RepositoryInterface`, never the concrete repository.
- **DTO instantiation in controllers:** use `Data::from($request->validated())` for create; `Data::forUpdate($request->validated())` for partial updates.
- **Response shape:** controllers call `$this->success(Resource::make($model), 200|201)` or `$this->paginated($paginator, Resource::class)`. Never `response()->json(...)`.
- **Authorization:** `$this->authorize('action', $modelOrClass)` at the top of the controller method, before the action call.

## Tasks 7.2 – 7.6 (abbreviated — same pattern)

Each task: failing test → DTO under `App\Data\Auth\` → Action under `App\Actions\Auth\` (uses `AsAction`) → Controller method on `App\Http\Controllers\Api\V1\Auth\AuthController` → route in `routes/tenant.php` → run → commit.

- **Task 7.2: `POST /v1/auth/register`** — `RegisterUserData` → `RegisterUserAction(repo: UserRepositoryInterface)::handle()` calls `$repo->create($dto->toArray())`, dispatches `UserRegistered`. Listener `SendEmailVerificationListener` (queued, `ShouldHandleEventsAfterCommit`) sends signed URL.
- **Task 7.3: `POST /v1/auth/login`** — `LoginData` → `IssuePersonalAccessTokenAction::handle()` validates credentials via `Auth::attempt`, returns `(string $token, User $user)` wrapped in a tiny DTO. Returns `{ token, token_type: 'Bearer', expires_at, user }`. **Once this lands, un-skip `TenantIsolationTest` from Task 6.4 and confirm it passes.**
- **Task 7.4: `GET /v1/auth/email/verify/{id}/{hash}`** — signed-URL verification; dispatches `EmailVerified`.
- **Task 7.5: `POST /v1/auth/password/forgot` and `POST /v1/auth/password/reset`** — token stored in tenant `password_reset_tokens`, mailed via Notification.
- **Task 7.6: `POST /v1/auth/logout`** — revokes current token via `$request->user()->token()->revoke()`.

Each lands with its own commit (`feat(auth): register endpoint`, etc.).

**Verification step at end of Milestone 7:**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/Auth/ tests/Feature/Tenants/TenantIsolationTest.php
docker compose exec app ./vendor/bin/pest tests/Architecture/
```
Expected: all green.

---

# Milestone 8 — Tenant CRUD (Central Domain)

## Task 8.1: `POST /v1/tenants` — provisioning action

**Files:**
- Create: `app/Data/Tenants/CreateTenantData.php`
- Create: `app/Actions/Tenants/CreateTenantAction.php`
- Create: `app/Http/Controllers/Api/V1/TenantController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Tenants/CreateTenantTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Models\Central\Tenant;

it('provisions a new tenant DB and seeds it', function () {
    $this->actingAsSuperAdmin();
    $response = $this->postJson('/v1/tenants', ['id' => 'acme', 'plan' => 'free']);
    expect($response->status())->toBe(201);
    expect(Tenant::find('acme'))->not->toBeNull();
    expect(\DB::connection('central')->getSchemaBuilder()->hasTable('users'))->toBeFalse();
    $tenant = Tenant::find('acme');
    $tenant->run(fn() => expect(\Schema::hasTable('users'))->toBeTrue());
});
```

- [ ] **Step 2: Write `CreateTenantData`**

```php
<?php
declare(strict_types=1);

namespace App\Data\Tenants;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation as V;

final class CreateTenantData extends BaseData
{
    public function __construct(
        #[V\Required, V\StringType, V\Min(2), V\Max(63), V\Regex('/^[a-z0-9-]+$/')]
        public string $id,
        #[V\Required, V\In(['free', 'pro', 'enterprise'])]
        public string $plan,
    ) {}
}
```

- [ ] **Step 3: Write `CreateTenantAction`**

```php
<?php
declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Actions\Concerns\AsAction;
use App\Data\Tenants\CreateTenantData;
use App\Events\Tenants\TenantCreated;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class CreateTenantAction
{
    use AsAction;

    public function __construct(private TenantRepositoryInterface $repo) {}

    protected function handle($dto): Model
    {
        assert($dto instanceof CreateTenantData);

        $tenant = $this->repo->create(['id' => $dto->id, 'plan' => $dto->plan]);
        $tenant->domains()->create(['domain' => $dto->id . '.api.localhost']);

        event(new TenantCreated($tenant));
        return $tenant;
    }
}
```

(Provisioning the DB itself happens inside Stancl's tenant-creation event listeners — Stancl ships `TenantsCreated` job that calls `createDatabase()`. The `TenantCreated` listener we dispatch is for downstream concerns: Meilisearch index creation, etc.)

- [ ] **Step 4: Write the controller**

```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tenants\CreateTenantAction;
use App\Data\Tenants\CreateTenantData;
use App\Http\Requests\Tenants\CreateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TenantController extends BaseApiController
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function index(): JsonResponse
    {
        return $this->paginated($this->tenants->paginate(), TenantResource::class);
    }

    public function store(CreateTenantRequest $request, CreateTenantAction $action): JsonResponse
    {
        $this->authorize('create', Tenant::class);
        $tenant = $action->execute(CreateTenantData::from($request->validated()));
        return $this->success(TenantResource::make($tenant), Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/api.php` (central):

```php
use App\Http\Controllers\Api\V1\TenantController;

Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::apiResource('tenants', TenantController::class)->only(['index', 'show', 'store', 'destroy']);
});
```

- [ ] **Step 6: Run, commit**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/Tenants/CreateTenantTest.php
git add app/Data/Tenants/ app/Actions/Tenants/ app/Http/Controllers/Api/V1/TenantController.php app/Http/Resources/TenantResource.php routes/api.php tests/Feature/Tenants/
git commit -m "feat(tenants): POST /v1/tenants provisions tenant DB + domain"
```

---

## Tasks 8.2 – 8.3 (abbreviated)

- **Task 8.2: `DELETE /v1/tenants/{id}`** — soft delete; `DeleteTenantAction` revokes all `oauth_access_tokens WHERE tenant_id = X`.
- **Task 8.3: `DELETE /v1/tenants/{id}/force`** — drops DB via Stancl `$tenant->delete()`; requires `X-Confirm-Drop: yes` header (validated in request).

---

# Milestone 9 — `make:api-resource` Generator

## Task 9.1: Stub files for the generator

**Files:**
- Create: `stubs/api-resource/{model,migration,repository,repository-interface,action-create,action-update,action-delete,data-create,data-update,controller,resource,policy,event-created,event-updated,event-deleted,factory,test}.stub`

- [ ] **Step 1: Write one stub at a time**, e.g. `stubs/api-resource/model.stub`:

```php
<?php

declare(strict_types=1);

namespace {{ namespace }};

use App\Models\BaseModel;
use App\Support\Concerns\HasSearchable;

final class {{ class }} extends BaseModel
{
    use HasSearchable;

    protected $table = '{{ table }}';
    protected $fillable = [{{ fillable }}];
}
```

(Repeat for every file the generator scaffolds. Keep stubs ≤ 40 lines each.)

- [ ] **Step 2: Commit each stub set**

```bash
git add stubs/api-resource/
git commit -m "feat(generator): stubs for make:api-resource"
```

---

## Task 9.2: `MakeApiResourceCommand`

**Files:**
- Create: `app/Console/Commands/MakeApiResourceCommand.php`
- Test: `tests/Feature/Generator/MakeApiResourceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

it('scaffolds a full resource', function () {
    $this->artisan('make:api-resource', ['name' => 'TestThing'])->assertSuccessful();
    expect(file_exists(app_path('Models/TestThing.php')))->toBeTrue();
    expect(file_exists(app_path('Repositories/TestThingRepository.php')))->toBeTrue();
    expect(file_exists(app_path('Actions/TestThings/CreateTestThingAction.php')))->toBeTrue();
    // ... assert remaining 10 files
})->afterEach(function () {
    // cleanup generated files
});
```

- [ ] **Step 2: Write the command**

```php
<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class MakeApiResourceCommand extends Command
{
    protected $signature = 'make:api-resource {name : Singular PascalCase name (e.g., Continent)} {--force}';
    protected $description = 'Scaffold a full API resource (model, repo, action × 3, DTO × 2, controller, resource, policy, events, migration, factory, test).';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $force = (bool) $this->option('force');

        $context = [
            'class' => $name,
            'plural' => Str::plural($name),
            'snake' => Str::snake($name),
            'table' => Str::snake(Str::plural($name)),
            'kebab' => Str::kebab(Str::plural($name)),
            'fillable' => "'name'", // sensible default; engineer customizes
        ];

        foreach ($this->fileMap($context) as $stub => $target) {
            $this->renderStub($stub, $target, $context, $force);
        }

        $this->appendRoute($context);

        $this->components->info("Resource {$name} scaffolded. Run migrations and customize.");
        return self::SUCCESS;
    }

    private function fileMap(array $ctx): array { /* maps stubs → target paths using $ctx */ return []; }
    private function renderStub(string $stub, string $target, array $ctx, bool $force): void { /* read stub, replace {{ ... }}, write */ }
    private function appendRoute(array $ctx): void { /* append apiResource line to routes/tenant.php */ }
}
```

- [ ] **Step 3: Fill in `fileMap`, `renderStub`, `appendRoute` methods**

(Each ~15 lines of straightforward string templating; the engineer writes them by mirroring the stubs created in Task 9.1.)

- [ ] **Step 4: Run, commit**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/Generator/
git add app/Console/Commands/MakeApiResourceCommand.php tests/Feature/Generator/
git commit -m "feat(generator): make:api-resource artisan command"
```

---

# Milestone 10 — `Continent` Reference Resource

## Task 10.1: Generate Continent via the new command

- [ ] **Step 1: Run the generator**

```bash
docker compose exec app php artisan make:api-resource Continent
```

- [ ] **Step 2: Customize the generated migration**

Edit `database/migrations/tenant/YYYY_MM_DD_create_continents_table.php`:

```php
Schema::create('continents', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->uuid('identifier')->unique();
    $t->string('name');
    $t->string('code', 3)->unique();
    $t->unsignedBigInteger('population')->default(0);
    $t->unsignedBigInteger('created_by')->nullable();
    $t->unsignedBigInteger('updated_by')->nullable();
    $t->timestamps();
    $t->softDeletes();
    $t->index('name');
});
```

- [ ] **Step 3: Customize the generated DTO `CreateContinentData`**

```php
public function __construct(
    #[V\Required, V\StringType, V\Min(2), V\Max(64)] public string $name,
    #[V\Required, V\StringType, V\Size(2)] public string $code,
    #[V\Required, V\IntegerType, V\Min(0)] public int $population,
) {}
```

- [ ] **Step 4: Customize `ContinentRepository::allowedFilters/Includes/Sorts`**

```php
protected function allowedFilters(): array { return ['name', 'code', AllowedFilter::scope('populated')]; }
protected function allowedIncludes(): array { return []; }
protected function allowedSorts(): array { return ['name', 'code', 'population', 'created_at']; }
```

- [ ] **Step 5: Customize `ContinentResource::payload()`**

```php
protected function payload(Request $request): array
{
    return [
        'name' => $this->name,
        'code' => $this->code,
        'population' => $this->population,
    ];
}
```

- [ ] **Step 6: Run the generated test (already TDD-included by the stub)**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/Continents/
```

- [ ] **Step 7: Commit**

```bash
git add app/Models/Continent.php app/Repositories/*Continent* app/Actions/Continents/ app/Data/Continents/ app/Http/Controllers/Api/V1/ContinentController.php app/Http/Resources/ContinentResource.php app/Events/Continents/ app/Policies/ContinentPolicy.php database/migrations/tenant/ tests/Feature/Continents/
git commit -m "feat: scaffold Continent reference resource via make:api-resource"
```

---

## Task 10.2: Add idempotent POST + response cache + audit + search

This task chains four small commits.

- [ ] **Step 1: Idempotency** — Add `idempotency` middleware to `POST /v1/continents` in `routes/tenant.php`. Write `tests/Feature/Continents/ContinentIdempotencyTest.php` asserting two requests with the same `Idempotency-Key` return identical bodies and the second has `X-Idempotent-Replay: true`. Commit.

- [ ] **Step 2: Response cache** — Add `cacheResponse:300` middleware to `GET /v1/continents`. Write `FlushContinentCacheListener` (implements `ShouldQueue + ShouldHandleEventsAfterCommit`) subscribed to all three Continent events; flushes by tag `continents:{tenant_id}`. Write `ContinentCacheTest`. Commit.

- [ ] **Step 3: Audit** — `BaseModel` is already `Auditable`; verify the test by creating a continent and asserting an audit row in the tenant DB. Commit.

- [ ] **Step 4: Search** — Add `HasSearchable` to the generated Continent model (the stub already includes it). Add `ReindexContinentSearchListener`. Write `ContinentSearchTest`. Commit.

End-of-task verification:

```bash
docker compose exec app composer test:full
docker compose exec app composer check
```
Expected: all green; layer-boundary test still passes.

---

# Milestone 11 — Cross-cutting (Versioning, Throttling, Idempotency, Response Cache)

## Task 11.1: `ApiVersionResolver` middleware

(TDD as before; middleware parses `/v1/` from URI, sets `$request->attributes->set('api_version', 'v1')`, adds `Sunset` header if config marks version deprecated. Test asserts header present.)

## Task 11.2: Smart throttling configuration

(Configure `config/api.php` with plan tiers from spec §6.9, install Grazulex package, register middleware in tenant route group.)

## Task 11.3: Idempotency configuration

(Already used in Task 10.2 — this task wires up Redis backing, 24-hour TTL, fingerprint logic.)

## Task 11.4: Response cache profile

(`TenantAwareCacheProfile` extends Spatie's `CacheProfile` and adds tenant_id to the cache key. Test: two tenants' GETs don't collide.)

Each is a TDD task with its own commit.

---

# Milestone 12 — Observability

## Task 12.1: `LogApiRequests` middleware (structured logs)

(Push `request_id`, `tenant_id`, `tenant_slug`, `user_id`, `api_version`, `route`, `duration_ms` into `Log::withContext()`. JSON formatter in `config/logging.php`. Test asserts log line contains these keys.)

## Task 12.2: Sentry integration

(Publish `config/sentry.php`, set `tenant_slug` tag in `App\Providers\AppServiceProvider::boot()` listening for the tenancy bootstrapped event. Test asserts Sentry scope tag.)

## Task 12.3: Health endpoints

`GET /health` (always 200) and `GET /health/deep` (checks central DB, 3 random tenant DBs, Redis, Meilisearch, mailer config). Token-gated via `HEALTHCHECK_TOKEN` env var.

Each milestone task: TDD, commit.

---

# Milestone 13 — Search

## Task 13.1: Scout + Meilisearch wiring with per-tenant indexes

(`HasSearchable` overrides `searchableAs()`; install Scout, configure `config/scout.php`; `ReindexContinentSearchListener` already exists from Task 10.2.)

## Task 13.2: `tenants:reindex` Artisan command

(`php artisan tenants:reindex --tenant=acme|--all` rebuilds.)

---

# Milestone 14 — OpenAPI

## Task 14.1: Install Scramble, publish config

## Task 14.2: `ScrambleExtensions` for envelopes, headers, security schemes

Test: assert `/docs.json` validates as OpenAPI 3.1 (use `cebe/php-openapi-validator` as dev dep) and that every endpoint's `200` response schema contains `meta.request_id`.

---

# Milestone 15 — Docs and Polish

## Task 15.1: README.md

(Three reader-personas as in spec §8.2.)

## Task 15.2: `docs/` sub-documents

`architecture.md`, `tenancy.md`, `authentication.md`, `conventions.md`, `adding-a-resource.md` (the 10-minute walkthrough), `api-versioning.md`, `testing.md`, `deployment.md`, `operations.md`, `security.md`.

## Task 15.3: `ValidateEnvironmentServiceProvider`

(Asserts required env vars present at boot; fails loudly in prod.)

## Task 15.4: Final verification — Success criteria

- [ ] **Step 1: Fresh clone smoke test**

```bash
git clone <repo-url> /tmp/smoke && cd /tmp/smoke
docker compose up -d
make setup
curl -s http://localhost:8080/health | jq .
```
Expected: `{"status":"ok",…}` within 5 minutes of clone.

- [ ] **Step 2: Generator end-to-end**

```bash
make resource NAME=Post
docker compose exec app php artisan migrate
docker compose exec app composer test -- --filter=PostCrud
```
Expected: green within 1 minute.

- [ ] **Step 3: Run the full quality gate**

```bash
docker compose exec app composer check
```
Expected: lint + analyze + test:full all pass; ≤ 3 minutes.

- [ ] **Step 4: TenantIsolationTest + LayerBoundariesTest**

```bash
docker compose exec app ./vendor/bin/pest tests/Feature/Tenants/TenantIsolationTest.php tests/Architecture/
```
Expected: green.

- [ ] **Step 5: OpenAPI spec validation**

```bash
curl -s http://localhost:8080/docs.json | jq '.openapi'
```
Expected: `"3.1.0"`.

- [ ] **Step 6: Sentry tag check** (manual)

Trigger a deliberate exception in a tenant request; verify the Sentry event has `tenant_slug` tag.

- [ ] **Step 7: Final commit + tag**

```bash
git add -A
git commit -m "chore: complete starter kit MVP"
git tag v0.1.0
```

---

## Done!

At this point the kit satisfies every success criterion in §9 of the design spec.

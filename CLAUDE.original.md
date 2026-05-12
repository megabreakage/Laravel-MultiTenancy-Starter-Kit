# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Production-ready, API-only **multi-tenant Laravel 13** starter kit. PHP 8.5, MySQL 8, Redis, Meilisearch, Horizon. All development runs inside Docker.

## Commands

```bash
make setup          # First-time: copy .env, docker up, composer install, key:generate, migrate, passport:keys
make up / down      # Start / stop all containers
make shell          # sh into app container
make logs           # Follow app logs

make test           # Parallel (4 workers) — fast, default
make test-seq       # Sequential — for debugging test interaction
make test-full      # Parallel non-serial + serial group (matches CI)
make lint           # Pint check (dry-run)
make fix            # Pint auto-fix
make analyze        # PHPStan level max
make check          # lint + analyze + test:full (full CI gate locally)

# Scaffold a full resource stack (model, migration, repo, actions, DTO, controller, resource, policy, events, factory, test)
make resource NAME=Continent

# Create a new tenant
make tenant TENANT=acme
```

Run a single test file or filter:
```bash
docker compose exec app ./vendor/bin/pest tests/Feature/Continents/ListContinentsTest.php
docker compose exec app ./vendor/bin/pest --filter="creates a continent"
```

Rector dry-run (CI also runs this):
```bash
docker compose exec app ./vendor/bin/rector --dry-run
```

## Architecture

### Multi-tenancy (Stancl)

Two database connections — `central` (tenants table, oauth, super-admins, global audits) and `tenant_*` (one DB per tenant, provisioned automatically). Models in `App\Models\Central\*` declare `$connection = 'central'`. All other models run on the current tenant connection. Never call `DB::setDefaultConnection()` or `DB::connection('central')` ad-hoc — tenancy bootstrappers handle switching.

### Request flow

```
Middleware (auth:api, tenant, version, throttle, idempotency)
  → Controller (Gate::authorize, repo read, DTO hydration)
    → Action::execute() [AsAction trait: log → DB::transaction → log]
      → handle(DTO) [repo write + event dispatch]
        → afterCommit listeners (audit, Scout reindex, cache flush, mail, notifications)
```

### Layer rules (enforced by CI)

| What | Where |
|---|---|
| DB writes | `App\Actions\*` only via `AsAction` trait |
| Multi-action orchestration | `App\Services\*` only |
| All Eloquent queries | `App\Repositories\*` only — never in controllers/actions/services |
| Response envelopes | `RespondsWithJson` trait on `BaseApiController` — never `response()->json()` |
| Domain errors | `App\Exceptions\ApiException` subclasses — never `abort()` |
| Transactions | `AsAction` (single write) or a `Services\*` method (multi-action atomicity) — never manual `beginTransaction/commit/rollBack` |

Architecture tests in `tests/Architecture/` enforce these at CI time. Run them with:
```bash
docker compose exec app ./vendor/bin/pest --filter=LayerBoundariesTest
docker compose exec app ./vendor/bin/pest --filter=InvariantsTest
```

### Key base classes — always extend/use, never re-implement

- `BaseModel` — sets `identifier` (UUID), `created_by`, `updated_by` via `booted()`; uses `SoftDeletes`
- `BaseRepository` — wraps `spatie/laravel-query-builder`; provides `browseAll`, `paginate`, `findByIdentifier`, `create`, `update`, `delete`, `forceDelete`
- `BaseData` (Spatie Laravel Data) — typed DTOs; validation lives here, not in FormRequests
- `BaseApiController` + `RespondsWithJson` — standardized JSON envelopes (`success`, `error`, `paginated`)
- `BaseApiResource` — exposes `identifier` as `id`, ISO timestamps, `whenIncluded()`
- `AsAction` trait — the **only** place `DB::transaction` opens; also handles `actingUserId` resolution for queue safety

### Transaction rule (critical)

`DB::transaction` is called **once per write** — inside `AsAction::execute()`. Concrete `handle()` methods must not open another transaction. Side-effects (mail, cache flush, search reindex) belong in listeners implementing `ShouldQueue + ShouldHandleEventsAfterCommit`. Full detail: `.claude/hooks/database-transaction-handling.md`.

### DRY enforcement

Before writing new code, check whether `BaseRepository`, `AsAction`, `BaseData`, `BaseApiController`, `BaseApiResource`, or an existing event/listener already solves it. The generator (`make:api-resource`) scaffolds the entire stack — use it first. Full checklist: `.claude/hooks/pre-execution-dry-principles.md`.

## Static analysis & style

- PHPStan at `level: max` (`phpstan.neon`). Baseline in `phpstan-baseline.neon` — do not add new ignores without justification.
- Pint with `declare_strict_types`, `ordered_imports`, `no_unused_imports`, `single_quote` (`pint.json`).
- Rector auto-upgrades to PHP 8.4 patterns + Laravel 13 idioms (`rector.php`).
- All files must have `declare(strict_types=1)`.

## Testing

- Framework: **Pest 4** with `pestphp/pest-plugin-arch` and `pestphp/pest-plugin-laravel`.
- Test DB: `api_kit_test_central` (MySQL). Parallel runs create `api_kit_test_central_w1..w4`.
- Custom expectations in `tests/Pest.php`: `toBeStandardSuccessEnvelope()`, `toBeStandardErrorEnvelope()`.
- Serial tests that can't run in parallel must use `->group('serial')`.
- `docker-compose.test.yml` provides isolated MySQL/Redis/Meilisearch on tmpfs for local test runs.

## CI pipeline

`.github/workflows/ci.yml` runs four jobs: `lint` (Pint + Rector dry-run), `analyze` (PHPStan), `test` (`composer test:full`), `security` (`composer audit`). All must pass before merge.

## Feature flags (.env)

`FEATURE_TENANCY`, `FEATURE_IDEMPOTENCY_ENFORCED`, `FEATURE_RESPONSE_CACHE`, `FEATURE_SEARCH`, `FEATURE_AUDIT`, `FEATURE_SENTRY` — control major subsystems. Check these before assuming a subsystem is active.

## API documentation

Auto-generated by `dedoc/scramble`. Extend `BaseApiResource` and use typed responses — Scramble derives docs from the type signatures.

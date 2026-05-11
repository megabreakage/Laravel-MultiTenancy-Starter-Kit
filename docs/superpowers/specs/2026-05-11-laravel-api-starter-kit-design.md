# Laravel API-Only Starter Kit — Design Specification

**Date:** 2026-05-11
**Status:** Approved for implementation planning
**Owner:** dev.iosync@gmail.com

---

## 1. Overview

A production-ready, API-only **Laravel 13** starter kit for multi-tenant SaaS backends. The kit is headless (no Blade, no Vite, no frontend assets) and serves mobile apps, SPAs, and microservices.

The kit ships **opinionated base classes** that encode the engineering conventions defined in the global `CLAUDE.md` (Repository + Service + Action layers, `BaseModel` with audit fields, transaction patterns, standardized JSON envelopes). A single Artisan generator (`make:api-resource`) scaffolds new resources on top of those base classes. One reference resource (`Continent`) exercises every pattern end-to-end.

### 1.1 Locked-in decisions

| Concern | Decision |
| --- | --- |
| Tenancy model | **Multi-database** (one MySQL DB per tenant) via Stancl Tenancy v3.9 |
| Tenant routing | **Subdomain** (`tenant.api.example.com`) |
| Central DB | Holds `tenants`, `domains`, `super_admins`, Passport `oauth_*`, `global_audits` |
| Tenant DB | Holds `users`, `roles`, `permissions`, `audits`, business data |
| Auth grants | **Personal Access Tokens** (Sanctum-style UX) + **Client Credentials** |
| Tokens | Stored centrally; tagged with `tenant_id` claim; `EnsureTokenMatchesTenant` middleware rejects cross-tenant use |
| Architecture | Controller → Action/Service → Repository, with `spatie/laravel-data` DTOs between layers |
| Approach | **Approach A**: convention-heavy base classes + `make:api-resource` generator |
| Infra defaults | Redis, Horizon, Mailpit/SMTP, Meilisearch+Scout |
| Audit log | **Per-tenant** (owen-it/laravel-auditing) in each tenant DB; cross-tenant admin actions go to central `global_audits` |
| Error tracking | **Central Sentry** with `tenant_slug` tag |
| Reference resource | `Continent` |
| Test runtime | **MySQL** matching production, executed via **Paratest** (parallel + sequential) |
| Coverage gate | 80% line coverage on `app/` |

### 1.2 DRY enforcement

The kit ships two enforcement hooks in `.claude/hooks/`:

- **`pre-execution-dry-principles.md`** — pre-code-generation checklist enumerating the kit's architectural invariants (`BaseModel`, `BaseRepository`, `AsAction`, `BaseData`, `BaseApiController`, `BaseApiResource`, event/listener pipeline, kit middleware) as single sources of truth, with `rg` commands that prove no duplication has been introduced.
- **`database-transaction-handling.md`** — defines `App\Actions\Concerns\AsAction` as the **single transaction site** in the kit. Controllers, repositories, listeners, and jobs **never** call `DB::transaction(...)`. Side-effects (mail, search, cache flush, audit post-processing) are triggered by domain events whose listeners implement `ShouldHandleEventsAfterCommit` — they never fire on a rolled-back transaction. The only exception is `App\Services\*`, which may open a transaction to make multiple actions commit atomically together (Laravel nests these as savepoints).

Both hooks are enforced in CI by `LayerBoundariesTest` plus zero-match invariants: no direct `Model::` access outside `app/Repositories/`, no `DB::transaction(` outside `AsAction` + `app/Services/`, no `DB::beginTransaction|commit|rollBack` anywhere, no `response()->json(` in controllers, no `abort(` in business code.

### 1.3 Out of scope

To prevent scope creep, the kit explicitly does NOT include: billing/Stripe, frontend or admin panels, SSO/SAML/social login, analytics warehousing, file upload endpoints, notification channels beyond email.

---

## 2. System Architecture (Bird's-Eye)

```
                ┌────────────────────────────────────────────────────────┐
                │              tenant1.api.example.com                   │
                │              tenant2.api.example.com                   │
                └────────────────────────┬───────────────────────────────┘
                                         │
                                ┌────────▼─────────┐
                                │  Nginx / Caddy   │
                                └────────┬─────────┘
                                         │
                  ┌──────────────────────▼──────────────────────┐
                  │            Laravel API (PHP 8.5)            │
                  │                                             │
                  │  Middleware pipeline:                       │
                  │   1. ForceJsonResponse                      │
                  │   2. InitializeTenancyByDomain (Stancl)     │
                  │   3. PreventAccessFromCentralDomains        │
                  │   4. LogApiRequests (request_id, tenant_id) │
                  │   5. ApiVersion (laravel-apiroute)          │
                  │   6. SmartThrottle (plan-aware)             │
                  │   7. ApiIdempotency (on POST/PUT/PATCH)     │
                  │   8. auth:api  (Passport)                   │
                  │   9. EnsureTokenMatchesTenant               │
                  │  10. EnsureEmailVerified (selective)        │
                  │                                             │
                  │  Controller → Action/Service → Repository   │
                  │  DTOs (spatie/laravel-data) flow between    │
                  └─────────┬──────────────────┬────────────────┘
                            │                  │
                  ┌─────────▼────────┐ ┌───────▼────────┐
                  │  Central MySQL   │ │ Tenant MySQL   │  ← one DB per tenant
                  │                  │ │ (resolved at   │
                  │ tenants          │ │  request time) │
                  │ domains          │ │                │
                  │ oauth_*          │ │ users          │
                  │ super_admins     │ │ roles          │
                  │ tenant_plans     │ │ permissions    │
                  │ global_audits    │ │ audits         │
                  └──────────────────┘ │ continents     │
                                       └────────────────┘

                  ┌──────────┐  ┌──────────┐  ┌────────────┐  ┌────────┐
                  │  Redis   │  │ Horizon  │  │ Meilisearch│  │ Mailpit│
                  │ cache    │  │ queue    │  │  search    │  │  /SMTP │
                  │ sessions │  │ workers  │  │  index     │  │        │
                  │ locks    │  │          │  │            │  │        │
                  │ idemp.   │  │          │  │            │  │        │
                  └──────────┘  └──────────┘  └────────────┘  └────────┘

                              ┌──────────────────┐
                              │  Sentry (cloud)  │  ← tagged tenant_slug
                              └──────────────────┘
```

**Request flow example:** `POST https://acme.api.example.com/v1/continents` → Nginx → Stancl resolves `acme` → tenant DB. Bearer token validated against central `oauth_access_tokens`; token's `tenant_id` claim MUST equal the resolved tenant or the request returns `403 TENANT_MISMATCH`. Controller delegates to `CreateContinentAction(CreateContinentData $dto)`, which opens a transaction, calls `ContinentRepository::create()`, dispatches `ContinentCreated` event, returns the model. Controller wraps in `ContinentResource` and returns the standardized JSON envelope.

---

## 3. Directory Structure & Layer Boundaries

### 3.1 Directory layout

```
laravel-api-kit/
├── app/
│   ├── Actions/                      # single-purpose write operations
│   │   ├── Auth/
│   │   │   ├── RegisterUserAction.php
│   │   │   ├── IssuePersonalAccessTokenAction.php
│   │   │   ├── SendEmailVerificationAction.php
│   │   │   ├── VerifyEmailAction.php
│   │   │   ├── SendPasswordResetAction.php
│   │   │   └── ResetPasswordAction.php
│   │   ├── Tenants/
│   │   │   ├── CreateTenantAction.php
│   │   │   └── DeleteTenantAction.php
│   │   └── Continents/
│   │       ├── CreateContinentAction.php
│   │       ├── UpdateContinentAction.php
│   │       └── DeleteContinentAction.php
│   │
│   ├── Console/
│   │   └── Commands/
│   │       └── MakeApiResourceCommand.php   # php artisan make:api-resource
│   │
│   ├── Data/                         # spatie/laravel-data DTOs
│   │   ├── Auth/
│   │   ├── Tenants/
│   │   └── Continents/
│   │
│   ├── Enums/
│   │   ├── TokenScope.php
│   │   ├── TenantStatus.php
│   │   └── UserRole.php
│   │
│   ├── Events/
│   │   ├── Auth/                     # UserRegistered, EmailVerified
│   │   ├── Tenants/                  # TenantCreated, TenantDeleted
│   │   └── Continents/               # ContinentCreated, ContinentUpdated, ContinentDeleted
│   │
│   ├── Exceptions/
│   │   ├── Handler.php
│   │   ├── ApiException.php
│   │   ├── DomainException.php
│   │   ├── ResourceNotFoundException.php
│   │   └── TenantResolutionException.php
│   │
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── BaseApiController.php
│   │   │   ├── Auth/
│   │   │   ├── HealthController.php
│   │   │   ├── TenantController.php       # central-domain only
│   │   │   └── ContinentController.php
│   │   ├── Middleware/
│   │   │   ├── ForceJsonResponse.php
│   │   │   ├── LogApiRequests.php
│   │   │   ├── EnsureEmailVerified.php
│   │   │   ├── EnsureTokenMatchesTenant.php
│   │   │   ├── ApiVersionResolver.php
│   │   │   └── PreventCentralDomainAccess.php
│   │   ├── Requests/                      # thin shells hydrating DTOs
│   │   └── Resources/
│   │       ├── BaseApiResource.php
│   │       ├── UserResource.php
│   │       └── ContinentResource.php
│   │
│   ├── Listeners/
│   │   ├── SendEmailVerificationListener.php
│   │   ├── LogAuditTrailListener.php
│   │   └── ReindexContinentSearchListener.php
│   │
│   ├── Models/
│   │   ├── BaseModel.php
│   │   ├── Central/                       # connection: 'central'
│   │   │   ├── Tenant.php
│   │   │   ├── Domain.php
│   │   │   └── SuperAdmin.php
│   │   ├── User.php                       # tenant DB
│   │   ├── Role.php                       # tenant DB
│   │   └── Continent.php                  # tenant DB
│   │
│   ├── Policies/
│   │   ├── UserPolicy.php
│   │   └── ContinentPolicy.php
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── RepositoryServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── TenancyServiceProvider.php
│   │
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   ├── BaseRepositoryInterface.php
│   │   │   ├── UserRepositoryInterface.php
│   │   │   ├── TenantRepositoryInterface.php
│   │   │   └── ContinentRepositoryInterface.php
│   │   ├── BaseRepository.php
│   │   ├── UserRepository.php
│   │   ├── TenantRepository.php
│   │   └── ContinentRepository.php
│   │
│   ├── Services/
│   │   ├── ApiResponseService.php
│   │   ├── TenantContextService.php
│   │   ├── IdempotencyService.php
│   │   └── ContinentService.php
│   │
│   └── Support/
│       ├── Concerns/
│       │   ├── HasAuditTrail.php
│       │   ├── HasUuidIdentifier.php
│       │   ├── HasSearchable.php
│       │   └── RespondsWithJson.php
│       └── OpenApi/
│           └── ScrambleExtensions.php
│
├── config/
│   ├── api.php
│   ├── tenancy.php
│   ├── permission.php
│   ├── responsecache.php
│   ├── scout.php
│   ├── auditing.php
│   ├── sentry.php
│   └── features.php
│
├── database/
│   ├── migrations/                        # central DB
│   ├── migrations/tenant/                 # runs inside each tenant DB
│   ├── factories/
│   └── seeders/
│
├── docker/
│   ├── app/Dockerfile
│   ├── nginx/default.conf
│   └── horizon/Dockerfile
├── docker-compose.yml
├── docker-compose.test.yml
│
├── routes/
│   ├── api.php                            # central-domain routes
│   ├── tenant.php                         # tenant-domain routes
│   └── channels.php
│
├── tests/
│   ├── Pest.php
│   ├── TestCase.php
│   ├── Concerns/
│   ├── Architecture/
│   ├── Feature/
│   └── Unit/
│
├── .github/workflows/ci.yml
├── phpstan.neon
├── rector.php
├── pint.json
├── Makefile
├── .env.example
└── README.md
```

### 3.2 Layer-boundary rules

| Layer | Reads from | Writes to | May call |
| --- | --- | --- | --- |
| Controller | Request | Resource/Response | Action OR Service, never Repository directly |
| Action | DTO | Domain model | Repository, dispatches Events |
| Service | DTO or primitives | Domain model / mixed | Multiple Repositories, Actions, external APIs |
| Repository | Eloquent query | Model | Eloquent only — no other Repositories, no Services |
| DTO | Request input | Typed properties | Nothing — pure data |
| Resource | Model | Array | Nothing |

These rules are enforced at CI time by Pest's `arch()` assertions in `tests/Architecture/LayerBoundariesTest.php`.

---

## 4. Base Classes & Conventions

### 4.1 `App\Models\BaseModel`

Matches the global `CLAUDE.md` definition: `SoftDeletes`, auto-fills `identifier` (UUID), `created_by`, `updated_by` on the `creating`/`updating` events. Two additions for this kit:

- `$connection` defaults to `null` (resolves to current tenant); `App\Models\Central\*` models override to `'central'`.
- Implements `OwenIt\Auditing\Contracts\Auditable` and uses `HasAuditTrail` so every subclass logs to its connection's `audits` table.

### 4.2 `App\Http\Resources\BaseApiResource`

- Exposes `identifier` as `id` in the public payload (the bigint PK is never serialized).
- Wraps timestamps as ISO-8601.
- Adds `meta.version` from the resolved API version.
- Provides `whenIncluded(string $relation)` helper for `?include=` support.

### 4.3 `App\Repositories\BaseRepository` (abstract)

```php
abstract class BaseRepository implements BaseRepositoryInterface
{
    abstract protected function model(): string;
    abstract protected function allowedFilters(): array;     // QueryBuilder
    abstract protected function allowedIncludes(): array;
    abstract protected function allowedSorts(): array;
    protected function defaultSort(): string { return '-created_at'; }

    public function newQuery(): QueryBuilder;
    public function findByIdentifier(string $id): Model;            // throws if not found
    public function browseAll(): Collection;                         // filters/sorts/includes, no pagination
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Model;
    public function update(Model $model, array $data): Model;
    public function delete(Model $model): void;                      // soft delete
    public function forceDelete(Model $model): void;                 // permanent removal
}
```

Guard rails baked in:

- `browseAll()` logs a warning when the result set exceeds `config('api.browse_all_warn_threshold', 1000)` rows. This catches accidental misuse without breaking the contract.
- `forceDelete()` is policy-gated separately from `delete()`. Generated policies include both `delete` and `forceDelete` abilities. The generator scaffolds a `DELETE /v1/{resource}/{id}/force` route in addition to the soft-delete route. Both write to the per-tenant audit log; `forceDelete` is tagged with `event: 'destroyed'` so it's distinguishable in audit history.
- Both methods are part of `BaseRepositoryInterface` so the contract is testable.

Concrete repositories override only `model()` and the four `allowed*` methods. Custom queries live as named methods on the concrete class (e.g., `ContinentRepository::popular()`).

### 4.4 `App\Actions\Concerns\AsAction` trait

Standardizes the action contract and is the **single, kit-wide transaction site**. Every action exposes `execute(Data $dto): Model`; concrete actions implement `protected handle(Data $dto): Model`. The trait:

1. Resolves `actingUserId` (`auth()->id()` → queue-payload value → system-user fallback).
2. Logs `starting` **outside** the transaction.
3. Wraps `handle(...)` in `DB::transaction(...)` — Laravel auto-rolls back on any thrown exception.
4. Logs `completed` **outside** the transaction.

Concrete `handle()` methods are pure write paths: repository calls + `event(new DomainEvent(...))`. Listeners that produce side-effects (mail, search reindex, cache flush, audit post-processing, webhooks) implement `ShouldHandleEventsAfterCommit` so they never fire on a rolled-back transaction. Authorization, validation, reads, logging, and external I/O happen **outside** `handle()`. See `.claude/hooks/database-transaction-handling.md` for the full rule set.

### 4.5 `App\Data\BaseData` (extends `Spatie\LaravelData\Data`)

- Adds `forCreation()` / `forUpdate()` static constructors that strip nulls for partial updates.
- Custom casts for `identifier` → `Tenant` / `User` model resolution.

### 4.6 `App\Http\Controllers\Api\V1\BaseApiController`

- Uses `RespondsWithJson` trait: `success($data, $status)`, `error($code, $message, $status)`, `paginated($paginator, $resource)`.
- Auto-authorizes via policies when `$policyResource` property is set.
- Default `index()/show()/store()/update()/destroy()` implementations work for vanilla CRUD; generated controllers override only what's special.

### 4.7 Generator: `php artisan make:api-resource Continent`

Scaffolds (and refuses to overwrite without `--force`):

```
app/Actions/Continents/{Create,Update,Delete}ContinentAction.php
app/Data/Continents/{Create,Update}ContinentData.php
app/Events/Continents/Continent{Created,Updated,Deleted}.php
app/Http/Controllers/Api/V1/ContinentController.php
app/Http/Resources/ContinentResource.php
app/Models/Continent.php
app/Policies/ContinentPolicy.php
app/Repositories/Contracts/ContinentRepositoryInterface.php
app/Repositories/ContinentRepository.php
database/migrations/tenant/YYYY_MM_DD_create_continents_table.php
database/factories/ContinentFactory.php
tests/Feature/Continents/ContinentCrudTest.php
```

Each generated file is **thin** — extends a base class, declares the minimum (fillables, allowed filters, validation rules). Routes are appended to `routes/tenant.php` with an `apiResource` line.

### 4.8 Standardized JSON envelopes

```jsonc
// Success
{
  "data": { ... },
  "meta": { "version": "v1", "request_id": "01HF...", "tenant": "acme" }
}

// Paginated
{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "version": "v1", "request_id": "...", "tenant": "acme" }
}

// Error
{
  "error": {
    "code": "RESOURCE_NOT_FOUND",
    "message": "Continent not found.",
    "details": { ... optional ... }
  },
  "meta": { "version": "v1", "request_id": "...", "tenant": "acme" }
}

// Validation error (HTTP 422)
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "fields": { "name": ["The name is required."] }
  },
  "meta": { ... }
}
```

`App\Exceptions\Handler` converts every `ApiException` subclass, `ValidationException`, `ModelNotFoundException`, `AuthenticationException`, and uncaught `Throwable` into these envelopes. Sentry receives the original exception untouched.

---

## 5. Routes, API Versioning, Auth Flows

### 5.1 Route files

**`routes/api.php` — central-domain only** (resolves when host matches `config('tenancy.central_domains')`):

```
POST   /v1/tenants                  → TenantController@store    (super-admin only)
GET    /v1/tenants                  → TenantController@index
GET    /v1/tenants/{identifier}     → TenantController@show
DELETE /v1/tenants/{identifier}     → TenantController@destroy
GET    /health                      → HealthController@check
GET    /health/deep                 → HealthController@deep
GET    /docs                        → Scramble UI (central)
```

**`routes/tenant.php` — tenant-domain only** (Stancl `InitializeTenancyByDomain` group):

```
# Public
POST   /v1/auth/register
POST   /v1/auth/login                      → personal access token
POST   /v1/auth/password/forgot
POST   /v1/auth/password/reset
GET    /v1/auth/email/verify/{id}/{hash}   → signed URL

# Authenticated (auth:api + EnsureTokenMatchesTenant)
POST   /v1/auth/logout
POST   /v1/auth/email/verify/resend
GET    /v1/auth/me
PATCH  /v1/auth/me

# Authenticated + verified email
GET    /v1/users
GET    /v1/users/{identifier}
PATCH  /v1/users/{identifier}
DELETE /v1/users/{identifier}              → soft delete
DELETE /v1/users/{identifier}/force        → permanent delete (super-admin)

GET    /v1/continents
GET    /v1/continents/{identifier}
POST   /v1/continents                      → idempotent (Idempotency-Key header)
PATCH  /v1/continents/{identifier}
DELETE /v1/continents/{identifier}
DELETE /v1/continents/{identifier}/force
GET    /v1/continents/export               → uses browseAll()

# Client-credentials only (scope: server)
POST   /v1/internal/continents/reindex
```

### 5.2 API versioning (grazulex/laravel-apiroute)

- URI-based: `/v1/...`, `/v2/...`. New versions live as fresh controller namespaces (`App\Http\Controllers\Api\V2\…`).
- A version can be marked deprecated in config; responses get `Sunset` and `Deprecation` HTTP headers and an `X-API-Deprecation-Warning` body field in the standard `meta`.
- `ApiVersionResolver` middleware tags every log line and Sentry event with `api_version`.
- The starter ships only `v1`; `v2` scaffolding is documented in the README.

### 5.3 Auth — Personal Access Tokens

1. **Register** — `POST /v1/auth/register` → `RegisterUserData` → `RegisterUserAction` creates user in tenant DB, dispatches `UserRegistered`; listener queues verification email. Returns 201 with user resource (no token yet).
2. **Login** — `POST /v1/auth/login` validates against tenant DB users; `IssuePersonalAccessTokenAction` calls `$user->createToken('mobile')` which writes to the **central** `oauth_access_tokens` table. The action injects a custom claim `tenant_id` via a `Bridge\AccessToken` extension. Returns `{ token, token_type: 'Bearer', expires_at, user }`.
3. **Verify email** — link is a Laravel signed URL pointing at the tenant subdomain. Hitting it dispatches `EmailVerified`; user record gets `email_verified_at`.
4. **Password reset** — token stored in tenant's `password_reset_tokens`, mailed; reset endpoint consumes it.
5. **Token revocation** — `POST /v1/auth/logout` revokes the current token. `DELETE /v1/auth/sessions/{token_id}` revokes any of the current user's tokens.

### 5.4 Auth — Client Credentials

- `oauth_clients` rows created via `php artisan passport:client --client --tenant=acme`. The custom `--tenant` flag attaches a `tenant_id` to the client row.
- `POST https://acme.api.example.com/oauth/token` with `grant_type=client_credentials&client_id=...&client_secret=...&scope=server` issues a token scoped to that tenant.
- Routes requiring this grant use middleware `client:server` (Passport's `CheckClientCredentials`).
- `EnsureTokenMatchesTenant` rejects any token whose `tenant_id` claim ≠ the subdomain-resolved tenant. **This is the load-bearing security check** preventing cross-tenant token reuse.

### 5.5 `Continent` as the reference resource

| Pattern | Where it appears on `Continent` |
| --- | --- |
| DTO | `CreateContinentData`, `UpdateContinentData` (validation lives here, not in FormRequest) |
| Repository | `ContinentRepository extends BaseRepository`; declares `allowedFilters = ['name', 'code', AllowedFilter::scope('populated')]` |
| Action | `CreateContinentAction::execute(CreateContinentData $dto): Continent` |
| Service | `ContinentService::importFromCsv(...)` — orchestrates multiple actions |
| QueryBuilder | `GET /v1/continents?filter[name]=Af&include=countries&sort=-population` |
| `browseAll()` | `GET /v1/continents/export` returns Collection wrapped in `ContinentResource::collection()` |
| Idempotent POST | `POST /v1/continents` reads `Idempotency-Key` header; replay returns cached response |
| Response cache | `GET /v1/continents` cached with tag `continents:{tenant_id}`; invalidated by `ContinentCreated/Updated/Deleted` listeners |
| Audit | Create/update/delete write to tenant `audits` table via `HasAuditTrail` |
| Search | Indexed in tenant-prefixed Meilisearch index `tenant_{id}_continents` |
| OpenAPI | Scramble auto-generates spec from typed action signatures, DTOs, resources |
| Events | `ContinentCreated`, `ContinentUpdated`, `ContinentDeleted` (queued listeners for audit, search, cache bust) |
| Policy | `ContinentPolicy` — `viewAny/view/create/update/delete/forceDelete` |
| Force delete | `DELETE /v1/continents/{id}/force` calls `repository->forceDelete()` |

---

## 6. Multi-Tenancy Operations & Data Flow

### 6.1 Tenant lifecycle

```
POST /v1/tenants                          (central domain, super-admin)
  └─ CreateTenantAction
        ├─ creates Tenant row + Domain row (central DB)
        ├─ provisions tenant DB           (CREATE DATABASE tenant_{id})
        ├─ runs tenant migrations         (database/migrations/tenant/*)
        ├─ runs TenantDatabaseSeeder      (default roles, admin user)
        ├─ creates Meilisearch indexes    (tenant_{id}_continents, etc.)
        ├─ dispatches TenantCreated event
        └─ returns Tenant resource

DELETE /v1/tenants/{identifier}           (soft delete)
  └─ Tenant marked deleted_at; DB retained for config('tenancy.soft_delete_grace_days', 90) days
  └─ All oauth_access_tokens WHERE tenant_id = X are revoked

DELETE /v1/tenants/{identifier}/force     (super-admin, X-Confirm-Drop header required)
  └─ Drops tenant DB, deletes Meilisearch indexes, deletes Domain row
```

### 6.2 Request → tenant resolution

```
Request hits Laravel
  ├─ ForceJsonResponse middleware           (sets Accept: application/json)
  ├─ Host header inspected by Stancl
  │     ├─ matches central_domain  → no tenancy bootstrap; central routes
  │     └─ matches a Domain row    → resolve Tenant, switch connection
  │           ├─ DB::setDefaultConnection('tenant') → tenant_{id}
  │           ├─ Cache prefix     = "tenant:{id}:"
  │           ├─ Redis key prefix = "tenant:{id}:"
  │           ├─ Filesystem root  = "tenants/{id}/"
  │           └─ Scout prefix     = "tenant_{id}_"
  │     └─ unknown host            → 404 TenantResolutionException
  ├─ LogApiRequests pushes tenant_id, request_id, api_version onto LogContext
  ├─ ApiVersionResolver parses /v1/ from URI
  ├─ SmartThrottle reads tenant's plan from central DB (cached 60s); applies limits
  ├─ ApiIdempotency (on POST/PUT/PATCH) checks Redis for Idempotency-Key
  ├─ auth:api validates Passport token against central oauth_access_tokens
  ├─ EnsureTokenMatchesTenant: token.tenant_id MUST equal resolved tenant_id
  └─ Controller runs
```

### 6.3 Queue jobs across tenants

1. **Stancl job middleware** — `PreventCentralBootingFromTenantJob` serializes the current tenant into the job payload on dispatch; `handle()` re-initializes the tenant context.
2. **Audit attribution** — `auth()->id()` is null in queue context. Jobs carry `actingUserId` explicitly; the `AsAction` trait reads it from constructor injection when `auth()` is null.
3. **Failed jobs** — written to the **central** `failed_jobs` table with added `tenant_id` and `acting_user_id` columns. Horizon groups failures by tenant.

### 6.4 Search indexing

- Per-tenant indexes per model: `tenant_{id}_continents`, `tenant_{id}_users`.
- `HasSearchable` overrides `searchableAs()` to return the tenant-prefixed name — Scout indexing requires no per-model prefix code.
- `ReindexContinentSearchListener` queues a Scout job on every create/update/delete.
- `php artisan tenants:reindex --tenant=acme|--all` wipes and rebuilds indexes.

### 6.5 Response caching

- `spatie/laravel-responsecache` configured with Redis. A custom `CacheProfile` adds `tenant_id` to the request signature — two tenants requesting `GET /v1/continents` get separate cache entries.
- Tag-based invalidation: cached responses are tagged `["tenant:{id}", "continents:{tenant_id}"]`. Listeners flush by tag on mutations.
- Default TTL: 5 minutes. Per-route override via `->cache(ttl: 60)`.
- Auth-required routes are NOT cached unless explicitly marked.

### 6.6 Audit log per tenant

- `owen-it/laravel-auditing` writes the `audits` table inside the **tenant DB**. Each `BaseModel` is `Auditable`.
- Stored fields: `event` (`created/updated/deleted/restored/destroyed`), `auditable_type/id`, `old_values`, `new_values`, `user_id`, `url`, `ip_address`, `user_agent`, `tags`.
- `php artisan tenant:export --tenant=acme` dumps the entire tenant DB including audits as a single archive.
- A separate central `global_audits` table records cross-tenant admin actions (tenant created/deleted, super-admin login, plan changed). Model: `App\Models\Central\GlobalAudit`.

### 6.7 Observability

Every log line and Sentry event includes:

```json
{
  "request_id": "01HF7K...",
  "tenant_id": "01HF...",
  "tenant_slug": "acme",
  "user_id": "01HF...",
  "client_id": "01HF...",
  "api_version": "v1",
  "route": "POST /v1/continents",
  "duration_ms": 142
}
```

- Logs: JSON formatter to stdout. `Log::withContext()` set in `LogApiRequests` middleware.
- Sentry: `tenant_slug` as a tag (filterable); `tenant_id` as context. PII configurable via `config/sentry.php`.
- `request_id` returned in every response's `meta.request_id` and as `X-Request-Id` header.

### 6.8 Health checks

- `GET /health` — liveness, always 200 if PHP is running.
- `GET /health/deep` — readiness, checks:
  - Central DB `SELECT 1`
  - Random sample of **3 tenant DBs** connectivity
  - Redis ping
  - Meilisearch ping
  - Mailer config present (no live send)
  - Returns 200 on all pass, 503 with per-check status on any failure.
- `/health/deep` gated by a `HEALTHCHECK_TOKEN` header to prevent fingerprinting.

### 6.9 Rate-limiting tiers

`grazulex/laravel-api-throttle-smart` reads each tenant's `plan` from central DB (cached 60s):

| Plan | Per minute | Burst | Daily quota |
| --- | --- | --- | --- |
| free | 30 | 60 | 1,000 |
| pro | 300 | 600 | 100,000 |
| enterprise | 3,000 | 6,000 | unlimited |

- Tenants without a matching plan row fall back to the `free` tier (defined in `config/api.php`).
- Counters keyed by `tenant_id` + `user_id|client_id` so one noisy user can't starve others on the same tenant.
- 429 responses include `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.
- Unauthenticated routes throttled by IP under a stricter `auth_throttle` profile.

### 6.10 Idempotency

`grazulex/laravel-api-idempotency` on POST/PUT/PATCH:

- Client sends `Idempotency-Key: <ulid>`.
- Redis key: `idempotency:tenant_{id}:user_{id}:key_{ulid}`, 24-hour TTL.
- First request: full body fingerprint + response stored.
- Replay with same key: returns cached response with `X-Idempotent-Replay: true`.
- Different body, same key: returns `409 IDEMPOTENCY_KEY_REUSED`.
- Missing header on POST: warning header in dev; enforcement configurable in prod (default: optional).

---

## 7. Testing, Quality, CI/CD

### 7.1 Pest test architecture

```
tests/
├── Pest.php
├── TestCase.php
├── Concerns/
│   ├── CreatesTenants.php
│   ├── ActsAsTenant.php
│   ├── IssuesTokens.php
│   └── AssertsJsonStructure.php
├── Architecture/
│   └── LayerBoundariesTest.php
├── Unit/
│   ├── Data/
│   ├── Repositories/
│   ├── Actions/
│   └── Services/
└── Feature/
    ├── Auth/
    ├── Tenants/
    ├── Continents/
    ├── Health/
    └── RateLimit/
```

### 7.2 Test runtime — MySQL, matching production

- `.env.testing` uses **MySQL** for both central and tenant DBs. No SQLite shortcut.
- `docker-compose.test.yml` runs `mysql:8.0-debian` with no persistent volume — recreated per CI run. Tests wait on healthcheck before starting.
- Central test DB: `qms_kit_test_central` (or `_w{TOKEN}` when running under Paratest).
- Tenant DBs created on demand by `CreatesTenants` trait, each named `qms_kit_test_tenant_w{TOKEN}_{ulid}`, dropped in `tearDown()`.
- `RefreshTenantDatabases` trait wraps each test in a central-DB transaction and tracks tenant DBs in a per-process registry for cleanup. Tests that can't run in a transaction use a `WithoutTransactions` marker that drops & recreates DBs.

### 7.3 Paratest — parallel + sequential

Two top-level Composer scripts:

- `composer test` → `./vendor/bin/pest --parallel --processes=4` (default, fast)
- `composer test:seq` → `./vendor/bin/pest` (sequential, for debugging)
- `composer test:full` → parallel (excluding `serial` group) + sequential (only `serial` group)

**Parallel-safety per worker:**

- Each Paratest worker receives `TEST_TOKEN` (1, 2, 3, 4). Central DB name is computed per token. Worker boot creates the DB if missing and runs migrations.
- Tenant DBs are namespaced by worker (`qms_kit_test_tenant_w{TOKEN}_{ulid}`).
- Redis test DB index = `10 + TEST_TOKEN`.
- Meilisearch indexes prefixed `w{TOKEN}_`.

Tests that hit shared state (`failed_jobs` format, Horizon metrics) are tagged `group('serial')`; the parallel run excludes them and a sequential pass picks them up.

### 7.4 Critical tests the kit ships

1. **`TenantIsolationTest`** — issue a token for tenant A, attempt every endpoint on tenant B's subdomain, assert 403 `TENANT_MISMATCH`. **The most important test in the kit.**
2. **`LayerBoundariesTest`** (Pest `arch()`):
   - Controllers may not import `App\Repositories\*`.
   - Repositories may not import `App\Services\*`.
   - DTOs may not import `Illuminate\Database\Eloquent\*`.
   - Models in `App\Models\Central\*` must declare `protected $connection = 'central'`.
3. **`ContinentIdempotencyTest`** — replay with same key returns the same response byte-for-byte plus the replay header.
4. **`ContinentCacheTest`** — second GET hits the response cache; POST invalidates the cache; cross-tenant requests don't share cache entries.
5. **`PlanBasedThrottleTest`** — free-plan tenant gets 30/min, pro gets 300/min, exceeding returns 429 with correct headers.
6. **`HealthCheckTest`** — `/health` shallow; `/health/deep` reports each dependency; returns 503 on any failure.

### 7.5 Static analysis & code quality

- **PHPStan** at `level: max` with `larastan/larastan`. Zero ignores by default; any ignore requires a `// @phpstan-ignore reason:` comment.
- **Rector** with `LaravelSetList::LARAVEL_130` + `SetList::CODE_QUALITY` + `SetList::DEAD_CODE`. CI runs `--dry-run`; failure on suggestions.
- **Pint** with a strict preset extending Laravel's: PSR-12, strict types declared, ordered imports, no unused imports, single-quote strings.
- **Composer audit** runs in CI; `roave/security-advisories` blocks known-vulnerable versions.

### 7.6 CI workflow (`.github/workflows/ci.yml`)

```yaml
name: CI
on: [push, pull_request]

jobs:
  lint:
    # composer install
    # ./vendor/bin/pint --test
    # ./vendor/bin/rector --dry-run

  static-analysis:
    # ./vendor/bin/phpstan analyse --no-progress

  test:
    services:
      mysql: { image: mysql:8.0, env: { MYSQL_ROOT_PASSWORD: secret } }
      redis: { image: redis:7-alpine }
      meilisearch: { image: getmeili/meilisearch:v1.10 }
    steps:
      - composer install
      - composer test:full        # paratest parallel + sequential serial group
      - upload coverage artifact

  security:
    # composer audit
```

Coverage threshold: **80% line coverage on `app/`**, enforced by Pest's `--min=80` flag.

### 7.7 Deployment artifacts

- **`docker/app/Dockerfile`** — multi-stage:
  - Stage 1: `composer:2` + `php:8.5-cli-alpine`, `--no-dev --optimize-autoloader`.
  - Stage 2: `php:8.5-fpm-alpine` with `pdo_mysql`, `redis`, `opcache`, `intl`, `bcmath`, `gd`; runs `config:cache && route:cache && view:cache && event:cache`.
- **`docker/horizon/Dockerfile`** — same base, runs `php artisan horizon` as supervisor.
- **`docker-compose.yml`** services: `app` (php-fpm), `nginx`, `horizon`, `scheduler`, `mysql`, `redis`, `meilisearch`, `mailpit`. Healthchecks on every dependency.
- **`.env.example`** is the canonical contract for required env vars. `ValidateEnvironment` service provider asserts required vars at boot and fails loudly with a clear error pointing at `.env.example`.

### 7.8 Pre-commit hooks (optional)

A `.git-hooks/pre-commit` script runs Pint + PHPStan on staged files. README documents `git config core.hooksPath .git-hooks` to enable. Not enforced — CI is the source of truth.

---

## 8. Documentation, OpenAPI, Project Polish

### 8.1 OpenAPI 3.1 via Scramble

- `dedoc/scramble` auto-generates spec from typed signatures, DTOs, resources, routes. **No annotations** in the codebase by default.
- Spec endpoints:
  - `GET /docs` — Stoplight/Swagger-UI rendered (configurable).
  - `GET /docs.json` — raw OpenAPI 3.1 spec.
  - `GET /docs?version=v1` filters routes by URI prefix.
- Scramble's `ServerVariables` registers `tenant` so docs render `https://{tenant}.api.example.com` with `acme` as the default placeholder.
- Custom Scramble extension (`App\Support\OpenApi\ScrambleExtensions`) teaches Scramble about:
  - Standardized success/error/paginated envelopes (every endpoint's response schema includes the `meta` block).
  - `Idempotency-Key`, `X-Request-Id` request headers (added globally).
  - `X-RateLimit-*`, `Sunset`, `Deprecation` response headers.
  - The 422 validation error shape with `fields`.
- Each route's security scheme is generated from middleware (`auth:api` → bearerAuth, `client:server` → clientCredentials scope `server`).
- `/docs` is public in local/dev; gated by basic auth in staging/prod (env-driven). The JSON spec is always public.

### 8.2 README structure

Top-level `README.md` is organized for three reader personas:

1. **"Just get it running"** — quick start (`cp .env.example .env && docker compose up -d && make setup`), first request `curl` example.
2. **"Tell me what's in the box"** — feature checklist, architecture diagram, layer rule table, TOC linking to sub-docs.
3. **"How do I extend this?"** — links into `docs/`.

### 8.3 `docs/` directory

```
docs/
├── architecture.md
├── tenancy.md
├── authentication.md
├── conventions.md
├── adding-a-resource.md     # the most important doc: make:api-resource walkthrough
├── api-versioning.md
├── testing.md
├── deployment.md
├── operations.md
├── security.md
└── superpowers/specs/
    └── 2026-05-11-laravel-api-starter-kit-design.md
```

`adding-a-resource.md` walks a developer from `make:api-resource Continent` to a fully tested, documented, idempotent, cached, audited endpoint **in under 10 minutes**. If that walkthrough exceeds 10 minutes, the kit has failed at its core promise.

### 8.4 `Makefile`

```
make setup            # build, install, migrate, seed
make up / down        # docker compose up -d / down
make shell            # exec into app container
make test             # paratest parallel + serial
make test-seq         # sequential, for debugging
make lint             # pint + rector --dry-run
make analyze          # phpstan
make check            # lint + analyze + test:full (full CI mirror locally)
make tenant TENANT=acme       # creates a dev tenant + domain entry
make resource NAME=Continent  # runs make:api-resource
```

### 8.5 `.env.example` discipline

- Every variable has a comment line explaining what it does and what fails if it's wrong.
- Sensitive keys (`APP_KEY`, `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY`, `SENTRY_DSN`) are blank. `ValidateEnvironment` asserts these are set in prod and fails loudly otherwise.
- Grouping order: app → db (central) → db (tenant template) → redis → mail → search → passport → sentry → feature toggles.

### 8.6 Feature toggles (`config/features.php`)

```php
return [
    'tenancy_enabled'        => env('FEATURE_TENANCY', true),
    'idempotency_enforced'   => env('FEATURE_IDEMPOTENCY_ENFORCED', false),
    'response_cache'         => env('FEATURE_RESPONSE_CACHE', true),
    'search_enabled'         => env('FEATURE_SEARCH', true),
    'audit_log_enabled'      => env('FEATURE_AUDIT', true),
    'sentry_enabled'         => env('FEATURE_SENTRY', true),
];
```

`tenancy_enabled=false` is the largest knob and disables Stancl bootstrap, routing tenant routes under the central domain. Documented but not the recommended path.

---

## 9. Success Criteria

The kit is considered complete when:

1. A developer can run `docker compose up -d && make setup` and have a working API at `http://api.localhost/health` within 5 minutes of cloning.
2. `make resource NAME=Post` produces a fully working, audited, idempotent, cached, documented `Post` resource in under 1 minute, including a green test suite.
3. The `TenantIsolationTest` proves that no token issued for tenant A can read or write data on tenant B.
4. `LayerBoundariesTest` proves controllers cannot reach Repositories directly and DTOs cannot reach Eloquent.
5. `make check` (lint + static analysis + full test suite) passes on a fresh clone with `composer test:full` ≤ 3 minutes locally.
6. `GET /docs.json` returns a valid OpenAPI 3.1 spec covering every shipped route, with response schemas including the standardized `meta` block.
7. Sentry receives a tagged event with `tenant_slug` when an exception is thrown in a tenant request context.

---

## 10. Open Risks & Future Work

| Risk / future work | Mitigation / plan |
| --- | --- |
| Passport custom claims (`tenant_id`) require subclassing `Bridge\AccessToken`. Future Passport versions may change this surface. | Wrap the bridge extension in a service provider; integration test asserts the claim is present. |
| Paratest with real MySQL + Stancl tenancy is non-trivial. First-iteration test boot may be flaky. | Sequential fallback is always available via `composer test:seq`. Document the `serial` group escape hatch. |
| Scramble's coverage of custom envelopes depends on its inference capability. | Provide `ScrambleExtensions` to teach it explicitly; integration test asserts `/docs.json` shape. |
| Stancl's central-DB / tenant-DB switching may interfere with Horizon's connection assumptions. | Horizon runs against the central DB (`failed_jobs`); job middleware re-resolves the tenant per-job. |
| Per-tenant Meilisearch indexes will multiply at scale (10k tenants × 5 indexed models = 50k indexes). | Document the cardinality; offer a config flag to switch to a single shared index with `tenant_id` filter for high-tenant-count deployments. |

---

**End of specification.**

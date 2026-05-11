# Pre-Execution DRY Principles — Laravel API Starter Kit

> **Companion hook:** `.claude/hooks/database-transaction-handling.md` defines the kit's single transaction boundary (`AsAction`). Read it before writing any write path.

## Purpose

Before writing **any** new code in this project, the agent **MUST** verify that an equivalent implementation does not already exist that can be reused via **dependency injection**. The starter kit is built around tight, opinionated abstractions (`BaseModel`, `BaseRepository`, `BaseData`, `BaseApiController`, `BaseApiResource`, the `AsAction` trait); duplicating their behavior in concrete classes silently breaks the kit's guarantees (audit trail, tenant scoping, transaction wrapping, idempotency, response envelopes).

This hook is enforced **before** generating code for: actions, services, repositories, controllers, DTOs, resources, requests, middleware, policies, listeners, jobs, Artisan commands.

---

## Architectural Invariants (Do Not Re-Implement)

These are the kit's single sources of truth. **Never re-implement, copy, or shadow them.** Inject them instead.

| Concern | Single source of truth | Wrong to re-implement |
| --- | --- | --- |
| Audit fields (`identifier`, `created_by`, `updated_by`) | `App\Models\BaseModel::booted()` | Setting `Str::uuid()` or `auth()->id()` manually in code |
| Soft + force delete on a model | `BaseRepository::delete()` / `forceDelete()` | Calling `$model->delete()` / `forceDelete()` directly from a controller, service, or action |
| Listing / filtering / sorting / includes | `BaseRepository::newQuery()` (wraps `spatie/laravel-query-builder`) | Calling `Model::query()` and chaining `where`/`orderBy` outside a repository |
| Reading all matching rows (no pagination) | `BaseRepository::browseAll()` | `Model::all()`, `Model::get()` in controllers / services |
| Pagination | `BaseRepository::paginate()` | `Model::paginate()` in controllers / services |
| Find by public id | `BaseRepository::findByIdentifier()` | `Model::find($identifier)` or `where('identifier', …)->first()` outside a repository |
| Write-side business operation | `App\Actions\*` (with `AsAction` trait) | `DB::transaction(fn() => …)` inline in a controller |
| Multi-step orchestration | `App\Services\*` | Chaining multiple repository / external API calls inside a controller or action |
| DTOs (typed input) | `App\Data\*` extending `BaseData` | Passing raw arrays between layers; per-controller validation logic |
| Response envelopes | `RespondsWithJson` trait on `BaseApiController` | Hand-built `response()->json(...)` payloads |
| Resource serialization | `BaseApiResource` (exposes `identifier` as `id`, ISO timestamps, `whenIncluded()`) | New `JsonResource` subclasses that bypass the base |
| Tenant resolution | Stancl middleware + `App\Services\TenantContextService` | Reading subdomain / tenant manually inside business code |
| Cross-tenant token rejection | `EnsureTokenMatchesTenant` middleware | Per-controller `tenant_id` checks |
| Idempotency | `App\Services\IdempotencyService` (driven by middleware) | Hand-rolled `Idempotency-Key` lookup in a controller |
| Rate-limit tiers | `grazulex/laravel-api-throttle-smart` config in `config/api.php` | Manually attaching `throttle:` middleware with hard-coded numbers |
| Response caching | `spatie/laravel-responsecache` + custom `CacheProfile` | `Cache::remember()` calls inside controllers / repositories |
| Search indexing | `HasSearchable` trait + Scout | Manual Meilisearch / Scout calls in actions or services |
| Audit logging | `HasAuditTrail` trait + `owen-it/laravel-auditing` events | Inserting rows into `audits` manually |
| Tenant connection switching | Stancl tenancy bootstrappers | `DB::connection('central')` / `DB::setDefaultConnection()` ad-hoc |
| Permissions | `spatie/laravel-permission` + Policies in `app/Policies/` | Inline `if ($user->role === '…')` checks |
| Standardized exceptions | `App\Exceptions\ApiException` and subclasses | `abort(404, '…')`, `throw new \Exception(...)` in business code |

If a task seems to require re-implementing any row above, **stop and search the codebase first.**

---

## MANDATORY Pre-Code-Generation Checklist

Run through this **before** writing the first line of any new class or method.

### 1. Repository check

- [ ] Read `app/Repositories/BaseRepository.php` — is there already a generic method (`browseAll`, `paginate`, `findByIdentifier`, `create`, `update`, `delete`, `forceDelete`, `newQuery`)?
- [ ] Search `app/Repositories/` for a concrete repository with a domain-named method that does what I need.
- [ ] If yes → inject the repository's `*RepositoryInterface` via constructor and call it.
- [ ] **Never** call `Model::query()`, `Model::create()`, `Model::where(...)` from controllers, actions, services, or listeners. Repository layer only.

```bash
# Verify before writing
rg -n "extends BaseRepository" app/Repositories/
rg -n "function (browse|paginate|find|create|update|delete|forceDelete)" app/Repositories/
```

### 2. Action check

- [ ] Search `app/Actions/` for an existing single-purpose action that mirrors what I'm about to do.
- [ ] Check that the action uses the `AsAction` trait — if so, it already wraps in `DB::transaction`, logs, and resolves `actingUserId`. **Do not re-wrap.**
- [ ] If a similar action exists in another domain (e.g., `CreateUserAction` while you're writing `CreateContinentAction`), use it as the template — do not invent a new shape.

```bash
rg -n "class \w+Action" app/Actions/
rg -n "use AsAction" app/Actions/
```

### 3. Service check

- [ ] Search `app/Services/` for orchestration logic that already exists.
- [ ] `ApiResponseService`, `TenantContextService`, `IdempotencyService` are kit-provided — never duplicate them.
- [ ] If the new logic spans multiple repositories or external APIs, it belongs in a service, not in an action or controller.

```bash
rg -n "class \w+Service" app/Services/
```

### 4. DTO check

- [ ] Search `app/Data/` for an existing DTO that matches the shape I need.
- [ ] Use `BaseData::forCreation()` / `forUpdate()` — do not re-implement partial-update null-stripping.
- [ ] Validation rules live on the DTO (Spatie Laravel Data attributes), not in FormRequest or controller.

```bash
rg -n "extends BaseData" app/Data/
```

### 5. Resource / response check

- [ ] Extend `BaseApiResource`, never `JsonResource` directly.
- [ ] For success / error / paginated responses, call the `RespondsWithJson` trait methods (`success`, `error`, `paginated`) on the base controller. Do not assemble `response()->json([...])` by hand.

```bash
rg -n "extends BaseApiResource" app/Http/Resources/
rg -n "use RespondsWithJson" app/Support/Concerns/
```

### 6. Trait check

- [ ] Search `app/Support/Concerns/` before defining a new trait or repeating model boilerplate.
- [ ] Kit-provided traits: `HasAuditTrail`, `HasUuidIdentifier`, `HasSearchable`, `RespondsWithJson`. If your need overlaps, **use the existing trait**.

```bash
ls app/Support/Concerns/
rg -n "^trait " app/Support/Concerns/
```

### 7. Middleware check

- [ ] Review `app/Http/Middleware/` and `bootstrap/app.php` for an existing middleware that handles the cross-cutting concern.
- [ ] **Never** re-implement: `ForceJsonResponse`, `LogApiRequests`, `EnsureEmailVerified`, `EnsureTokenMatchesTenant`, `ApiVersionResolver`, `PreventCentralDomainAccess`.

```bash
ls app/Http/Middleware/
rg -n "middleware" bootstrap/app.php
```

### 8. Policy check

- [ ] Search `app/Policies/` for a similar ability before writing inline authorization checks.
- [ ] Generated policies include `viewAny / view / create / update / delete / forceDelete` — reuse and extend.
- [ ] Use `Gate::authorize()` or `$this->authorize()` on the base controller — never inline `if (auth()->user()->...)` checks.

```bash
rg -n "class \w+Policy" app/Policies/
```

### 9. Event / Listener check

- [ ] Search `app/Events/` and `app/Listeners/` for existing domain events.
- [ ] Audit logging, search reindexing, and cache invalidation are **already handled by listeners** (`LogAuditTrailListener`, `ReindexContinentSearchListener`, response-cache tag flushing). Dispatching the correct event in your action triggers all three. **Do not call audit / Scout / cache directly.**

```bash
rg -n "class \w+(Created|Updated|Deleted)" app/Events/
```

### 10. Generator check

- [ ] **Before creating a new resource manually**, run `php artisan make:api-resource {Name}`.
- [ ] The generator scaffolds the full stack (model, migration, repository + interface, action × 3, DTO × 2, controller, resource, policy, events × 3, factory, test) using base classes. Manual scaffolding **will** drift from kit conventions.

```bash
php artisan list make | grep api-resource
```

### 11. Tenant-aware code check

- [ ] Never write `DB::setDefaultConnection(...)`, `DB::connection('central')->...`, or `where('tenant_id', ...)`. Stancl bootstrap + the central/tenant model split (`App\Models\Central\*` declares `$connection = 'central'`) handle this.
- [ ] To read the current tenant in business code, inject `TenantContextService`.

```bash
rg -n "setDefaultConnection|connection\('central'\)" app/
```

### 12. Exception check

- [ ] For domain errors, throw an `App\Exceptions\ApiException` subclass (`ResourceNotFoundException`, `DomainException`, `TenantResolutionException`). The kit's `Handler` converts these to the standardized error envelope automatically.
- [ ] **Never** call `abort(...)` or `response()->json(['error' => …], 4xx)` from business code — the envelope shape will diverge.

```bash
rg -n "class \w+Exception" app/Exceptions/
```

---

## Critical DRY Violations Specific to This Kit

### ❌ Skipping the repository layer

```php
// ❌ BAD — direct Eloquent access; bypasses audit, query-builder filters, and layer rules
$continents = Continent::where('code', $code)->get();

// ✅ GOOD — through repository, gets allowedFilters + audit + tenant scoping for free
public function __construct(private ContinentRepositoryInterface $repo) {}
$continents = $this->repo->browseAll();
```

### ❌ Manually wrapping a transaction in a controller

```php
// ❌ BAD — duplicates what AsAction already does, and breaks the layer rule
public function store(Request $req) {
    return DB::transaction(function () use ($req) {
        $continent = Continent::create($req->all());
        // …
    });
}

// ✅ GOOD — controller delegates to action; AsAction trait wraps the transaction
public function store(CreateContinentRequest $req, CreateContinentAction $action) {
    $continent = $action->execute(CreateContinentData::from($req->validated()));
    return $this->success(ContinentResource::make($continent), 201);
}
```

### ❌ Reading tenant from the request manually

```php
// ❌ BAD — tenancy resolution is the middleware's job; this drifts
$tenantSlug = explode('.', $request->getHost())[0];
$tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

// ✅ GOOD
public function __construct(private TenantContextService $tenants) {}
$tenant = $this->tenants->current();
```

### ❌ Hand-building a response envelope

```php
// ❌ BAD — diverges from the standardized envelope; Scramble docs become wrong
return response()->json(['data' => $continent, 'status' => 'ok']);

// ✅ GOOD — RespondsWithJson on BaseApiController
return $this->success(ContinentResource::make($continent));
```

### ❌ Manual UUID / created_by / updated_by

```php
// ❌ BAD — BaseModel::booted() already does this
$model->identifier = Str::uuid();
$model->created_by = auth()->id();

// ✅ GOOD — let BaseModel handle it
$this->repository->create($data); // identifier + audit fields populated automatically
```

### ❌ Inline `auth()->id()` inside a queued job

```php
// ❌ BAD — auth() is null inside queues; audit fields silently become null
public function handle() {
    $this->repo->update($order, $this->data); // updated_by ends up null
}

// ✅ GOOD — pass actingUserId explicitly, AsAction reads it
public function __construct(public readonly int $actingUserId, public readonly array $data) {}
public function handle(OrderRepositoryInterface $repo) {
    AsAction::actingAs($this->actingUserId, fn() => $repo->update($order, $this->data));
}
```

### ❌ Duplicating cache / search / audit calls

```php
// ❌ BAD — three sources of truth
Cache::tags(['continents:'.$tenantId])->flush();
Continent::search('')->reindex();
$audit->insert([...]);

// ✅ GOOD — dispatch the event; listeners handle all three
event(new ContinentUpdated($continent));
```

### ❌ Hand-rolling idempotency

```php
// ❌ BAD
if (Cache::has('idem:'.$key)) return Cache::get('idem:'.$key);

// ✅ GOOD — ApiIdempotency middleware + IdempotencyService already cover this
// Just declare the route under the idempotent middleware group.
```

### ❌ Bespoke rate-limit middleware

```php
// ❌ BAD — bypasses plan tiers
Route::post('/v1/foo', …)->middleware('throttle:60,1');

// ✅ GOOD — let SmartThrottle read the tenant's plan
Route::post('/v1/foo', …); // SmartThrottle is in the tenant group
```

---

## Dependency Injection Rules

### ✅ Always constructor-inject repository **interfaces**, not concrete classes

```php
public function __construct(
    private ContinentRepositoryInterface $continents,
    private TenantContextService $tenants,
    private IdempotencyService $idempotency,
) {}
```

`RepositoryServiceProvider` binds `*RepositoryInterface` → concrete. Injecting interfaces keeps tests easy to mock and the layer rules enforceable in `LayerBoundariesTest`.

### ✅ Never `new` a kit-provided service

```php
// ❌ BAD
$service = new ContinentService($repo);

// ✅ GOOD
public function __construct(private ContinentService $service) {}
```

### ✅ Never call repositories from other repositories

This violates the layer-boundary rule and `LayerBoundariesTest` will fail in CI. If two write paths share logic, lift that logic into a **Service** and inject the service into both Actions.

---

## Search Strategies (Run These Before Coding)

```bash
# 1. Find existing concrete implementations of a behavior
rg -n "function (create|update|delete|forceDelete|browseAll|paginate|findByIdentifier)" app/Repositories/

# 2. Find existing actions across all domains
rg -n "class \w+Action" app/Actions/

# 3. Find existing DTOs
rg -n "class \w+Data extends BaseData" app/Data/

# 4. Find a similar event pattern (mirroring Continent for a new resource)
rg -n "class Continent(Created|Updated|Deleted)" app/Events/

# 5. Find existing kit traits before adding new ones
ls app/Support/Concerns/

# 6. Confirm a middleware doesn't already exist
ls app/Http/Middleware/

# 7. Sanity-check that no direct Eloquent access leaks outside repositories
rg -n "Model::(create|where|find|paginate|all|get|query)\(" app/Http app/Actions app/Services
# ^ this command must return ZERO results for kit-conformant code
```

---

## "Mirror an Existing Resource" Pattern

When implementing a **new resource**, mirror `Continent` step-for-step. Every architectural pattern in the kit is exercised by `Continent` (see Section 5.5 of the design spec). The order:

1. Run `php artisan make:api-resource {Name}`.
2. Open the generated files alongside the `Continent` equivalents.
3. Fill in only:
   - `model()` return value
   - `allowedFilters` / `allowedIncludes` / `allowedSorts` on the repository
   - DTO property list + validation attributes
   - Migration columns
   - Policy abilities
   - Resource fields
4. **Do not** add anything else. If you find yourself wanting to, ask: "is `Continent` doing this differently? Why?" Most "extra" code is a DRY violation.

---

## Refactoring Trigger Points

If you find yourself:

- Copying ≥ 3 lines from another action / repository / service → extract to a shared trait or service.
- Writing a second `DB::transaction { … }` outside of `AsAction` → the work belongs in an Action.
- Adding a second `Cache::` / `Scout::` / `Audit::` call alongside an existing event → fold it into a listener for that event.
- Writing a second per-route `throttle:N,1` → add a plan tier to `config/api.php` instead.
- Writing a second `if ($user->cannot(...))` inline → add a policy ability.

---

## Verification Steps Before Marking a Task Complete

1. ✅ Run `rg -n "Model::(create|where|find|paginate|all|get|query)\(" app/Http app/Actions app/Services` — must return **zero** results.
2. ✅ Run `rg -n "DB::transaction" app/Http app/Repositories app/Listeners app/Jobs` — must return **zero** results. Transactions are opened **only** by `AsAction` (inside `app/Actions/Concerns/AsAction.php`) and, when multiple actions must commit atomically together, by methods in `app/Services/*` (each such call must carry a comment naming the actions whose atomicity is being guaranteed). See `.claude/hooks/database-transaction-handling.md`.
3. ✅ Run `rg -n "response\(\)->json\(" app/Http/Controllers` — must return **zero** results (use `RespondsWithJson`).
4. ✅ Run `rg -n "abort\(" app/Http app/Actions app/Services app/Repositories` — must return **zero** results (use `ApiException` subclasses).
5. ✅ Run `./vendor/bin/pest --filter=LayerBoundariesTest` — must pass.
6. ✅ Run `./vendor/bin/pest --filter=TenantIsolationTest` — must pass.
7. ✅ `./vendor/bin/phpstan analyse` — clean at `level: max`.
8. ✅ Confirm every new class is constructor-injecting interfaces, not `new`-ing kit services.

---

## FINAL CHECKPOINT

Before writing **any** new code in this project, answer:

> "Has a base class, trait, service, repository method, event, listener, middleware, or generator already solved this — and can I inject it?"

- **Yes** → inject and reuse. Stop.
- **Maybe** → search the codebase using the commands above. Then decide.
- **No** → proceed, but write the new code so that the **next** developer's answer is "Yes."

> The best code in this starter kit is code that **doesn't get written** because `BaseRepository`, `AsAction`, `BaseData`, `BaseApiController`, `BaseApiResource`, and the event/listener pipeline already do the work.

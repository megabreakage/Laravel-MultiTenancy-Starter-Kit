# Database Transaction Handling — Laravel API Starter Kit

**CRITICAL: This document defines the ONLY acceptable pattern for database transactions in this codebase.**

---

## Core Principle

In this kit, **`DB::transaction(fn() => …)` is wrapped exactly once** — inside the `AsAction` trait on `App\Actions\*`. Controllers, services, repositories, listeners, and jobs **never** open a transaction directly. They never call `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`.

This is the single, kit-wide transaction boundary:

```
Controller   →   Action (AsAction)   →   Repository
                  │
                  └─ wraps DB::transaction(function () use ($dto) { … });
                     Laravel auto-rolls back on any thrown exception.
```

If you find yourself typing `DB::transaction(` outside `App\Actions\Concerns\AsAction`, **stop**. Either:

- The work belongs in an existing action (inject it), or
- You need a new action — generate it through `php artisan make:api-resource` or mirror an existing action class.

---

## The Single Transaction Site (`AsAction`)

`App\Actions\Concerns\AsAction` is the only place in the kit that opens a transaction:

```php
trait AsAction
{
    public function execute(BaseData $dto): Model
    {
        $actingUserId = $this->resolveActingUserId();      // auth → queue payload → system user

        Log::info(static::class . ' starting', [           // OUTSIDE transaction
            'acting_user_id' => $actingUserId,
            'dto'            => $dto->toArray(),
        ]);

        $model = DB::transaction(function () use ($dto) {  // WRAP ONLY THE WRITE
            return $this->handle($dto);                    // concrete action implements handle()
        });

        Log::info(static::class . ' completed', [          // OUTSIDE transaction
            'model_id' => $model->getKey(),
        ]);

        return $model;
    }
}
```

Concrete actions implement `handle(BaseData $dto): Model`. They **must not** open another transaction — Laravel would nest it, and the kit's contract is "one action = one transaction."

```php
class CreateContinentAction
{
    use AsAction;

    public function __construct(private ContinentRepositoryInterface $repo) {}

    protected function handle(CreateContinentData $dto): Continent
    {
        $continent = $this->repo->create($dto->toArray());

        event(new ContinentCreated($continent));   // dispatch inside the transaction — Laravel queues
                                                   // listeners until after commit (afterCommit listeners)

        return $continent;
    }
}
```

---

## Mandatory Rules

### 1. Authorization BEFORE the transaction

Authorization happens in the controller (policy via `BaseApiController::authorize()`) before the action is invoked. The action never authorizes.

```php
// ✅ CORRECT — kit pattern
public function store(CreateContinentRequest $request, CreateContinentAction $action): JsonResponse
{
    Gate::authorize('create', Continent::class);                       // before transaction

    $continent = $action->execute(CreateContinentData::from($request->validated()));

    return $this->success(ContinentResource::make($continent), Response::HTTP_CREATED);
}

// ❌ WRONG — authorization inside action / transaction
protected function handle(CreateContinentData $dto): Continent
{
    Gate::authorize('create', Continent::class);                       // holds DB locks during gate check
    return $this->repo->create($dto->toArray());
}
```

**Why:** Gate checks can hit policies, cache, or external systems. They have no business inside an open transaction.

### 2. Validation BEFORE the transaction

Validation lives on the **DTO** (Spatie Laravel Data attributes). The DTO is hydrated in the controller from the FormRequest's validated payload, **before** calling the action.

```php
// ✅ CORRECT
$dto = CreateContinentData::from($request->validated());   // throws ValidationException here, outside tx
$continent = $action->execute($dto);

// ❌ WRONG
protected function handle(array $rawInput): Continent
{
    $dto = CreateContinentData::from($rawInput);           // validation now inside transaction
    return $this->repo->create($dto->toArray());
}
```

**Why:** A failed validation should never have opened a transaction.

### 3. Wrap ONLY repository / database writes

The body of `handle()` should contain repository calls and the event dispatch — nothing else.

```php
// ✅ CORRECT — write + event only
protected function handle(CreateContinentData $dto): Continent
{
    $continent = $this->repo->create($dto->toArray());
    event(new ContinentCreated($continent));               // listeners are afterCommit, see Rule 7
    return $continent;
}

// ❌ WRONG — external I/O inside transaction
protected function handle(CreateContinentData $dto): Continent
{
    $this->slack->notify('Creating continent…');           // external API
    $continent = $this->repo->create($dto->toArray());
    $this->mailer->send(new ContinentCreatedMail($continent));   // mail send (network)
    Cache::tags("continents:{$tenant}")->flush();          // manual cache flush
    return $continent;
}
```

**Why:** Anything that takes network time, may hang, or may fail independently of the DB write must live outside the transaction. The kit handles cache flushing, search reindexing, audit logging, mail dispatch, and notifications via **event listeners marked `afterCommit`** — see Rule 7.

### 4. Logging is OUTSIDE the transaction

`AsAction::execute()` already logs **before** and **after** the transaction. Concrete `handle()` methods **do not log**.

```php
// ✅ CORRECT — logging happens in the trait, outside the wrap
public function execute(BaseData $dto): Model
{
    Log::info(static::class.' starting', [...]);           // outside
    $model = DB::transaction(fn() => $this->handle($dto)); // wrap only
    Log::info(static::class.' completed', [...]);          // outside
    return $model;
}

// ❌ WRONG — logging inside handle()
protected function handle(CreateContinentData $dto): Continent
{
    Log::info('Creating continent', ['name' => $dto->name]);    // inside transaction — I/O lengthens lock
    return $this->repo->create($dto->toArray());
}
```

**Why:** Log writes are I/O. They lengthen lock hold time and add no value inside the wrap.

### 5. NO manual transaction management

`DB::beginTransaction()`, `DB::commit()`, `DB::rollBack()` are **banned** in this codebase. The `LayerBoundariesTest` asserts zero matches across `app/`.

```php
// ❌ NEVER
DB::beginTransaction();
try {
    $continent = $this->repo->create($data);
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}

// ✅ ALWAYS — let AsAction do it
$continent = $action->execute($dto);
```

**Why:** Manual management is error-prone (forgetting `rollBack` on a non-`Exception` `Throwable`, mismatched begin/commit pairs on nested calls). The closure form is the framework's recommended pattern.

### 6. Let exceptions bubble naturally

Concrete `handle()` methods **never** catch exceptions to rollback. They let exceptions throw; `DB::transaction()` rolls back automatically.

```php
// ✅ CORRECT — exceptions bubble; AsAction logs at the boundary
protected function handle(UpdateContinentData $dto): Continent
{
    $continent = $this->repo->findByIdentifier($dto->identifier);
    return $this->repo->update($continent, $dto->toArray());   // throws → auto rollback
}

// ❌ WRONG — manual rollback or swallowing
protected function handle(UpdateContinentData $dto): Continent
{
    try {
        return $this->repo->update(...);
    } catch (\Throwable $e) {
        DB::rollBack();                                          // already rolled back; this errors
        throw $e;
    }
}
```

The kit's `App\Exceptions\Handler` converts every exception type into the standardized error envelope and tags Sentry with `tenant_slug`. Controllers do **not** wrap actions in `try/catch` — the global handler is the catch.

### 7. Side-effects via events with `afterCommit` listeners

External side-effects (mail, search reindex, cache flush, audit-trail post-processing, Slack/webhook notifications) are triggered by **domain events** dispatched **inside** `handle()`. The kit's listeners are all declared `ShouldHandleEventsAfterCommit`, so Laravel defers them until the transaction commits successfully.

```php
// In the action:
event(new ContinentCreated($continent));

// In the listener:
class ReindexContinentSearchListener implements ShouldQueue, ShouldHandleEventsAfterCommit
{
    public function handle(ContinentCreated $event): void { /* Scout reindex */ }
}
```

This means:
- Side-effects never fire on a rolled-back transaction.
- The transaction stays small (write only).
- One event triggers audit, search, cache invalidation, and notifications — no manual fan-out from the action.

**Never** call `Cache::flush`, `Scout::index`, `Mail::send`, or `Notification::send` directly from inside `handle()`. Dispatch the event; let the listeners do it.

---

## Standard Templates

### Create (controller → action)

```php
public function store(
    CreateContinentRequest $request,
    CreateContinentAction $action,
): JsonResponse {
    Gate::authorize('create', Continent::class);

    $continent = $action->execute(
        CreateContinentData::from($request->validated())
    );

    return $this->success(
        ContinentResource::make($continent),
        Response::HTTP_CREATED,
    );
}
```

```php
final class CreateContinentAction
{
    use AsAction;

    public function __construct(
        private ContinentRepositoryInterface $repo,
    ) {}

    protected function handle(CreateContinentData $dto): Continent
    {
        $continent = $this->repo->create($dto->toArray());
        event(new ContinentCreated($continent));
        return $continent;
    }
}
```

### Update (controller → action)

```php
public function update(
    UpdateContinentRequest $request,
    string $identifier,
    UpdateContinentAction $action,
): JsonResponse {
    $continent = $this->continents->findByIdentifier($identifier);

    Gate::authorize('update', $continent);

    $updated = $action->execute(
        UpdateContinentData::from(['identifier' => $identifier, ...$request->validated()])
    );

    return $this->success(ContinentResource::make($updated));
}
```

```php
final class UpdateContinentAction
{
    use AsAction;

    public function __construct(
        private ContinentRepositoryInterface $repo,
    ) {}

    protected function handle(UpdateContinentData $dto): Continent
    {
        $continent = $this->repo->findByIdentifier($dto->identifier);
        $continent = $this->repo->update($continent, $dto->toArray());
        event(new ContinentUpdated($continent));
        return $continent;
    }
}
```

### Delete (soft) and Force-Delete

```php
public function destroy(string $identifier, DeleteContinentAction $action): JsonResponse
{
    $continent = $this->continents->findByIdentifier($identifier);

    Gate::authorize('delete', $continent);

    $action->execute(DeleteContinentData::from(['identifier' => $identifier]));

    return $this->success(['id' => $identifier]);
}

public function forceDestroy(string $identifier, ForceDeleteContinentAction $action): JsonResponse
{
    $continent = $this->continents->findByIdentifier($identifier);

    Gate::authorize('forceDelete', $continent);

    $action->execute(DeleteContinentData::from(['identifier' => $identifier]));

    return $this->success(['id' => $identifier]);
}
```

```php
final class DeleteContinentAction
{
    use AsAction;

    public function __construct(private ContinentRepositoryInterface $repo) {}

    protected function handle(DeleteContinentData $dto): Continent
    {
        $continent = $this->repo->findByIdentifier($dto->identifier);
        $this->repo->delete($continent);                        // soft delete
        event(new ContinentDeleted($continent));
        return $continent;
    }
}

final class ForceDeleteContinentAction
{
    use AsAction;

    public function __construct(private ContinentRepositoryInterface $repo) {}

    protected function handle(DeleteContinentData $dto): Continent
    {
        $continent = $this->repo->findByIdentifier($dto->identifier);
        $this->repo->forceDelete($continent);                   // permanent
        event(new ContinentDeleted($continent, force: true));
        return $continent;
    }
}
```

### Multi-step orchestration (Service layer)

When a single business operation requires **multiple actions** to participate in **one transaction** (rare, but legitimate), wrap the **service** method — not the actions — in a transaction. The actions inside become the unit of work; their internal `AsAction` transactions become **savepoints** (Laravel handles this transparently with `transactionLevel()`).

```php
final class ContinentService
{
    public function __construct(
        private CreateContinentAction $createContinent,
        private ImportCountriesAction $importCountries,
    ) {}

    public function importFromCsv(ImportContinentData $dto): Continent
    {
        // ✅ CORRECT — one transaction wraps the orchestration; nested actions become savepoints
        return DB::transaction(function () use ($dto) {
            $continent = $this->createContinent->execute($dto->continent);
            $this->importCountries->execute($dto->countries->withContinent($continent));
            return $continent;
        });
    }
}
```

This is the **only** place in user-land code outside `AsAction` where `DB::transaction` is acceptable, and it is restricted to `App\Services\*`. It must be justified by a comment naming the actions whose atomicity must be guaranteed together. `LayerBoundariesTest` whitelists `App\Services\*` for this call.

---

## Execution Flow

```
HTTP Request
  ↓
Middleware (auth, tenant, version, throttle, idempotency)
  ↓
Controller method
  ├─ Gate::authorize(...)              ← OUTSIDE transaction
  ├─ Repository read (if needed)       ← OUTSIDE transaction (no write)
  ├─ DTO::from($request->validated())  ← OUTSIDE transaction
  ↓
$action->execute($dto)
  ↓
AsAction::execute()
  ├─ Log::info(starting)               ← OUTSIDE transaction
  ├─ DB::transaction(function () {
  │     handle($dto)
  │       ├─ $repo->create(...)        ← INSIDE transaction
  │       ├─ event(new XCreated(...))  ← INSIDE; listeners deferred to afterCommit
  │       └─ return $model
  │  })
  ├─ Log::info(completed)              ← OUTSIDE transaction
  ↓
Controller wraps response
  ↓
Resource → standardized JSON envelope
  ↓
HTTP Response
  ↓
Queue picks up afterCommit listeners (audit, search, cache flush, mail, notifications)
```

Any thrown exception inside `handle()` rolls back the transaction, **prevents** listeners from firing, propagates to `App\Exceptions\Handler`, which renders the standardized error envelope and sends the exception to Sentry tagged with `tenant_slug`.

---

## Common Mistakes To Avoid

### ❌ Opening a transaction in a controller

```php
public function store(...) {
    return DB::transaction(function () use ($request) {           // controller opening tx
        return $this->repo->create($request->validated());        // and skipping the action!
    });
}
```

Fix: route through an action.

### ❌ Opening a transaction inside `handle()`

```php
protected function handle(CreateContinentData $dto): Continent
{
    return DB::transaction(fn() => $this->repo->create(...));     // double-wrap
}
```

Fix: remove the inner wrap. `AsAction::execute()` already wraps.

### ❌ Try/catch with manual rollback

Already banned by Rule 5. `LayerBoundariesTest` greps for `beginTransaction|commit\(|rollBack\(` and fails the build.

### ❌ External calls inside `handle()`

```php
protected function handle(CreateContinentData $dto): Continent
{
    $continent = $this->repo->create($dto->toArray());
    Http::post('https://hooks.slack.com/...', [...]);             // network call inside tx!
    return $continent;
}
```

Fix: dispatch an event; move the HTTP call to a listener marked `ShouldQueue + ShouldHandleEventsAfterCommit`.

### ❌ Wrapping repository reads

```php
$continent = DB::transaction(fn() => $this->repo->findByIdentifier($id));   // read in tx — no
```

Fix: reads don't need transactions. Skip the wrap.

---

## Banned Patterns (Enforced by CI)

The `LayerBoundariesTest` in `tests/Architecture/LayerBoundariesTest.php` asserts these greps return **zero results** in `app/`:

| Grep | Allowed locations (exceptions) |
| --- | --- |
| `DB::transaction(` | `app/Actions/Concerns/AsAction.php`, `app/Services/*` |
| `DB::beginTransaction\|DB::commit\|DB::rollBack` | nowhere |
| `Model::(create\|where\|find\|paginate\|all\|get\|query)\(` outside `app/Repositories/` | nowhere outside repositories |
| `try.*catch.*rollBack` | nowhere |
| `response()->json(` in `app/Http/Controllers/` | nowhere (use `RespondsWithJson`) |
| `abort(` in `app/Actions`, `app/Services`, `app/Repositories` | nowhere (throw `ApiException` subclasses) |

The build fails the moment any of these returns a match.

---

## Why This Pattern

1. **One source of truth.** The transaction boundary lives in exactly one trait. Audit it once.
2. **Minimum lock duration.** Authorization, validation, logging, and side-effects are outside the wrap.
3. **No double-rollback bugs.** Manual `rollBack()` after Laravel has already rolled back throws `RuntimeException: There is no active transaction`.
4. **Side-effects only fire on commit.** `afterCommit` listeners mean we never send an email or reindex search for a transaction that failed.
5. **Testable.** Actions are pure functions of DTO → Model; tests don't need to manage transactions.
6. **Composable.** A service can orchestrate multiple actions in one outer transaction; nested `AsAction` transactions become savepoints automatically.
7. **Tenancy-safe.** Stancl's tenant-DB switching happens above the action. The transaction always runs on the correct connection.
8. **Queue-safe.** When actions run from queued jobs, `AsAction` reads `actingUserId` from the job payload, so audit attribution survives the transaction.

---

## Decision Tree

> "I need to add a database write."

1. Is it a single business operation on one aggregate? → Add it to (or create) an `App\Actions\*` class. Done.
2. Does it span multiple actions that must commit atomically together? → Add an `App\Services\*` method that wraps the action calls in a `DB::transaction` (justified by a comment).
3. Are you tempted to write `DB::transaction(` inside a controller, repository, listener, or job? → **Stop.** Re-read this document. Refactor.

> "I need to send a notification / flush cache / reindex search after a write."

1. Define a domain event (`SomethingCreated`, `SomethingUpdated`, `SomethingDeleted`).
2. Dispatch it from inside `handle()`.
3. Create a listener that implements `ShouldQueue + ShouldHandleEventsAfterCommit`.
4. Never call the side-effect synchronously from `handle()`.

---

## Questions?

If a scenario isn't covered:

1. **Default to the safest option:** the transaction wraps **only** the repository write.
2. **Ask:** *"Does this belong inside the transaction?"* — for most operations the answer is **no**.
3. **If uncertain,** keep it outside.
4. **Consult** this document, the design spec (§4.4, §5), and `.claude/hooks/pre-execution-dry-principles.md`.

---

**Applies to:** every write path in `app/` — controllers, actions, services, listeners, jobs, console commands.
**Enforced by:** `tests/Architecture/LayerBoundariesTest.php` + CI `phpstan` + code review.

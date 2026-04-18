---
name: doctrine-implementation
description: Plan and implement FOSSBilling persistence changes using Doctrine-first patterns. Use when adding new DB-backed features, migrating legacy RedBean queries or rewriting existing parts of the system using Doctrine.
license: Apache-2.0
compatibility: Designed for the FOSSBilling repository. Assumes access to the repo files and standard PHP tooling.
metadata:
  project: FOSSBilling
  owner: FOSSBilling
---

# Doctrine-first implementation

Use this skill for FOSSBilling tasks that add or refactor persistence logic.

## Default approach

- Use Doctrine for new persistence code.
- Default to entity-centric persistence: create/update/delete through Doctrine entities whenever practical.
- Prefer compatibility-first migrations, but allow core schema evolution when it improves correctness, maintainability, or performance.
- When schema or seed-content changes are needed, route them through `update-patch-creator` and require a seamless migration path via update patches.
- Keep the change minimal. Extend existing entities or repositories before inventing new layers.
- Service-layer contract: keep business-operation entry points (`create`, `update`, `delete`) in Service classes; use repositories for query/read responsibilities.
- Write-path contract: Service orchestrates validation, events, and logging; entity manager persists write changes.
- End-goal contract: migrated module methods should return and consume Doctrine entities/value data, not legacy RedBean `Model_*` types.

## Gotchas

- Do not add new RedBean-backed persistence for new functionality.
- Use `$di['em']` for Doctrine entity manager access.
- Avoid direct SQL-style `insert()`/`update()`/`delete()` write helpers for normal domain writes; prefer creating/updating entities and persisting via the entity manager.
- DBAL write helpers are allowed only as a temporary bridge when entity mapping is not yet sufficient and behavior must stay compatible. If used, explicitly mark as transitional and keep scope narrow.
- Do not add defensive checks like `isset($this->di['em'])`; the entity manager is always available in this context.
- Do not add defensive fallback logic to RedBean when Doctrine entity manager access fails; treat `$di['em']` as always available in this skill's scope.
- During Doctrine migrations, expect initial test failures like `Identifier "em" is not defined`; legacy tests often need manual updates to inject a mock entity manager into the DI container.
- When this happens, fix the test setup (inject/mock `em`) instead of adding runtime guards in production code.
- Put entities in `src/modules/<Module>/Entity/<Entity>.php`.
- Put repositories in `src/modules/<Module>/Repository/<EntityRepository>.php`.
- For entities with `createdAt`/`updatedAt`, prefer Doctrine lifecycle callbacks to set timestamps automatically (`#[ORM\\HasLifecycleCallbacks]` + `#[ORM\\PrePersist]`/`#[ORM\\PreUpdate]`).
- If the task touches pagination, prefer `$di['pager']->paginateDoctrineQuery()` instead of legacy result-set pagination.
- During RedBean-to-Doctrine migrations, move search query builder methods (for example `getSearchQuery`) from Service classes into repository classes.
- Keep `create`, `update`, and `delete` entry points in Service classes (business-operation layer).
- It is fine to move `get`/read/query/search/list methods and other read-oriented persistence helpers into repository classes.
- After moving a search query builder method to a repository, update all call sites to use the repository method directly. Do not keep a passthrough alias in the Service class.
- During RedBean-to-Doctrine migrations, do not keep old Model classes for convenience. The target state is full Doctrine usage for the module's entities.
- Move repository-appropriate functions (query builders, search helpers, and similar persistence logic) into repositories when this can be done without losing functionality.
- For paginated API queries, do not explicitly define `$per_page` or `$page` unless the endpoint needs to override pager defaults.
- If a module already has Doctrine entities or repositories, continue that pattern instead of creating parallel persistence code.
- Do not perform core schema mutations ad hoc in Doctrine or API code; route them through `update-patch-creator` so UpdatePatcher logic and install baselines stay aligned.
- Schema changes are allowed when they are beneficial or necessary and a seamless patch-based migration path is defined.
- Avoid hybrid new code that writes via RedBean and reads via Doctrine unless you explicitly frame it as a temporary bridge migration.
- If the task requires core DB structure or seed-content changes, route that part to `update-patch-creator` instead of designing ad-hoc schema mutations here.

## Migration phases

- Phase 1 (reads): move query/list/search code to repositories and Doctrine query builders first.
- Phase 2 (writes): move create/update/delete persistence to entity-manager based flows while preserving service-level behavior, events, and response contracts.
- Phase 3 (integration): refactor dependent call sites in other modules/services so they consume Doctrine entities from migrated methods.
- Phase 4 (cleanup): remove remaining RedBean compatibility code and delete obsolete `Model_*` classes for the migrated module entities.

## Cross-module dependency rule

- When a migrated method changes from RedBean models to Doctrine entities, proactively search and refactor dependent code in other modules.
- Do not stop at local module compilation; update cross-module call sites to the Doctrine contract in the same migration scope whenever feasible.
- Treat lingering external dependencies on removed model behavior as migration debt to be resolved before considering the migration complete.

## Testing expectations for migrations

- Update legacy tests to inject/mock `$di['em']` and the relevant repositories for migrated paths.
- In touched tests, assert migrated methods use Doctrine paths (for example, repository method expectations) and no longer rely on fallback RedBean query methods.
- Keep behavior assertions stable: events fired, response shape unchanged, and exceptions/messages preserved.
- Run affected module tests at minimum, then nearby regression suites where the service is reused.
- Add/adjust tests for dependent modules if their integrations consume migrated methods/entities.

Pagination default example:

```php
public function get_list(array $data): array
{
    /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
    $repo = $this->getService()->getCurrencyRepository();

    $qb = $repo->getSearchQueryBuilder($data);

    return $this->di['pager']->paginateDoctrineQuery($qb);
}
```

## Cross-skill handoff

- This skill owns Doctrine entities, repositories, query design, and API/service migration planning.
- `update-patch-creator` owns core DB structure/content patching via `UpdatePatcher`, including baseline sync for install SQL files.
- When both are needed, produce a coordinated plan: Doctrine-side changes in this skill and patch/baseline changes via `update-patch-creator`.

## Inputs to extract

Collect these from the task or from the codebase before proposing changes:

- target module
- whether this is a new feature, migration, refactor, or narrow bugfix
- whether the task is `list`, `get`, `create`, `update`, or `delete`
- whether pagination is involved
- whether schema evolution is prohibited, optional, or required for this task
- whether a seamless update-patch migration path can be created if schema or seed-content changes are introduced
- target API or service files, if already known

If any input is missing, inspect the relevant module files and infer the minimum needed context.

## Procedure

Progress:
- [ ] Inspect the target module structure
- [ ] Identify existing Doctrine and RedBean usage
- [ ] Identify cross-module dependencies on migrated methods and model contracts
- [ ] Decide if schema/seed changes are required and hand off to `update-patch-creator` when needed
- [ ] Choose the migration mode
- [ ] Produce the file-level implementation plan
- [ ] Validate the plan against FOSSBilling conventions

### 1. Inspect the module structure

Check whether the module already has:

- an `Entity/` directory
- a `Repository/` directory
- API classes under `Api/`
- service logic that already depends on Doctrine or RedBean

Prefer extending established module patterns over introducing new structure.

### 2. Identify current persistence pattern

Choose the current state:

- `new-doctrine`: the task introduces new persistence behavior
- `bridge-migration`: legacy RedBean code can be moved incrementally to Doctrine
- `legacy-touch-only`: a very narrow legacy fix where migration risk is too high for this task

Default to `new-doctrine` for new DB-backed work.

### 3. Decide whether schema or seed-content changes should be part of the solution

- If the current schema blocks correctness, maintainability, or performance, schema/content changes are allowed.
- Only include schema/content changes when they are beneficial or necessary and a seamless migration path can be implemented through `update-patch-creator`.
- If schema/content changes are selected, add explicit handoff scope to `update-patch-creator`.
- If not selected, document why the existing schema is sufficient.

### 4. Produce the implementation plan

Return a concise plan with these sections:

- `Decision`
- `Summary`
- `Files to create`
- `Files to update`
- `Entity plan`
- `Repository plan`
- `API or service plan`
- `Migration notes`
- `Risks`
- `Validation`

Be specific about exact file paths and the smallest useful set of methods to add or change.

### 5. Apply FOSSBilling-specific rules

- For new persisted features, plan Doctrine entities and repositories first.
- For create/write flows, prefer `new Entity(...)` + `$di['em']->persist(...)` + flush over direct insert helpers when entity mapping can represent the write.
- For migrations, preserve behavior where practical; if schema evolution is beneficial or necessary, define the target model here and delegate patch and baseline implementation to `update-patch-creator`.
- For paginated endpoints, plan around a Doctrine query and `$di['pager']->paginateDoctrineQuery()`.
- For paginated API refactors, call `paginateDoctrineQuery($qb)` directly unless there is an explicit need to override default paging behavior.
- For RedBean-to-Doctrine migrations, place search query builder logic in repositories and update references accordingly instead of leaving compatibility aliases in Service classes.
- Keep write operations (`create`, `update`, `delete`) in Service classes; move read/query operations to repositories where appropriate.
- For Doctrine entities with timestamp fields, include lifecycle callbacks so `createdAt`/`updatedAt` are set automatically on persist/update.
- For RedBean-to-Doctrine migrations, refactor method contracts to Doctrine entities and remove reliance on legacy `Model_*` types.
- Move fitting persistence-related methods to repositories whenever behavior can be preserved.
- For read-only migrations, prefer moving the read path first if that materially lowers risk.
- Refactor dependent modules/services that call migrated methods so they work with Doctrine entities.
- Remove obsolete RedBean models for migrated module entities once no call sites require them.

Write-flow orchestration example:

```php
public function createThing(array $data): int
{
    $this->di['events_manager']->fire(['event' => 'onBeforeThingCreate', 'params' => $data]); // If the event already exists

    $thing = new Thing();
    $thing->setName($data['name']);

    $em = $this->di['em'];
    $em->persist($thing);
    $em->flush();

    $this->di['events_manager']->fire(['event' => 'onAfterThingCreate', 'params' => ['id' => $thing->getId()]]); // If the event already exists
    $this->di['logger']->info('Created thing #%s', $thing->getId());

    return (int) $thing->getId();
}
```

Timestamp callback example:

```php
#[ORM\PrePersist]
public function onPrePersist(): void
{
    $now = new \DateTime();
    $this->createdAt = $now;
    $this->updatedAt = $now;
}

#[ORM\PreUpdate]
public function updateTimestamp(): void
{
    $this->updatedAt = new \DateTime();
}
```

### 6. Validate your own plan

Before finalizing, check:

- Does the plan avoid new RedBean usage for new code?
- Does it reuse existing module structure where available?
- Have dependent modules/services been checked and updated for migrated Doctrine method contracts?
- Are legacy Model classes and RedBean-only paths removed for migrated module entities?
- Have repository-appropriate methods been moved to repositories without losing behavior?
- If schema or seed changes are proposed, are they clearly justified as beneficial or necessary and paired with a seamless `update-patch-creator` migration path?
- If schema or seed changes are not proposed, is that decision justified by the task constraints?
- If pagination exists, does it use the Doctrine pager path?
- Does it include the minimum relevant validation steps?

If any answer is no, revise the plan.

## Output template

```text
Decision: <new-doctrine|bridge-migration|legacy-touch-only>

Summary:
<short rationale>

Files to create:
- <path>

Files to update:
- <path>

Entity plan:
- <mapping, fields, or relation note>

Repository plan:
- <method and query note>

API or service plan:
- <method, params, or response-shape note>

Migration notes:
- <compatibility or phased-migration note>

Risks:
- <risk>

Validation:
- <command or verification step>

Schema strategy:
- <no schema change | schema/content change via update-patch-creator; why>

Handoff:
- <none|update-patch-creator with scope>
```

## Example

Input:

```text
Module: Support
Change type: migration
Target files: src/modules/Support/Api/Admin.php
Behavior: Refactor the ticket list endpoint from legacy DB access to Doctrine
Operations: list
Pagination: yes
Compatibility requirement: strict
Schema info: existing ticket table must remain unchanged
```

Output:

```text
Decision: bridge-migration

Summary:
Migrate the ticket list read path to Doctrine without requiring schema changes for this task. Keep the API contract stable and replace legacy pagination with Doctrine pagination.

Files to create:
- src/modules/Support/Entity/Ticket.php
- src/modules/Support/Repository/TicketRepository.php

Files to update:
- src/modules/Support/Api/Admin.php

Entity plan:
- Map a Doctrine entity to the existing ticket table
- Preserve current column names, nullability, and defaults
- Add only the fields required by this endpoint first

Repository plan:
- Add `getTicketListQuery(array $filters)`
- Return a Doctrine QueryBuilder suitable for pager integration

API or service plan:
- Keep endpoint params and response shape unchanged
- Replace legacy fetch logic with repository query results
- Preserve current filter and sort semantics
- Use `$di['pager']->paginateDoctrineQuery()` in the API layer

Migration notes:
- Migrate the read path first and defer write-path migration unless this task also changes writes

Risks:
- Existing filters may rely on legacy SQL behavior
- Default sorting must remain identical to avoid API regressions

Validation:
- Run `./src/vendor/bin/phpstan analyse`
- Run `./src/vendor/bin/php-cs-fixer fix --dry-run --diff`
- Run affected PHPUnit tests
- Verify the paginated response shape manually if no targeted tests exist
```

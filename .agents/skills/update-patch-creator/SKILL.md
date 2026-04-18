---
name: update-patch-creator
description: Create FOSSBilling UpdatePatcher patches for core database structure or data-content changes. Use when altering core schema, backfilling or migrating core DB content, and keeping install baselines aligned. Do not use for third-party modules.
license: Apache-2.0
compatibility: Designed for the FOSSBilling repository and UpdatePatcher workflow.
metadata:
  project: FOSSBilling
  owner: FOSSBilling
---

# UpdatePatcher patch creation

Use this skill when a task changes database structure or database content and needs those changes delivered through `src/library/FOSSBilling/UpdatePatcher.php`.

## Activation triggers

- The task adds, removes, or alters tables, columns, indexes, or constraints.
- The task migrates, backfills, or normalizes existing DB rows.
- The task changes default seeded DB values that new installs should start with.
- The task requires adding a new `patchNN` and keeping install baselines aligned.

## Scope gate (mandatory)

- Activate this skill only for changes that belong to FOSSBilling core database baseline files:
  - `src/install/sql/structure.sql`
  - `src/install/sql/content.sql`
- Do not activate this skill for third-party or external modules that are not part of the core install baseline.
- Third-party modules must manage their own database migrations and seeding within their own module lifecycle.

## Do not activate when

- The requested DB changes are only for external or marketplace modules outside core baselines.
- The task is unrelated to DB structure/content migration.

## Defaults

- Add core patches in `src/library/FOSSBilling/UpdatePatcher.php`.
- Keep each patch focused on one migration concern.
- Make patches safe to retry after partial failures.

## Gotchas

- `applyCorePatches()` runs a patch first and only then updates `last_patch`, so failed patches are retried on the next run.
- Patch map and method name must match exactly, for example `54 => 'patch54'` and `private function patch54(): void`.
- New installs seed `last_patch` from `src/install/sql/content.sql`, so this value must be updated when a new patch is added.
- Schema updates belong in install baseline `src/install/sql/structure.sql`.
- Seed/default data updates belong in install baseline `src/install/sql/content.sql`.
- Patch work is incomplete unless runtime patch logic and install baselines are synchronized.

## Inputs to extract

Collect these from the request and existing code:

- migration intent
- whether changes are schema, data, file-system, or mixed
- affected tables and columns
- whether existing helper methods (`executeSql`, `executeFileActions`, `migrateEncryptedColumn`) can be reused

If inputs are missing, inspect `UpdatePatcher.php` and the install SQL files before proposing edits.

## Procedure

Progress:
- [ ] Confirm task is in core DB scope
- [ ] Determine next patch number
- [ ] Design idempotent patch behavior
- [ ] Add and register `patchNN`
- [ ] Sync install baselines
- [ ] Validate and report risk

### 1. Confirm core DB scope

- Verify the requested change affects core schema or seed data represented by `src/install/sql/structure.sql` and `src/install/sql/content.sql`.
- If the change is for a third-party module, stop and route to module-specific migration/seeding logic instead of `UpdatePatcher`.

### 2. Determine next patch number

- Read `getPatches()` and choose the next integer key.
- Ensure the key is unique and has a matching `patchNN` method.

### 3. Design idempotent behavior

- For schema changes, use existence checks where possible (`IF EXISTS`, `IF NOT EXISTS`, or DBAL schema introspection).
- For data migrations, target only rows that actually need migration.
- For file changes, operate only on existing paths.

### 4. Add and register patch

- Add `private function patchNN(): void` in `UpdatePatcher.php`.
- Keep the patch concise with a short intent comment.
- Register `NN => 'patchNN'` in `getPatches()`.
- Keep numeric ordering intact.

### 5. Sync install baselines (mandatory)

For every new `patchNN`:

1. Mirror DB schema effects in `src/install/sql/structure.sql`.
2. Mirror seed/default data effects in `src/install/sql/content.sql`.
3. Update `setting.last_patch` in `src/install/sql/content.sql` to `NN`.

Rationale: fresh installs must start in the same effective state as upgraded installs.

### 6. Validation loop

1. Re-check map/method parity (`getPatches()` entry and `patchNN` method).
2. Verify baseline parity:
   - `src/install/sql/structure.sql` includes schema outcomes of `patchNN`
   - `src/install/sql/content.sql` includes seed/default data outcomes of `patchNN`
   - `last_patch` in `src/install/sql/content.sql` equals `NN`
3. Run relevant checks.
4. If any check fails, revise and revalidate.

## Output template

```text
Decision: patch-added

Patch number:
<NN>

Files to update:
- src/library/FOSSBilling/UpdatePatcher.php
- src/install/sql/structure.sql
- src/install/sql/content.sql

Patch intent:
- <what changed>
- <why>

Idempotency strategy:
- <guards used>

Validation:
- <commands and expected results>

Risk notes:
- <operational caveats>
```

## Example

Input:

```text
Add a patch that introduces a new nullable column and backfills only missing rows.
```

Output:

```text
Decision: patch-added

Patch number:
54

Files to update:
- src/library/FOSSBilling/UpdatePatcher.php
- src/install/sql/structure.sql
- src/install/sql/content.sql

Patch intent:
- Add the new nullable column needed by the feature
- Backfill only rows that currently have no value

Idempotency strategy:
- Check column existence before schema change
- Restrict backfill update to NULL rows

Validation:
- Confirm `54 => 'patch54'` exists and `patch54()` is implemented
- Confirm structure and content baselines include equivalent changes
- Confirm `last_patch` in content baseline is `54`
- Run `./src/vendor/bin/phpstan analyse`
- Run `./src/vendor/bin/php-cs-fixer fix --dry-run --diff`

Risk notes:
- Large backfills may lock tables if not scoped carefully
```

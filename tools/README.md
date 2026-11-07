# FOSSBilling tools

Development and CI tooling, packaged as a single Symfony Console application. Requires PHP 8.5. Install the dependencies:

```bash
composer install --working-dir=tools
```

Use `--no-dev` when tests aren't needed. All tools are dev-facing only: none of this directory ships in the release artifact. The tools inspect source without executing PHP files, rendering templates, or loading the application.

## Commands

Run everything through the shared console:

```bash
tools/bin/console list
```

| Command | Purpose |
| --- | --- |
| `i18n:extract` | Extract a combined core and bundled-module translation template |
| `i18n:extract-modules` | Extract one translation template per module |
| `i18n:diff` | Report message, plural and casing changes between templates |
| `i18n:validate` | Validate module PO files with GNU gettext and project-specific checks |
| `i18n:compile` | Compile validated module translations into a staging directory |
| `manifest:validate` | Validate bundled module `manifest.json` files |
| `demo:seed` | Seed a FOSSBilling instance with demo data over the admin API |

## Translation tooling

Validation and compilation also require GNU gettext (`msgfmt`, `msgcat`, and `msgattrib` on `PATH`).

```bash
tools/bin/console i18n:extract src /tmp/messages.pot
tools/bin/console i18n:extract src /tmp/messages.pot --check
tools/bin/console i18n:diff old.pot new.pot
tools/bin/console i18n:diff old.pot new.pot --format=json
tools/bin/console i18n:extract-modules /path/to/modules /path/to/core/messages.pot /tmp/templates
tools/bin/console i18n:validate /path/to/modules /tmp/templates
tools/bin/console i18n:compile /path/to/modules /tmp/templates /tmp/compiled
```

`i18n:extract` produces the combined core and bundled-module template used by `FOSSBilling/locale`. `i18n:extract-modules` accepts a modules checkout or single module directory and writes `<Module>/locale/<id>.pot` beneath its output directory. Each module needs a v1 manifest and a directory named `Ucfirst(id)`. The core POT must match the module's minimum core version; shared references must match its message and plural pair. Cross-module references require the target module in the input checkout.

Both extraction commands support `--check`, which reports stale templates without writing and ignores creation dates. `i18n:diff` reports added, removed, plural, and casing changes. `--format=json` returns a `changed` flag, counts (`added`, `removed`, `gone`, `casing_only`, `plural_changed`), and the text `report`; dates, references, and entry order do not count as changes. `gone` excludes casing-only replacements. Generation validates all sources before writing, leaves unchanged module templates alone, and rejects conflicting plurals or orphaned templates.

Extraction follows the [translation source rules](../CONTRIBUTING.md#translation-lookup-in-code). PHP messages default to core; module-owned messages need an explicit literal `from` at the message call site. Constructor defaults and values forwarded through constructor bodies are not inferred. Dynamic messages are skipped. Invalid Twig syntax, unknown tags, and ambiguous translation arguments fail extraction. Unknown filter, function, and test names are accepted as inert parsing declarations; custom tags need explicit registration.

### Module translations

PO files live at `<Module>/locale/<locale>/LC_MESSAGES/<id>.po`, for example `Example/locale/fr_FR/LC_MESSAGES/example.po`. Compilation writes the same layout with `.mo` files beneath a new or empty staging directory, after every input passes validation.

Validation runs `msgfmt --check --check-format` and checks template membership, plural slots, filenames, and `:name` placeholders. Missing entries are allowed for core fallback; stale entries and message contexts are rejected. Retain gettext format flags for printf-style validation.

Fuzzy entries are filtered before normalization, and empty translations are omitted. Non-fuzzy plural entries must translate every required form or leave all forms empty. Intentional translations identical to English are preserved. Compilation uses normalized UTF-8 input, including with older gettext versions.

## Manifest validation

```bash
tools/bin/console manifest:validate
tools/bin/console manifest:validate /path/to/modules
```

Validates every module directory beneath `src/modules` (or the given checkout) against [the manifest JSON schema](resources/manifest.schema.json) plus project-specific checks the schema cannot express: type, id/directory match, http(s) URLs, single package icon, semver version without a `v` prefix, composer/semver constraint syntax, no self-references, and valid SPDX licenses. Exits nonzero when any module fails.

Keep the copy in the [FOSSBilling/modules](https://github.com/FOSSBilling/modules) repository in sync with this validator.

## Demo seed

Repeatable, additive test-data seeder for a FOSSBilling instance over the admin API. All records use a `Demo` prefix so they can be found and removed with the `batch_delete` endpoints.

```bash
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... tools/bin/console demo:seed
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... tools/bin/console demo:seed --dry-run
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... tools/bin/console demo:seed --limit-clients=5 --limit-orders=5 --limit-tickets=5
```

The admin API key must be provided through `TEST_API_KEY`; command-line arguments can be visible to other users on the system.

Writes `manifest.json` (or `manifest.dry.json`) beneath `tools/var/demo-seed/` with every created ID plus the shared test passwords (`DemoPass123!` for clients and demo staff). Pass `--manifest=/some/dir` to change the output directory.

### Export / reuse

```bash
ddev export-db --file=.ddev/fossbilling-demo-<date>.sql.gz
ddev import-db --file=.ddev/fossbilling-demo-<date>.sql.gz
```

The SQL dump is the portable snapshot; this command is the repeatable recipe. Re-running skips existing foundation/catalog records (emails/titles/codes), but appends new orders, invoices and tickets each time.

### Safety

- Additive only; never wipes.
- `send_welcome_email=0` for clients.
- Only offline-safe gateways (Custom, ClientBalance) are enabled.
- Dummy hosting server uses TEST-NET `192.0.2.10` + Custom manager.

Sample files for the downloadable demo product live in [resources/demo-seed](resources/demo-seed).

## Composer hooks

`composer/sync-rector-phpdoc-parser.php` is wired into the root `composer.json` post-install/post-update hooks and is deliberately dependency-free so it can run before any vendor directory exists. It is root-application plumbing, not part of this console application.

## Tests

```bash
composer test --working-dir=tools
```

The Pest suite covers extraction, CLI behavior, module templates, gettext builds, and manifest validation. It inspects raw MO bytes and exercises fuzzy plurals and older gettext encoding behavior. Tests use temporary directories. The demo seeder is network-bound and has no tests.

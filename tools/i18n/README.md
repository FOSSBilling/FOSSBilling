# Translation tooling

Requires PHP 8.5 with tokenizer and mbstring. Validation and compilation also require GNU gettext (`msgfmt`, `msgcat`, and `msgattrib` on `PATH`). Install the tooling dependencies:

```bash
composer install --working-dir=tools/i18n
```

Use `--no-dev` when tests aren't needed. The tools inspect source without executing PHP files, rendering templates, or loading the application.

## Commands

```bash
tools/i18n/bin/console extract src /tmp/messages.pot
tools/i18n/bin/console extract src /tmp/messages.pot --check
tools/i18n/bin/console diff old.pot new.pot
tools/i18n/bin/console diff old.pot new.pot --format=json
tools/i18n/bin/console extract-modules /path/to/modules /path/to/core/messages.pot /tmp/templates
tools/i18n/bin/console validate /path/to/modules /tmp/templates
tools/i18n/bin/console compile /path/to/modules /tmp/templates /tmp/compiled
```

`extract` produces the combined core and bundled-module template used by `FOSSBilling/locale`. `extract-modules` accepts a modules checkout or single module directory and writes `<Module>/locale/<id>.pot` beneath its output directory. Each module needs a v1 manifest and a directory named `Ucfirst(id)`. The core POT must match the module's minimum core version; shared references must match its message and plural pair. Cross-module references require the target module in the input checkout.

Both extraction commands support `--check`, which reports stale templates without writing and ignores creation dates. `diff` reports added, removed, plural, and casing changes. `--format=json` returns a `changed` flag, counts (`added`, `removed`, `gone`, `casing_only`, `plural_changed`), and the text `report`; dates, references, and entry order do not count as changes. `gone` excludes casing-only replacements. Generation validates all sources before writing, leaves unchanged module templates alone, and rejects conflicting plurals or orphaned templates.

Extraction follows the [translation source rules](../../CONTRIBUTING.md#translation-lookup-in-code). PHP messages default to core; module-owned messages need an explicit literal `from` at the message call site. Constructor defaults and values forwarded through constructor bodies are not inferred. Dynamic messages are skipped. Invalid Twig syntax, unknown tags, and ambiguous translation arguments fail extraction. Unknown filter, function, and test names are accepted as inert parsing declarations; custom tags need explicit registration.

## Module translations

PO files live at `<Module>/locale/<locale>/LC_MESSAGES/<id>.po`, for example `Example/locale/fr_FR/LC_MESSAGES/example.po`. Compilation writes the same layout with `.mo` files beneath a new or empty staging directory, after every input passes validation.

Validation runs `msgfmt --check --check-format` and checks template membership, plural slots, filenames, and `:name` placeholders. Missing entries are allowed for core fallback; stale entries and message contexts are rejected. Retain gettext format flags for printf-style validation.

Fuzzy entries are filtered before normalization, and empty translations are omitted. Non-fuzzy plural entries must translate every required form or leave all forms empty. Intentional translations identical to English are preserved. Compilation uses normalized UTF-8 input, including with older gettext versions.

## Migration preparation

For the two-project Crowdin drafts and offline Branding/Cookieconsent translation seeds, see [migration preparation](migrations/branding-cookieconsent/README.md).

## Tests

```bash
composer test --working-dir=tools/i18n
```

The Pest suite covers extraction, CLI behavior, module templates, and gettext builds. It inspects raw MO bytes and exercises fuzzy plurals and older gettext encoding behavior. Tests use temporary directories.

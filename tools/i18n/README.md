# Translation tooling

<!-- cspell:words msgfmt msgcat msgattrib Ucfirst mktemp tokenizer Pest -->

The tools require PHP 8.3+ with the tokenizer and mbstring extensions. Install the standalone tooling dependencies from the FOSSBilling checkout:

```bash
composer install --working-dir=tools/i18n
```

Extraction uses PHP's tokenizer and Twig's parser without executing source files, rendering templates, connecting to a database, or bootstrapping FOSSBilling. All callers use the tooling package's Composer dependencies. Use `--no-dev` when installing only for extraction or translation builds.

Module translation validation and compilation also require GNU gettext (`msgfmt`, `msgcat`, and `msgattrib` on `PATH`).

## Combined core template

The combined template includes application and bundled-module strings for the `FOSSBilling/locale` workflow:

```bash
php .github/scripts/extract_pot.php src /tmp/messages.pot
php .github/scripts/extract_pot.php src /tmp/messages.pot --check
php .github/scripts/extract_pot.php --diff old.pot new.pot
```

`--check` detects changed messages, plural pairs, and references without writing files; it ignores creation dates. `--diff` reports message and plural changes, including casing changes that invalidate existing translations.

Twig syntax errors and unregistered extension tags fail extraction with diagnostics, leaving existing templates untouched. Supported tags are Twig's built-in tags; custom tags need explicit registration in the extraction environment. Unknown filter, function, and test names are accepted as inert parsing declarations. Translation source arguments must be constant IDs or null; duplicate, ambiguous, and unpacked translation arguments are rejected.

## Module templates

`extract_pot.php --modules` uses the [translation source rules](../../CONTRIBUTING.md#translation-lookup-in-code). Each module needs a v1 manifest and a directory named `Ucfirst(id)`. PHP extraction assumes the standard core default; module-owned PHP messages need a literal `from` argument. Dynamic messages are skipped, and ambiguous or invalid sources fail extraction.

Pass a core POT snapshot from a pinned revision compatible with the module's minimum core version. Shared core references must match its message and plural pair. Cross-module references require the target module in the input checkout; identical wording can belong to more than one module.

```bash
CORE_TOOLS="/path/to/pinned/FOSSBilling/.github/scripts"
MODULES_ROOT="/path/to/modules"
CORE_POT="/path/to/pinned/locale/messages.pot"
TRANSLATION_STAGING="$(mktemp -d)"

php "$CORE_TOOLS/extract_pot.php" --modules "$MODULES_ROOT" \
  --core-pot "$CORE_POT" --output "$TRANSLATION_STAGING/templates"

php "$CORE_TOOLS/extract_pot.php" --modules "$MODULES_ROOT" \
  --core-pot "$CORE_POT" --output "$TRANSLATION_STAGING/templates" --check
```

A single module directory is also accepted. Templates are written to `templates/<Module>/locale/<id>.pot`. Generation validates all sources before writing, leaves unchanged templates alone, and rejects conflicting plurals or orphaned templates. Use the modules checkout as `--output` to update committed templates, or add `--check` to verify them without writing.

## Validate and compile

PO inputs live at `<Module>/locale/<locale>/LC_MESSAGES/<id>.po`, for example `Example/locale/fr_FR/LC_MESSAGES/example.po`.

```bash
php "$CORE_TOOLS/module_translations.php" validate "$MODULES_ROOT" \
  --templates "$TRANSLATION_STAGING/templates"

php "$CORE_TOOLS/module_translations.php" compile "$MODULES_ROOT" \
  --templates "$TRANSLATION_STAGING/templates" \
  --output "$TRANSLATION_STAGING/compiled"
```

`module_translations.php` runs `msgfmt --check --check-format` and uses `msgcat` to normalize PO syntax and encoding for the FOSSBilling-specific checks: template membership, plural slots, filenames, and `:name` placeholders. Missing entries are allowed for core fallback; stale entries and message contexts are rejected.

For printf-style validation, retain the appropriate gettext format flag in each PO entry.

Compilation uses GNU `msgfmt` and writes `compiled/<Module>/locale/<locale>/LC_MESSAGES/<id>.mo` after every input passes validation. The output directory must be new or empty. Fuzzy translations are filtered with `msgattrib` before normalization, while their source messages still undergo template and context checks. Empty translations are omitted; translations identical to English remain valid. Compilation reads the normalized UTF-8 catalogue, so output stays UTF-8 with older gettext versions as well.

Non-fuzzy plural entries must either translate every form required by `nplurals` or leave all forms empty for core fallback. Partially translated entries are rejected, including when the first form is empty and `msgfmt` would silently omit the entry. Plural expressions are validated by gettext and evaluated by MoTranslator.

## Tests

With the standalone Composer dependencies and GNU gettext installed:

```bash
composer test --working-dir=tools/i18n
```

The Pest suite covers extraction fixtures, CLI and module templates, and gettext compilation tests that inspect raw MO bytes. It covers first-empty fuzzy plurals and emulates older gettext encoding behavior to verify UTF-8 output. Tests use temporary directories and never alter application translations.

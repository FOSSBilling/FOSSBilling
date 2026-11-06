# Translation tooling

<!-- cspell:words msgfmt Ucfirst mktemp polib venv -->

The tools in `.github/scripts/` require Python 3.10+. For module translations, install GNU gettext (`msgfmt` on `PATH`) and the pinned Python dependency:

```bash
python3 -m venv /tmp/fossbilling-i18n-venv
. /tmp/fossbilling-i18n-venv/bin/activate
python3 -m pip install --require-hashes -r .github/scripts/requirements-i18n.txt
```

## Combined core template

The existing command still includes application and bundled-module strings in one template, as required by the current `FOSSBilling/locale` workflow:

```bash
python3 .github/scripts/extract_pot.py src /tmp/messages.pot
python3 .github/scripts/extract_pot.py src /tmp/messages.pot --check
python3 .github/scripts/extract_pot.py --diff old.pot new.pot
```

`--check` detects changed messages, plural pairs, and references without writing files; it ignores creation dates. `--diff` reports message and plural changes, including casing changes that invalidate existing translations.

## Module templates

`module_translations.py` uses the [translation source rules](../../CONTRIBUTING.md#translation-lookup-in-code). Each module needs a v1 manifest and a directory named `Ucfirst(id)`. PHP extraction assumes the standard core default; module-owned PHP messages need a literal `from` argument. Dynamic messages are skipped, and ambiguous or invalid sources fail extraction.

Pass a core POT snapshot from a pinned revision compatible with the module's minimum core version. Shared core references must match its message and plural pair. Cross-module references require the target module in the input checkout; identical wording can belong to more than one module.

```bash
CORE_TOOLS="/path/to/pinned/FOSSBilling/.github/scripts"
MODULES_ROOT="/path/to/modules"
CORE_POT="/path/to/pinned/locale/messages.pot"
TRANSLATION_STAGING="$(mktemp -d)"

python3 "$CORE_TOOLS/module_translations.py" extract "$MODULES_ROOT" \
  --core-pot "$CORE_POT" --output "$TRANSLATION_STAGING/templates"

python3 "$CORE_TOOLS/module_translations.py" extract "$MODULES_ROOT" \
  --core-pot "$CORE_POT" --output "$TRANSLATION_STAGING/templates" --check
```

A single module directory is also accepted. Templates are written to `templates/<Module>/locale/<id>.pot`. Generation validates all sources before writing, leaves unchanged templates alone, and rejects conflicting plurals or orphaned templates. Use the modules checkout as `--output` to update committed templates, or add `--check` to verify them without writing.

## Validate and compile

PO inputs live at `<Module>/locale/<locale>/LC_MESSAGES/<id>.po`, for example `Example/locale/fr_FR/LC_MESSAGES/example.po`.

```bash
python3 "$CORE_TOOLS/module_translations.py" validate "$MODULES_ROOT" \
  --templates "$TRANSLATION_STAGING/templates"

python3 "$CORE_TOOLS/module_translations.py" compile "$MODULES_ROOT" \
  --templates "$TRANSLATION_STAGING/templates" \
  --output "$TRANSLATION_STAGING/compiled"
```

Validation runs `msgfmt --check --check-format` and checks template membership, plural slots, filenames, and `:name` placeholders. Missing entries are allowed for core fallback; stale entries and message contexts are rejected.

Compilation writes `compiled/<Module>/locale/<locale>/LC_MESSAGES/<id>.mo` after every input passes validation. The output directory must be new or empty. Fuzzy and empty translations are omitted; translations identical to English remain valid.

polib writes the MO files because `msgfmt` omits a whole plural entry when its first form is empty. This preserves usable later forms for runtime fallback. The tool also checks plural slot counts, which `msgfmt` skips in that case. Plural expressions are validated by gettext and evaluated by MoTranslator.

## Tests

With the dependencies above installed:

```bash
python3 .github/scripts/tests/test_extract_pot.py
python3 -m unittest discover -s .github/scripts/tests -p 'test_*.py' -v
```

Tests use temporary modules and language files, and read compiled output with Python's gettext implementation.

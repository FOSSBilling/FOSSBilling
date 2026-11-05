# Translation tooling

<!-- cspell:words msgfmt Ucfirst mktemp polib venv -->

The shared tools live in `.github/scripts/` and require Python 3.10 or newer. Module tooling also requires GNU gettext's `msgfmt` on `PATH` and the pinned Python dependency:

```bash
python3 -m venv /tmp/fossbilling-i18n-venv
. /tmp/fossbilling-i18n-venv/bin/activate
python3 -m pip install --require-hashes -r .github/scripts/requirements-i18n.txt
```

## Existing combined template

The existing command remains compatible with the current `FOSSBilling/locale` workflow:

```bash
python3 .github/scripts/extract_pot.py src /tmp/messages.pot
python3 .github/scripts/extract_pot.py src /tmp/messages.pot --check
python3 .github/scripts/extract_pot.py --diff old.pot new.pot
```

It still includes all application and bundled-module strings in one template. Source-aware extraction does not switch the existing locale pipeline to separate module templates. That switch belongs to the later Crowdin/module migration.

`--check` is read-only. It ignores creation-date changes but detects changed messages, plural pairs, and source references. `--diff` reports added/removed messages, casing-only changes, and changed plural forms.

## Translation ownership

Extraction follows the runtime lookup rules:

| Call | First source |
| --- | --- |
| Twig `trans` inside a module directory | The module inferred from the file path |
| Twig `trans` elsewhere | Core |
| PHP helpers or translated exceptions without `from` | Core |
| An explicit literal `from` | The named source |
| Explicit `from: 'core'` or `from: null` | Core |

PHP extraction assumes the standard core default; it cannot infer a custom default selected by an integration at runtime.

The extractor understands named/reordered and positional arguments, nested placeholder expressions, PHP plural calls, and the shared/adapter exception constructors. Dynamic message text is skipped as before. A dynamic, invalid, duplicated, or unpacked source argument produces an error instead of guessing ownership. Use literal lowercase module IDs for extractable module messages.

Each source retains its own entry even if another module or core uses identical wording. Cross-module overrides are routed to the requested owner when extracting a complete modules repository; the target module must be present. Unknown source IDs fail extraction.

For debugging a standalone module, the lower-level extractor accepts an explicit module context and source filter:

```bash
python3 .github/scripts/extract_pot.py /path/to/Example /tmp/example.pot \
  --module-id example --source example
```

The `--source` filter selects the first lookup source, not its fallback sources. Do not replace the combined core command with `--source core` during the transition: bundled module templates still depend on their translations being included in the core language packs.

## Generate module templates

Use `module_translations.py` against a modules checkout with v1 manifests and directories named `Ucfirst(id)`. Pass a core POT snapshot from a pinned revision appropriate for the supported application version. PHP calls and explicit core references must match a singular/plural entry in that snapshot; new module wording must use the module's `from` argument.

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

The generated layout is:

```text
templates/
  Example/locale/example.pot
  Other/locale/other.pot
```

Generation validates all sources and plural pairs before writing templates. Unchanged files keep their existing dates. Conflicting plurals and orphaned templates fail with diagnostics; removals require review. The input modules stay unchanged unless their repository is explicitly selected as the output directory. A single module directory is also accepted, with the same output layout.

For a future modules-repository check, set `--output` to that checkout and use `--check` to verify committed templates. Pin the shared tooling checkout as well as the core POT input; do not download an unpinned extractor during release builds. The current combined core template still contains optional-module strings awaiting migration, so its presence checks cannot replace the migration's ownership audit.

## Validate and compile translations

Translation inputs belong to each module:

```text
Example/
  locale/
    example.pot
    fr_FR/LC_MESSAGES/example.po
```

Validate against generated templates, without writing to either checkout:

```bash
python3 "$CORE_TOOLS/module_translations.py" validate "$MODULES_ROOT" \
  --templates "$TRANSLATION_STAGING/templates"
```

Validation checks PO syntax and gettext format flags with `msgfmt --check --check-format`, then checks template membership, plural pairs, filenames, and FOSSBilling's `:name` placeholders. Wrapped PO strings are supported. Message contexts are rejected because the current module runtime does not support them. Missing translation entries are allowed for runtime fallback; stale active entries must be merged with the current template.

Compile into a new or empty staging directory:

```bash
python3 "$CORE_TOOLS/module_translations.py" compile "$MODULES_ROOT" \
  --templates "$TRANSLATION_STAGING/templates" \
  --output "$TRANSLATION_STAGING/compiled"
```

This produces `compiled/Example/locale/fr_FR/LC_MESSAGES/example.mo`. Fuzzy translations are omitted, and language files containing no usable translations are not shipped. An intentional translation identical to English remains valid. All inputs are validated before compiled output is copied, and a nonempty output directory is rejected to avoid mixing old and new build artifacts.

Compilation produces only translation files. Module packaging and release workflows will be handled separately.

## Plural translations

MoTranslator selects the plural form using each translation file's language rules. FOSSBilling checks whether the plural entry exists and whether the selected translation is nonempty, then falls back to core and finally English. A valid entry may have empty forms: translated forms remain usable and empty forms fall back individually. An intentional English translation remains usable too.

Build-time validation runs `msgfmt --check --check-format` and verifies that every usable plural entry has all declared form slots. Invalid plural expressions, division by zero, and out-of-range indexes fail the build. After validation, polib writes the MO files, preserving partially translated plural entries even when their first form is empty; `msgfmt` would omit those entries. Runtime handling of malformed plural data supplied outside the validated build process is left to the library.

## Tests

```bash
python3 .github/scripts/tests/test_extract_pot.py
python3 -m unittest discover -s .github/scripts/tests -p 'test_*.py' -v
```

The fixtures use temporary modules and language files. Compilation tests read the resulting MO files through Python's gettext implementation. Compilation tests require `msgfmt`; CI installs gettext so they run there.

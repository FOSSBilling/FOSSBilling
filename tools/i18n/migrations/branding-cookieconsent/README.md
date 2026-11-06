# Branding and Cookieconsent translation migration

<!-- cspell:words msgfmt polib -->

This prepares the two-project Crowdin configuration, translation seeds, and a candidate Cookieconsent patch for review. Application modules and live Crowdin settings stay unchanged. The configuration files use `.example.yml` names and no synchronization job reads them.

## Prepare the bundle

Use the [translation tooling dependencies](../../README.md): Python 3.10+, polib, and GNU gettext. `profile.json` pins `FOSSBilling/locale` to `0f00d645373d9546d741c337d9f9e75fdd5bc6a6`. Supply a clean checkout of that revision with its PO files, `messages.pot`, and licence files:

```bash
python3 .github/scripts/prepare_translation_migration.py \
  --app-root . \
  --locale-root /path/to/pinned/locale \
  --output tools/i18n/migrations/branding-cookieconsent/prepared
```

The output must be a new or empty directory outside `src/` and the locale input. Git inputs must match the pin and have no modified, untracked, or ignored translation inputs. Exported snapshots are accepted with their revision marked unverified. The report records input SHA-256 hashes in either case.

The generator applies the profile's three source edits to temporary copies. Each edit must match exactly once; source drift fails preparation. All seeded translations must validate before output is written.

## Review the output

[PREVIEW.md](PREVIEW.md) records the result against the pinned inputs. Regenerate before activation to include subsequent translator work and application changes.

| Output | Purpose |
| --- | --- |
| `REPORT.md`, `report.json` | Summary, per-entry translation status, language IDs, input hashes, and pending decisions |
| `modules/` | Candidate module copies with POT/PO files and translation attribution |
| `module-source.patch` | Three Cookieconsent edits, ready for separate source review |
| `crowdin-import/` | Import copies with fuzzy translations blanked |
| `compiled/` | Validated MO files for languages with usable translations |
| `core/messages.pot` | Candidate core template **for after module removal** |
| `core/crowdin.example.yml`, `modules/crowdin.example.yml` | Inactive configurations for Core and Modules |
| `crowdin-project-settings.json` | Proposed project names, language inventory, and translation memory settings |

Generated bundles are ignored by Git. They contain full module copies for review; these copies are not moved into another repository or released.

The Cookieconsent patch gives its PHP navigation label an explicit module source and translates the default notice in the service and admin textarea. Stored custom notices, including stored English text, remain supplied content. Branding uses Twig's inferred module source.

## Translation preservation

Seeds copy active, context-free entries only when both the message and plural text match exactly. Casing changes, obsolete entries, and changed plural pairs are never guessed. Missing entries remain empty and are reported.

Translator comments, flags, translated text, author/team metadata, revision dates, and plural rules are retained. References and module metadata are updated; donor Crowdin IDs are replaced with a pinned revision marker. Source English (`en_US`) is excluded as a target; regional English targets and donor Crowdin language IDs remain in the inventory.

Fuzzy work stays in the module copies for review, is blanked in import copies, and is omitted from compiled output. Intentional translations identical to English are retained. The copied `LICENSE.translations`, `LICENSE.md`, and `TRANSLATION_ATTRIBUTION.md` accompany the candidate modules; code remains Apache-2.0 and translations CC BY 4.0.

PO files do not carry Crowdin approval state, votes, or complete translation history. Imported seeds need independent review before approval. Keep the original project and its history available for attribution and older releases.

## Local checks

```bash
python3 -m unittest discover -s .github/scripts/tests -p 'test_*.py' -v

git apply --check --directory=src/modules \
  tools/i18n/migrations/branding-cookieconsent/prepared/module-source.patch
```

Both generated configuration drafts can be checked with `crowdin config lint` using dummy credentials and an empty identity file. Supply a dummy `CROWDIN_MODULES_PROJECT_ID`; its real value is deliberately unset. Avoid `crowdin config sources` for offline checks because it performs a project lookup.

## Later activation

1. Refresh the locale snapshot and regenerate. Review the source patch, translation seeds, attribution, language IDs, and core removal list.
2. Select compatible module versions and a minimum released core version containing the runtime support. Candidate manifests retain their current versions and requirements during preparation.
3. Keep existing Crowdin project **4**, its file/branch identity, and translation URL. Rename only its display name to **FOSSBilling Core**. Create a separate file-based **FOSSBilling Modules** project, reconcile source/target languages, assign shared translation memory and terminology resources, and grant translators access.
4. Install the Modules configuration with its own project ID and token. Keep automatic imports and synchronization disabled during the one-time seed import. Allow intentional source-equal translations; leave imports unapproved unless approval evidence is separately verified.
5. Export to staging, validate/compile, compare against the seed inventory, and test runtime fallback and custom cookie notices. Then review package publication and module removal as separate changes.
6. After compatible packages and exports are verified, remove the application modules and regenerate the core template. Compare its delta with the reviewed removal list before uploading it. Enable normal synchronization only after verification.

Until that final cleanup, rollback consists of retaining the combined core translations and withholding activation.

Configuration syntax follows [Crowdin's configuration documentation](https://support.crowdin.com/developer/configuration-file/). Review [translation import options and results](https://support.crowdin.com/uploading-translations/) before a live import.

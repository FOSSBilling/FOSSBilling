# Migration preview

<!-- cspell:words undismissed licences -->

Prepared offline. No source checkout or live Crowdin project was modified.

Locale snapshot: [0f00d645373d9546d741c337d9f9e75fdd5bc6a6](https://github.com/FOSSBilling/locale/tree/0f00d645373d9546d741c337d9f9e75fdd5bc6a6).

Locale revision: verified Git snapshot. Target locale folders: 42; source English is not imported as a target.

Application source SHA-256: `39dd6c25bd4104f5e02c22ce6aec9cb0c90f411997f199c7c474e7cd5c693911`.

| Module | Source entries | Locales with usable seeded translations |
| --- | ---: | ---: |
| branding | 2 | 25 |
| cookieconsent | 11 | 35 |

## Review artifacts

- `modules/`: candidate module copies with templates, seeded PO files, and translation licences.
- `module-source.patch`: staged Cookieconsent source changes; the app checkout is unchanged.
- `crowdin-import/`: import copies with fuzzy work excluded, including intentional source-equal translations.
- `compiled/`: validated MO files; no percentage-based language cutoff.
- `core/messages.pot`: candidate core template for after module removal; do not upload it now.
- `crowdin-project-settings.json`: proposed project settings, including donor language IDs.
- `report.json`: per-entry migration disposition and input SHA-256 hashes.

## Candidate core cleanup

Core entries: 3906 before, 3901 after.

Only strings with no remaining application source reference are removed:

- "Cookie Consent"
- "Cookie Consent Settings"
- "Edit Cookie Consent Message Text"
- "If there is a need to change the way notification looks or is being displayed, you can do it by editing"
- "Specify a message which will be shown to the user. The message will be shown until dismissed with the <strong>Close &times;</strong> button. An undismissed message does not effect functionality or prevent users from performing any actions."

Shared strings retained in core:

- "Add Note or Todo Task"
- "Close"
- "Configuration Updated"
- "FOSSBilling"
- "Information:"
- "Powered by"
- "Update"

## Decisions before activation

- Create the Modules Crowdin project and supply its project ID; existing project 4 is not reused.
- Set module version and requires.fossbilling to the first released core version supporting this runtime.
- Verify source/target languages and shared translation-memory assignments in Crowdin before importing.
- PO seeds do not carry Crowdin approvals, votes, or review history; retain the original project as provenance.
- Do not apply the candidate core template until module packages and the new project exports are verified.

Exact msgid AND plural matches are copied. Missing, differently cased, contextual, obsolete, or changed-plural entries are not guessed. Fuzzy work stays in module seeds for review but is blank in the import copies and omitted from compiled translations. Stored administrator-written cookie notices are unchanged.

# Crowdin configuration drafts

These configurations are inactive examples for two Crowdin projects. No workflow reads them, and merging them does not change live Crowdin settings.

| Project | Repository | Configuration |
| --- | --- | --- |
| FOSSBilling Core | `FOSSBilling/locale` | [crowdin-core.example.yml](crowdin-core.example.yml) |
| FOSSBilling Modules | `FOSSBilling/modules` | [crowdin-modules.example.yml](crowdin-modules.example.yml) |

Core retains existing project **4**, its file identity and translation history. Only its display name would change. Modules needs a separate project; its ID and token are supplied through `CROWDIN_MODULES_PROJECT_ID` and `CROWDIN_MODULES_PERSONAL_TOKEN`.

The Modules draft starts with Branding and Cookieconsent. Each module has its own source template and translation files:

```text
Branding/locale/branding.pot
Branding/locale/<locale>/LC_MESSAGES/branding.po
Cookieconsent/locale/cookieconsent.pot
Cookieconsent/locale/<locale>/LC_MESSAGES/cookieconsent.po
```

## Manual migration

Carry out the one-time migration directly when the module move is ready:

1. Generate the module templates using the existing [translation tooling](../README.md). Give module-owned PHP strings an explicit `from`; Twig infers the module from its path. Make Cookieconsent's default notice translatable while preserving stored custom notices.
2. Copy exact matching translations from a reviewed locale snapshot, preserving translator metadata, comments, fuzzy flags and licence attribution. Leave fuzzy work out of imports. PO files do not carry Crowdin approvals or full history; keep the existing project available and review imported translations before approval.
3. Create the Modules project and review language mappings, translator access and shared translation memory. Install the configurations in their respective repositories when approved.
4. Import the translations, allowing intentional translations identical to English. Export to staging and validate, compile and test the module translations with core fallback before enabling synchronization.
5. After compatible module packages are available and the modules have been removed from core, regenerate the core template and review its changes. Shared strings must remain in core.

Module moves, release versions, minimum core requirements and workflow activation need separate review. Keep the combined core translations until the migration is complete.

Configuration syntax follows [Crowdin's configuration documentation](https://support.crowdin.com/developer/configuration-file/). Review the [translation import options](https://support.crowdin.com/uploading-translations/) before importing.

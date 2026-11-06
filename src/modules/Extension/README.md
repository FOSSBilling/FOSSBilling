# Directory packages

The installer accepts existing external releases and directory-built package
releases. A managed release carries an `artifact_id` and a lowercase SHA-256
checksum. Its ZIP is downloaded through the directory API, never directly from
private package storage.

## Installation

The directory's explicit `is_latest` selection wins. Without a selection, the
newest stable version is preferred, falling back to a prerelease. Compatibility
checks use the selected release's FOSSBilling and optional PHP constraints.
An omitted PHP constraint leaves the FOSSBilling runtime requirements in force.

Downloads are limited to 17 MiB and checked against any supplied SHA-256 before
extraction. Managed packages require a checksum, and their root manifest must
match the selected extension ID, type, version and requirements. External
releases without a checksum remain supported.
Temporary downloads and extraction directories are removed on failure.
Extraction is limited to 10,000 archive entries and 256 MiB of expanded files.

## Same-version manifest corrections

The **Apply manifest correction** action in the installed extensions list can apply a reviewed manifest correction
for a managed package with the same installed version. It verifies the new ZIP
and manifest, then compares every packaged file except `manifest.json` with the
installed copy. Missing or changed files stop the correction. Only the manifest
is replaced, atomically, after checking its runtime and module requirements.
Local configuration and other installed files are preserved.

Core modules, symlinked roots/manifests, external releases, and changes to another
version still require manual updates. This implements metadata corrections;
it does not run extension upgrade scripts or replace installed application code.

## Local validation

```sh
php src/vendor/bin/pest --test-directory ../tests \
  tests/Unit/FOSSBilling/ExtensionManagerTest.php \
  src/modules/Extension/tests/Unit/ServiceTest.php
```

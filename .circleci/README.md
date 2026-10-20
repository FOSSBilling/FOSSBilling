<!-- cspell:words cimg FPM -->
# CircleCI validation

The `validation` workflow builds production frontend assets, runs PHP tests
and static analysis, and tests the application through its API and browser.
GitHub Actions still supplies the required checks and publishing workflows;
the CircleCI migration has not cut over.

## Jobs and dependencies

| Job | Coverage | Depends on |
| --- | --- | --- |
| PHP tests (8.3), (8.4), (8.5) | Pest unit/module suites; PHPStan on 8.3 | Checkout and Composer packages |
| Frontend (Build) | JavaScript unit tests, TypeScript/icon checks, production core/admin/client assets, browser-suite typechecking and widget fixture | Checkout and npm packages |
| Frontend (Widget tests) | 23 isolated frontend widget browser tests, two workers | Frontend (Build) |
| Integration (Live API tests) | 91 live API tests against a fresh application/database | Frontend (Build) |
| Integration (Browser tests) | 21 application browser tests, one worker | Frontend (Build) |

These counts are from the latest verified trials; new tests can change them.
Both platforms also run `npm run test:js`: CircleCI in `Frontend (Build)` and
Actions in its reusable Playwright workflow, after installing dependencies.
The command covers frontend helpers, build tooling and admin utilities; a failure
stops subsequent build/browser steps. After the 2026-10-20 merge from main, all 61 tests passed locally in the Linux
Node 24 environment in 2.75s including npm startup. Hosted overhead has not yet
been measured; the earlier full-pipeline timings below predate this coverage.

All seven jobs use `medium.gen2`. Images are pinned by digest. PHP and Node
orbs install locked dependencies and cache downloads. PHPStan result caches
are scoped by PHP version, architecture, dependencies, configuration and branch.
Existing cache namespaces retain their historical `native` names to avoid
invalidating caches for a cosmetic rename.

Frontend outputs and the prebuilt widget fixture travel through a workspace.
PHP matrix jobs do not wait for the frontend build. API and application browser
jobs each use their own MariaDB service and fresh application installation.
The shared `integration` executor accepts an image parameter: API tests use the
smaller prepared PHP/Apache image, while application browser tests use its
Chromium headless-shell extension. Widget tests use the official Playwright
image and the standalone runner under `playwright/`.

Apache and PHP-FPM run in the primary container so the repository, dependencies
and frontend assets are available together. Apache exercises the application's
actual `.htaccess` rewrites and access rules. Browser launch checks confirm the
preinstalled Chromium works with the pinned Playwright runner.

## Running and checking changes

```sh
circleci config validate .circleci/config.yml
circleci config process .circleci/config.yml
bash -n .circleci/scripts/start-integration-app.sh
bash -n .circleci/scripts/build-frontend.sh
bash -n .circleci/scripts/check-validation-scope.sh
python3 .circleci/tests/test-validation-scope.py
```

Compilation validates configuration and the dependency graph; it does not run
the tests. Check runtime changes locally before hosted trials. Once pushed,
the configured CircleCI trigger starts the workflow. For an explicit full run:

```sh
circleci run trigger --project gh/FOSSBilling/FOSSBilling \
  --branch YOUR_BRANCH --parameter force-full-validation=true --json
```

Automatic documentation-only branches may halt each job successfully after
checkout so checks remain visible. The allowlist covers the root README,
contribution/governance documents, this guide, and Markdown under `docs/`.
The comparison uses the merge base with freshly fetched canonical upstream
`main`. Main, manual/API runs, forced runs, mixed/code changes and uncertain
comparisons run full validation. The experiment archive is outside the allowlist.

JUnit reports are uploaded to CircleCI test results. Artifacts retain frontend
build diagnostics, Pest reports, Playwright HTML reports and failure captures,
plus Apache/PHP-FPM and application logs. Jobs preserve test failures rather
than adding blanket retries.

## Project and migration status

The selected project is `gh/FOSSBilling/FOSSBilling`, ID
`703784ab-97c9-4827-9fc1-926297d4b6ba`, in organization `gh/FOSSBilling`, ID
`061a93ae-4d02-460e-8dae-07be3c6a1859`. It was configured with GitHub OAuth
for contributor validation; fork secret forwarding was disabled, and redundant
workflow cancellation was enabled. Keep publishing credentials out of this
validation project and verify settings before cutover.

Keep the workflow named **Validation** in user-facing documentation, with
`validation` as its configuration identifier. **CI** remains a useful umbrella
for validation, Docker packaging, image maintenance and previews. The existing
GitHub Actions `CI` workflow also includes labeling, merge-hold checks and
publishing, so its rename or split should be considered during that migration.

Remaining work before cutover:

- Finalize image maintenance retention and update triggers.
  The restricted hosted publishing trial and full API/browser candidate
  validation passed; both validated digests are now the runtime defaults.
- Verify cold/warm and dependency-changing runs, fork/internal PR check
  association, checkout revisions and deliberate hosted failure artifacts.
- Plan migration of PHP quality checks and independent Docker packaging coverage,
  then agree required-check changes. Previews and releases remain separate work.

## Prepared images and measurements

Root and standalone Playwright packages, the official widget image and the
prepared application browser image now target **1.64.1**. Local validation on
2026-10-20 passed all 21 application and 23 widget tests without retries, using
a fresh application/database and the minimal runner. Production asset builds,
type checks and the 61 JavaScript tests also passed. These checks include the
latest merged main; subsequent hosted verification passed as recorded below.

Image build definitions are under `images/Dockerfile` and `images/docker-bake.hcl`.
The browser target extends the Apache stage in the shared definition. Rebuild with new,
immutable tags, validate locally, publish to Docker Hub, and update digest pins
when required. A base update must also rebuild and repin its browser extension.
The candidates verified in pipeline 475 are now the default runtime pins.
Future opt-in maintenance trials publish candidates without changing those defaults.
For a Playwright upgrade, update the root and standalone runner lock files,
the official widget image and the browser image together. The runner checks that
both package versions agree; the Actions wrapper derives its version from the
root manifest. Application code, dependencies and secrets are not baked into
these prepared images. Publishing them is separate from application releases.

The latest full verification was [pipeline 471](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/471)
on `32914c250`: 82s / 63 credits. Its same-commit repeat passed in 74s / 45 credits
with restored dependency/PHPStan caches and mixed image caching. Both passed all
seven jobs with identical test outcomes. Earlier Playwright 1.63.0 full runs
ranged from 54–62s on older source with fewer tests; they are historical context,
not isolated comparisons with the latest revision.

[Pipeline 85](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/85)
compared three paired live API jobs with separate versus unified images.
Both passed identical tests. Unified added about five seconds when browser
layers were downloaded and was effectively equal when fully cached. Its extra
layers total about 212 MB compressed / 747 MB unpacked. Retain separate variants
with a shared build definition: the local maintenance trial below found little
extra build cost for two outputs.

The [two-target maintenance workflow](images/README.md) removes
intermediate publishing and duplicated build definitions. Its local full refresh
was 65.0s for separate outputs versus 64.1s for unified, with about 22 KB extra
registry metadata and no extra shared layer blobs. Browser-only changes build the
same single target in both designs. See its limitations before treating those
local timings as hosted cost evidence. The shared definition is adopted. The
[hosted maintenance trial](experiments.md#hosted-image-maintenance-trial-2026-10-20)
measured cold publication at 129s / 32 credits, warm publication at 53s / 12 credits
and a genuine browser refresh at 113s / 30 credits, including setup and smoke
verification. These measure separate outputs; no hosted unified comparison was
run. The opt-in workflow uses the restricted `docker-hub` context and does not
promote candidate images into validation; see the image guide for credential
and artifact details.

See [the experiment history](experiments.md) for configurations, revisions,
individual timings, credit totals and limitations of each comparison.

To roll back this unpublished migration, revert the configuration or disable
its CircleCI trigger. Keep authoritative Actions checks until cutover is agreed.

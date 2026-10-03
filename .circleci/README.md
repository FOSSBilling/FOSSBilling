<!-- cspell:words buildx zstd cimg FPM pwuser -->
# CircleCI validation

Each CircleCI pipeline runs the `validation` workflow: PHP 8.3/8.4/8.5 tests,
PHPStan on 8.3, frontend assets/typechecking, live API tests, application
browser tests and frontend widget browser tests. No pilot activation parameter or stage filters remain. GitHub Actions
remain the required checks and the only publisher; cutover is still pending.

Keep experiments local until hosted verification is needed. Once this config
is pushed, the configured CircleCI push trigger will start all seven jobs.

The earlier Docker compatibility workflow has been removed, including its
machine executor, image-build/test jobs, image transfer commands, Docker orb,
and activation/resource/cache parameters. Its diagnostic results below are
historical evidence, not an additional active test path. Docker packaging
validation remains a separate future step.

## Organization and integration

Use a GitHub-backed (`gh/FOSSBilling`) CircleCI organization and a **GitHub
OAuth pipeline** for contributor validation. CircleCI currently supports fork
PR builds through GitHub OAuth, but not through GitHub App pipelines. OAuth
has broader GitHub permissions than the GitHub App; review those permissions
during authorization. A GitHub App can coexist later for separate needs.

The initial organization `8399effa-fb5f-4cf3-9e5c-c2690e7fb1d2` is a
CircleCI-native organization. On 2026-10-01 its project `FOSSBilling`
(`78966fd9-3579-4d79-b936-96ff4ef39f8a`) had a GitHub App pipeline for
`FOSSBilling/FOSSBilling` and two enabled presets (`all-pushes` and
`only-build-prs`). These can overlap. `FOSSBilling1` had no pipeline
definitions. Neither project is a migration requirement.

The CLI creates CircleCI-native organizations; a GitHub-backed organization
is imported through GitHub social sign-in and OAuth authorization. The
initial organization cannot be given an OAuth pipeline simply by editing
this YAML.

Selected organization: `gh/FOSSBilling`, ID
`061a93ae-4d02-460e-8dae-07be3c6a1859`. Selected project:
`gh/FOSSBilling/FOSSBilling`, ID `703784ab-97c9-4827-9fc1-926297d4b6ba`,
using the implicit GitHub OAuth pipeline
`32028f14-086d-505a-9dee-7c749de33a59`. Project variables were empty at setup.
Fork secret forwarding was disabled and verified before enabling fork builds;
redundant-run cancellation is enabled. Following the project refreshed its
stale metadata to `main`, updated the PR-only branch override to `main`, and
recognized the repository as open source. Pilot runs use an explicit branch.

References:

- [Integration feature support](https://circleci.com/docs/guides/integration/version-control-system-integration-overview/)
- [Organization types and OAuth permissions](https://circleci.com/docs/guides/permissions-authentication/users-organizations-and-integrations-guide/)
- [OAuth and GitHub App pipelines in one organization](https://circleci.com/docs/guides/integration/using-the-circleci-github-app-in-an-oauth-org/)

## Pilot scope

- Run the existing Pest unit/module suites in pinned PHP 8.3, 8.4 and 8.5
  environments, plus PHPStan on 8.3 to match Actions coverage.
- Build full production frontend assets and typecheck browser tests in Node 24.
- Pass frontend outputs through a workflow workspace.
- Run live API and browser tests in separate PHP environments with MariaDB
  services and fresh app installations, after the frontend build succeeds.
- Collect Pest and Playwright JUnit reports, browser artifacts and server logs.

PR quality checks and independent Docker packaging validation are not yet
part of this pilot. Both CI systems now use Playwright 1.63.0, the latest
stable version checked on 2026-10-03. Earlier recorded trials used 1.62.1;
compare new image trials against the updated baseline.

## Baseline and acceptance

Three successful main-branch Actions runs sampled on 2026-10-01:

| Run | Full pipeline including preview publishing |
| --- | --- |
| [36916377569](https://github.com/FOSSBilling/FOSSBilling/actions/runs/36916377569) | 7m44s |
| [36918954624](https://github.com/FOSSBilling/FOSSBilling/actions/runs/36918954624) | 7m45s |
| [36927047097](https://github.com/FOSSBilling/FOSSBilling/actions/runs/36927047097) | 8m50s |

PHP build steps took 73–97s, Pest 18–26s and PHPStan 22–25s. Live jobs took
86–101s and browser jobs 133–157s. This small sample does not establish p95,
cold-cache performance or flakiness. The CircleCI pilot has no publishing
tail: compare time to equivalent validation results, not its total against
the full Actions duration.

Before cutover, compare identical checked-out revisions and suite counts,
cold and warm builds, dependency-changing commits, successful and failed
runs, queue time, startup, image export/transfer/load, test runtime and
credits consumed. Collect a larger representative sample before agreeing
on a numerical performance target. Verify JUnit appears in CircleCI and
HTML, traces and screenshots are readable after a browser failure.

## Trigger and check mapping before activation

| Event | Current Actions behavior | CircleCI requirement |
| --- | --- | --- |
| Branch push | Heavy validation on all branches | Preserve coverage without a second run for the same internal PR update |
| Internal PR update | Heavy checks rely on branch push; PR quality jobs run separately | Confirm check association and the revision tested |
| Fork PR opened/updated | Heavy validation on PR checkout | Enable fork builds on OAuth; never pass project secrets to forks |
| PR labels changed | Merge-hold check, no heavy rebuild | Keep GitHub label automation |
| Main push | Validation followed by preview publishing | Pilot validates only; Actions continues publishing |
| Release published/manual release | Release distribution and Sentry | Remain on Actions |

Record `git rev-parse HEAD` in every pilot job. Resolve PR-head versus merge
commit behavior explicitly before required checks move; the current Actions
fork checkout uses GitHub's PR merge ref. Choose a single validation trigger
route and verify redundant-run cancellation without cancelling release work.

Before any run, inspect project environment-variable names and attached
contexts. Keep the validation project free of publishing credentials, keep
fork secret forwarding off and use read-only checkout credentials. YAML
without a context does not prevent project-level variables being injected.

The live GitHub ruleset currently requires Actions Spellcheck, all three
PHP jobs, Live Tests, and Rector/PHP-CS-Fixer. Playwright runs but is not
required. CodeQL and GitHub Code Quality are separate rules. Do not replace
these requirements until equivalent CircleCI checks and contributor flows
have been proven. Move quality checks in a subsequent phase.

## Validation and rollback

```sh
circleci config validate .circleci/config.yml
circleci config process .circleci/config.yml
bash -n .circleci/scripts/start-native-app.sh
```

Config compilation verifies the job graph, not runtime execution. Prepare
changes locally before hosted runs and retain Actions checks during comparison.
Disable the CircleCI trigger or revert the configuration to stop these jobs.
There are no published outputs to undo.

### Historical Docker compatibility trials (removed)

The following results describe the retired Docker workflow. Its parameters
and jobs are no longer available in the current configuration.

Local preparation checks on 2026-10-01: CircleCI compiled the default config
to zero jobs and the enabled config to all five intended jobs. Shell syntax
checks passed. The existing local PHP 8.5 image ran 3,795 unit/module tests
with zero failures and four skips, producing JUnit with file/class metadata.
The live report contained 84 tests and one failure in AutoPromoTest's admin
invoice promotion flow (`attach_order` rejected an immutable invoice). The
original committed script reproduced the same failure against that image;
the reporting change is not its cause. This image was not rebuilt for the
pilot. The remote fresh-image run below passed this flow and the full browser
suite; the local failure does not reproduce on the pilot revision.

Remote pilot on 2026-10-01, revision
`974ee4a8ac6c1b1c61b8ec4dd44572e072053ad6`:

- [CircleCI pipeline 3](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/3)
  passed all five jobs. All three PHP suites and PHPStan passed; live API
  tests passed all 91 tests and Playwright passed all 44 tests. CircleCI
  received JUnit for every test job and the browser HTML report.
- [Actions comparison](https://github.com/FOSSBilling/FOSSBilling/actions/runs/36936483500)
  passed the equivalent validation jobs on the same revision. Spellcheck
  separately flagged `buildx` and `zstd` in the new config; a scoped spelling
  annotation addresses those command names.

| Equivalent validation timing | CircleCI medium | GitHub Actions |
| --- | --- | --- |
| First PHP job start to final browser completion | 5m37s | 4m10s |
| PHP 8.3 job including PHPStan | 3m03s | 1m37s |
| PHP 8.4 job | 2m21s | 1m10s |
| PHP 8.5 job including CircleCI image export | 2m49s | 1m15s |
| Live API job | 1m18s | 1m12s |
| Browser job | 2m33s | 2m29s |

The conservative CircleCI baseline is slower. This proves execution and
reporting compatibility, not a performance improvement. The later resource-class and cache trials below also recorded credits
alongside elapsed time.
Fork PR checkout/check association, cancellation, and browser failure-artifact
retention still need dedicated trials before activation or cutover.

The retired Docker optimization trials used the same application/test inputs as the
compatibility run. Pipeline [7](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/7)
on `4dabb6bea61881c66188d4ef5951f032913ddc23` passed all five jobs using
`large`, the Docker orb, native npm caching, and DLC on all PHP builds.
Workflow elapsed time was 5m02s and Insights reported 822 credits. PHP 8.5
image building took 87s, Pest 39s, and browser execution 95s.

The same-revision DLC repeat restored cache data but reused only 10 cached
steps on PHP 8.5, compared with 40 on PHP 8.3. This is a mixed cache hit,
not a fully warm result. A subsequent configuration limited DLC to the PHP 8.5 build,
which gates the integration jobs, to prevent parallel cache writers from
competing and reduce the fixed DLC charge to 200 credits per pilot. Gen2
runner trial is diagnostic. Further cache trials are paused pending the
architecture decision below.

| Diagnostic run | Workflow elapsed | Actual Insights credits | Result |
| --- | --- | --- | --- |
| [3: medium compatibility baseline](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/3) | 5m39s | 123 | All five jobs passed |
| [7: large, first DLC run on all PHP jobs](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/7) | 5m02s | 822 | All five jobs passed |
| [8: identical-revision DLC repeat, mixed hits](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/8) | 5m34s | 790 | All five jobs passed |
| [11: large.gen2, DLC off](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/11) | 4m20s | 338 | All five jobs passed |

These figures include workflow startup and differ from the first-job-to-last-job
timings above. Runs 7 and 8 use `4dabb6b`; run 11 uses `3ef0c57`, changing CI
configuration/documentation only. They are individual diagnostic samples, not
an accepted performance comparison or evidence for the proposed native design.

## Native architecture and initial trials

Prefer CircleCI's language containers and service containers for application
validation. Preserve existing checks and outcomes, rather than reproducing
the Actions job graph or its image-building approach.

| Work | Proposed execution | Dependencies |
| --- | --- | --- |
| Pest matrix, PHP 8.3/8.4/8.5 | Native Docker executor with pinned `cimg/php` images; PHP orb caches isolated by PHP version/lockfile | Checkout and Composer installation only |
| PHPStan and later PHP quality checks | Same PHP environment, initially alongside PHP 8.3 tests | Composer dependencies, no frontend or application-image build |
| Frontend checks and production assets | Native Node 24 executor; npm download cache; run the existing full build and browser typecheck once | Checkout and npm installation |
| Live API tests | PHP primary container, Apache with PHP-FPM, MariaDB service container, fresh app install | Built frontend assets via workspace; Composer dependencies |
| Browser tests | PHP/Node/browser environment, Apache with PHP-FPM, separate MariaDB service container and fresh install | Built frontend assets via workspace; matching Playwright package/browser |
| Docker packaging validation | One independent machine build and runtime smoke check for the shipped PHP 8.5 image | Runs beside application validation; does not gate starting live/browser tests |

The PHP convenience image already provides Composer, FPM, and the required
core extensions, including the database drivers. Verify precise image digests
and PHP/Node/browser versions before a pilot. Add services for optional
PostgreSQL tests in a separate coverage expansion, so suite changes are not
hidden in performance comparisons.

Apache is deliberate: `src/.htaccess` handles rewrites, authorization-header
forwarding, and access rules. A basic `php -S` server would not reproduce that
contract. Start Apache/PHP-FPM in the primary container so checkout, dependencies
and assets are directly available; a secondary service container cannot be
assumed to see those files. MariaDB can use CircleCI's native shared network.
Keep live and browser databases isolated.

The new graph removes frontend/release assembly from each PHP matrix job and
removes image export, transfer and load from the integration dependency chain.
Workspaces carry frontend outputs; caches carry dependency downloads; native
JUnit and artifacts carry diagnostics. Retain Docker packaging coverage as a
separate check, including the release-tree transformations that source-based
tests would no longer exercise. Publishing stays in its existing phase.

First prove one native PHP job and exact suite/skip parity. Next prove the
frontend producer and live/browser environment, including installation,
Apache routing, authentication, writable directories and failure reports.
Only then expand the matrix and benchmark identical revisions, coverage,
cold/warm dependencies, queue time, total validation latency and credits.
Resource sizing and test splitting follow measurements; do not introduce
parallel browser workers against a shared mutable database by default.

The initial trials covered one native PHP 8.5 job, a Node 24 frontend
producer, and separate live/browser jobs using native MariaDB services and
Apache/PHP-FPM. The current configuration expands PHP to 8.3/8.4/8.5 and
adds PHPStan on 8.3. PR quality checks and the independent Docker packaging
check remain subsequent steps.

### Initial execution results

Native PHP pipeline [16](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/16)
passed in 33s using 7 credits. Its JUnit report matches the Docker PHP 8.5
baseline exactly after normalizing checkout-root prefixes in Pest class names:
3,895 test records, the same four PostgreSQL skips, and no changed outcomes.
Composer installation took 4.8s and Pest took 16.1s. This uses a digest-pinned
PHP 8.5.10 convenience image and `medium.gen2` (two CPUs), without image builds.

The first complete native pipeline
[20](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/20)
passed in 2m10s using 44 credits. All 91 live API and 44 browser test identities
and outcomes match the Docker reports. Node runs the existing full production
asset build and browser typecheck once; live/browser jobs depend only on that
asset workspace, not on the unit job. Composer/npm download caches are native
and lockfile keyed. Each integration job installs its own fresh app/database.

An initial server-setup failure was traced to Apache's default user lacking
permission to traverse the checkout directory. Apache and FPM now run as the
checkout owner. Server logs uploaded on that failure. Explicit startup checks
also exercise `/login` rewriting and the 404 protection for `/config.php`.
Only the browser job disables session fingerprinting, matching the Docker
browser runner. Playwright 1.62.1 and its matching Chromium preserve the current
baseline; reconciling the package-lock version remains separate work.

These native measurements cover PHP 8.5 and frontend/integration validation;
they exclude PHP 8.3/8.4, PHPStan, quality checks, and packaging. They are proof
of the initial architecture, not a complete CI speed comparison.

Final native verification
[24](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/24),
revision `e3e2923fd45c37f11db8b5a954a8ddb1ed771ff2`, passed all four jobs in
2m18s using 45 credits. All three JUnit reports were downloaded and compared
with the Docker baseline: no added, missing or changed test outcomes. Apache
startup checks passed, and the browser HTML report and JUnit were verified in
the uploaded artifacts. The native repeat in pipeline 22 passed in 2m14s using
43 credits. These runs reuse dependency download caches but still install
Apache and the matching Playwright browser in each fresh job.

The Docker compatibility workflow and the native proof's stage control have
been removed. At that stage, pipelines ran the complete six-job validation workflow
without activation parameters. The widget separation below adds a seventh job.

### Dependency orb refactor

Native jobs use the pinned `circleci/php@3.0.0` and `circleci/node@7.2.1`
orbs for dependency installation and caching. PHP uses `php/install_packages`
with `vendor_dir: src/vendor`, the actual Composer file-cache path, the
existing installation flags, and a PHP-specific cache version. This orb
caches both installed dependencies and downloaded archives; it still runs
`composer install` to check the platform and regenerate the optimized
autoloader. Matrix jobs use `v2-native-php8.<minor>` cache versions; integration
jobs share the PHP 8.5 cache namespace.

Node uses `node/install-packages` with the existing
`npm ci --no-audit --no-fund` command and an explicit `~/.npm` cache path.
The override command enables the orb's download-cache steps; the expanded
configuration caches no `node_modules`. Its fallback keys can reuse older
download archives, while `npm ci` continues to enforce the current lockfile.
Both native Node consumers now use the same orb cache namespace.

Keep the digest-pinned convenience images: they already include PHP, Composer,
or Node, so the orbs' runtime installation commands are unnecessary. App
installation, Apache/FPM setup, test commands and reporting remain specific
to this repository.

Validate and expand configuration before running the expanded shell steps
in clean local containers. Local shell execution checks dependency installs
and tests; it does not verify hosted cache transfers, workspace orchestration,
artifact uploads, or CircleCI performance. The installed CircleCI CLI lacks
`local execute`, so these checks use Docker directly. Avoid pushing experimental
changes until hosted verification is needed, since pushes also start Actions.

Local validation of this refactor passed: the expanded PHP orb install and
Pest suite produced 3,895 records with four skips, matching every test identity
and outcome from native pipeline 24. The expanded Node orb install, full
production asset build, and browser typecheck passed in the pinned Node image.
At that stage, config validation and expansion passed, including the
zero-job default and all four enabled native jobs. No hosted run was triggered for this refactor;
cache transfers and performance remain unverified with the new orb caches.


### PHP matrix expansion

The `php-tests` job uses CircleCI's matrix syntax to create `PHP tests (8.3)`,
`PHP tests (8.4)` and `PHP tests (8.5)`. The `php-minor` parameter selects one of
three executors with digest-pinned images and `medium.gen2` resources. Every
version installs locked dependencies, prepares the same configuration, runs
the existing Pest suite and uploads JUnit. Artifact destinations include the
PHP version. Only PHP 8.3 runs the existing PHPStan command, before Pest,
matching `.github/workflows/php-build-test.yml`.

The `validation` workflow runs the three matrix jobs plus frontend, live API
and browser jobs for six in total. Integration jobs still
wait only for frontend assets and use PHP 8.5; they do not rebuild an app
image or wait for the matrix. CircleCI keeps matrix jobs independent rather
than reproducing Actions' strategy-level cancellation of sibling jobs on a
failure. Every PHP version must pass before cutover is considered.

Local validation passed in clean pinned PHP 8.3.35 and 8.4.26 containers:
each produced 3,891 passed records and the same four skips, with exact test
identity/outcome parity against the previously verified PHP 8.5.10 report.
PHPStan passed on 8.3. The expanded 8.5 shell commands and image are unchanged
from that successful local run. Config validation and matrix expansion passed before removal of the pilot
stage control. No hosted run was triggered. The current config expands to all
six jobs without parameters; no workflow activation conditions or job filters
remain.

### Remote six-job verification

After a fresh local recheck of the current working tree, pipeline
[25](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/25)
tested revision `e0d5587c26cfb5a573c40aa77be3c596664891c3`. All six jobs passed.
Workflow Insights records 129 seconds and 61 credits; this is measured workflow
duration, rather than the CLI watcher's polling-dependent completion time.

Every PHP version produced 3,891 passed records and the same four skips,
with exact identity/outcome parity against its fresh local report. PHPStan
passed on 8.3. Frontend installation, the full production build, typechecking
and workspace persistence passed. All 91 live API and 44 browser records
match the earlier native reports. Uploaded JUnit and browser HTML reports
were downloaded and verified.

The Node orb created its new npm archive cache, and the browser job consumed
the frontend workspace and restored the PHP 8.5 and npm caches. This confirms
cache creation and downstream use, not a separate warm-run benchmark. No
duplicate manual run was triggered. The equivalent Actions PHP, live API,
browser, spellcheck, quality and CodeQL checks passed on the tested revision;
its preview packaging workflow was still pending when these results were
recorded. Packaging and PR quality coverage on CircleCI remain future work.

### Official Playwright service experiment

Browser jobs keep PHP/Node, Apache and the checkout in their primary container,
with MariaDB and the pinned official Playwright 1.62.1 Noble image as services.
The Playwright service runs `playwright@1.62.1 run-server` as `pwuser` on port
3000. The matching test runner connects through `PW_TEST_CONNECT_WS_ENDPOINT`.
Only browser jobs start this service; live API jobs keep their two-container
environment. The primary no longer downloads Chromium or installs browser
system packages on every run. Test selection, retries and report paths stay
the same.

The preceding browser job spent 18.8 seconds installing its runner and browser,
and 59.1 seconds executing tests. Compare total job startup and execution,
including the official image pull and browser-server startup, before claiming
a performance gain. First verify all 44 tests and a disposable failure probe
locally before a hosted comparison. Configuration validation and expansion
passed. After Docker Desktop was restarted, the expanded browser job ran
locally with the pinned PHP, MariaDB and official Playwright images sharing
a network namespace. All 44 tests passed with exact identity/outcome parity
against pipeline 25. A separate disposable test failed deliberately and
retained its JUnit, HTML, screenshot, video and valid trace archive in the
primary container. The probe is not part of the committed suite. Local
execution does not establish hosted performance or artifact-upload behavior.

Hosted pipeline [52](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/52),
revision `507765a85ebb4418655b8c2ef8ffdfb3aeb84104`, passed all six jobs.
Downloaded browser JUnit matches all 44 baseline identities and outcomes,
and the uploaded HTML report is present. Existing Actions CI, quality and
CodeQL checks passed on this revision.

| Measurement | Pipeline 25: install browser | Pipeline 52: official service |
| --- | --- | --- |
| Browser job elapsed | 101.2s | 99.2s |
| Container startup | 1.4s | 14.1s |
| Runner/browser installation | 18.8s | 1.5s (runner only) |
| Playwright test execution | 59.1s | 62.8s |
| Workflow duration (Insights) | 2m09s | 2m05s |
| Workflow credits (Insights) | 61 | 57 |

The larger image's startup cost largely offsets the installation saving in
this single sample. Retain the official service for its prepared browser
environment, but do not claim a material performance improvement from this
run. Warm/cold repeats and hosted failure-artifact uploads remain separate
validation work; the local probe proves transfer back to the runner only.


### Frontend widget separation

The widget file accounted for 23 of 44 browser tests and 41.8 seconds of
summed test time in pipeline 52. It mounts HTML directly, bundles widget
source using esbuild and loads the production admin CSS. It needs frontend
source/dependencies and built assets, but no installed PHP application,
MariaDB service or admin credentials.

The `frontend-browser-tests` job uses the pinned official Playwright 1.62.1
image as its primary container, the existing full npm installation and the
frontend asset workspace. `playwright.widgets.config.ts` selects only the
widget file, with two workers and fully parallel tests. Every test retains
its own browser context and runs its setup hooks. The application job uses
`playwright.application.config.ts` to exclude that file and keeps one worker
against its isolated app/database. Both jobs retain JUnit and HTML/failure
artifacts. They run alongside each other after the frontend build succeeds.

The default `playwright.config.ts` still selects all 44 tests with one worker,
so existing Actions and local commands keep their coverage. The browser
TypeScript check now includes all three root configurations. The application
job preserves the working-copy choice to install Chromium in its PHP/Node
container; the official image is used only for the new frontend widget job.
Dependency minimization is a later trial.

Local validation passed with the expanded commands and fresh frontend assets:
23 widget tests passed without any backend container, and 21 application
tests passed against a fresh app/database. The union of downloaded-style
JUnit records matches all 44 pipeline-52 identities/outcomes, with no overlap,
omissions or retries. Default test discovery still lists all 44 tests.
The production frontend build and typecheck passed. A widget-only repeat
under a two-CPU limit took 47.4 seconds with one worker and 28.0 seconds with
two; both retained exact 23-test parity and required no retries. These local
Docker Desktop timings are diagnostic, not a hosted benchmark. A disposable
intentional widget failure retained JUnit, HTML, screenshot, video and a valid
trace archive. The failure probe is not committed.

Hosted pipeline [55](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/55),
revision `298b2b3056a702069ba4dc6a7510b41d6c855f8b`, passed all seven jobs.
Downloaded JUnit verifies 23 widget and 21 application tests, with a disjoint
union matching every pipeline-52 test identity/outcome. Both HTML reports
were downloaded and checked; neither browser job required retries. Existing
Actions CI, quality and CodeQL checks passed on this revision.

| Hosted measurement | Pipeline 52 | Pipeline 55 |
| --- | --- | --- |
| Workflow duration (Insights) | 2m05s | 1m20s |
| Workflow credits (Insights) | 57 | 57 |
| Application browser job elapsed | 99.2s (all 44 tests) | 61.9s (21 tests) |
| Widget browser job elapsed | Included above | 50.4s (23 tests) |
| Browser test execution | 62.8s combined | 18.4s app / 23.6s widgets, concurrently |

This single sample reduced workflow duration by 45 seconds at unchanged
credits. It includes the application job's return to in-container Chromium
installation, which took 18.5 seconds, alongside widget separation and
parallelism. Further warm/cold samples are needed before treating the result
as a sustained improvement. Hosted failure-artifact uploads still need a
dedicated failure trial; local retention has been verified.


### Minimal application browser dependencies

The 21 application browser tests import only Playwright and local helpers.
They consume frontend outputs from the workspace rather than rebuilding
assets. The separate widget job still needs the full frontend dependencies.

Application jobs now use the Node orb against `.circleci/playwright`, a
standalone manifest/lockfile containing `@playwright/test@1.62.1` and its
locked transitive dependencies. `npm ci --workspaces=false` avoids installing
the root frontend/workspace tree. A separate
`v1-application-playwright-node24` namespace caches npm download archives
using this lockfile. A root `@playwright/test` symlink lets the unchanged
configuration, tests and helpers resolve that same runner. Chromium and
application tests use the standalone CLI directly; there is no second
runner installation in `/tmp` for this job.

Root frontend dependencies, the widget job, default Actions/local commands,
browser selection, retries and artifact paths are unchanged. The standalone
runner preserves the recorded 1.62.1 comparison baseline; reconcile versions
in both browser jobs separately when updating that baseline.

Pipeline 55 spent 5.7 seconds installing all npm dependencies and another
1.6 seconds installing its baseline runner in the application job. Compare
total job/workflow time and credits after local parity verification, including
creation of the new cache, before claiming a performance gain.

Local execution of the expanded application job passed all 21 tests with
exact identity/outcome parity against pipeline 55 and no retries. Its clean
container had only `@playwright/test`, `playwright` and `playwright-core`
installed; esbuild, CKEditor and Tabler dependencies were absent. The HTML
report was present. A separate intentional failure retained JUnit, HTML,
screenshot, video and a valid trace archive. Config validation/expansion,
lockfile integrity/version checks and spelling checks passed.

Hosted pipeline [56](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/56),
revision `9838478c7dc9a13d97bcceefdf0067b25e072f8c`, passed all seven jobs.
Downloaded application JUnit matches all 21 pipeline-55 identities/outcomes;
HTML was checked and no retries occurred. Existing Actions CI, quality and
CodeQL checks also passed. The Node orb created a 3.8 MiB standalone npm
archive cache.

| Hosted measurement | Pipeline 55 | Pipeline 56 |
| --- | --- | --- |
| Application npm + runner installation | 7.3s | 0.5s |
| Application browser job elapsed | 61.9s | 59.0s |
| Workflow duration (Insights) | 1m20s | 1m17s |
| Workflow credits (Insights) | 57 | 56 |

Application test execution remained similar (18.4s versus 18.6s). Container
startup rose from 1.5s to 5.9s, partly offsetting the installation saving.
This is one hosted sample, including first cache creation; subsequent warm
restoration and sustained timing improvements are not established. The
change removes unnecessary dependencies and a duplicate runner installation
while preserving the tested application suite.


### PHPStan result caching

Only the PHP 8.3 job restores and saves PHPStan results. The CircleCI overlay
`.circleci/phpstan.neon` includes the root analysis configuration and sets
`tmpDir` to the ignored `cache/phpstan` directory. The full `src` scope,
rule level and bootstrap remain inherited; GitHub Actions and default local
commands continue using the root configuration.

Native cache keys isolate PHP 8.3, executor architecture, Composer lockfile,
both analysis configurations and branch. Exact revision lookup falls back
to the latest matching branch snapshot. A new revision saves a new snapshot
because CircleCI caches are immutable; this differs from dependency archives,
which do not need refreshing after each successful analysis. Failed PHPStan
steps do not publish a snapshot. Only this job writes the namespace.

PHPStan checks file/dependency changes and its own runtime/configuration
metadata before reusing results, and periodically performs a full analysis.
`-vv` reports whether results were actually reused; a CircleCI archive hit
alone is insufficient evidence. See the [PHPStan result cache documentation](https://phpstan.org/user-guide/result-cache).

Local disposable PHP 8.3 containers passed the cold analysis (79s) and a
fresh-container warm analysis (17.4s, zero files analyzed again). Adding a
temporary method returning a string as `int` made the warm analysis fail
with the expected `return.type` error and one file analyzed again. Removing
that probe restored a pass. Config validation/expansion confirms only PHP
8.3 gains the restore/analyse/save steps; PHP 8.4/8.5 remain unchanged.
Hosted measurements must include restore/save overhead.
This targets PHP analysis time and credits; the browser jobs currently gate
workflow completion, so it may not reduce overall workflow duration.


Hosted pipeline [57](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/57),
revision `2271a5f91f6a39bc646a4ca72f5d4dfb6ceffa55`, passed all seven jobs
and existing Actions CI, quality and CodeQL checks. Its cold PHPStan analysis
saved a 5.1 MiB archive. A targeted same-revision rerun of only PHP 8.3
restored that archive and PHPStan confirmed reuse with one file analyzed again.
Both downloaded PHP reports match all 3,891 passing tests and four skips
against the earlier matrix baseline, with identical test identities/outcomes.

| Hosted measurement | Cold | Warm PHP 8.3 rerun |
| --- | --- | --- |
| PHPStan reported analysis time | 18.8s | 1.7s |
| PHPStan step elapsed | 19.0s | 1.9s |
| Result cache restore step | 0.13s (miss) | 0.43s |
| Result cache save step | 0.52s | 0.06s (existing key) |
| PHP 8.3 job elapsed | 43.2s | 35.3s |

The cold full workflow took 79s and 54 credits in Insights, versus pipeline
56's 77s and 56 credits. The warm experiment reran only PHP 8.3, so its
workflow duration/credits cannot be compared with a full seven-job workflow.
The analysis saving is established for this cache hit; these single samples
do not establish sustained credit or overall workflow improvements. Restoring
an older branch snapshot after a source commit remains for normal subsequent
runs; deliberate source-change invalidation was verified locally.


### Prepared Apache integration image trial

`.circleci/images/php-apache/Dockerfile` extends the exact pinned
`cimg/php:8.5.10-node` integration base and installs only Apache with
`--no-install-recommends`, then removes package indexes and restores the
`circleci` user. It contains no checkout, dependencies, built frontend assets
or credentials. Application configuration, module activation, PHP-FPM,
virtual host setup and server startup remain in `start-native-app.sh`.
That script installs Apache only when `apache2ctl` is absent, preserving
the original-image fallback.

Build the prototype locally:

```sh
docker build --platform linux/amd64 -t fossbilling-circleci-php-apache:trial \
  .circleci/images/php-apache
```

The prepared image is published publicly as
`docker.io/fossbilling/circleci-php-apache:php8.5.10-node-apache-25434d4`.
The integration executor now trials its pinned index digest
`sha256:acfccef40b6fdd3f274e0bad1c28661e2393ee48beefd62f6ebe3181804033aa`.
Anonymous manifest access was verified, including the Linux AMD64 platform.
Only live API and application browser executors change; MariaDB, test
selection and all other jobs retain their existing images/configuration. Building it
inside every validation job would put installation back on the critical
path. Rebuild when the pinned base changes or Apache packages need updating;
APT resolves Apache packages at build time, so the final published digest
must identify the tested contents. Use a new versioned tag for each rebuild,
then update the executor digest after verification. Compare hosted pull/startup, app setup,
whole-job elapsed time and credits before adopting it. The image should
remain a separate CI tool image, outside application release packaging.

Three alternating fresh-container trials per image used two CPUs, a fresh
MariaDB database ready before timing, identical installed dependencies and
built assets, and the same app startup script. Docker pulls, Composer
installation and database readiness were excluded from app setup timing.
The installer removes its own entry script, so that tracked fixture was
restored between trials. Apache was `2.4.52-1ubuntu4.23` in this build.

| Local diagnostic | Current base | Apache prepared |
| --- | --- | --- |
| App setup, three trials | 14.5s / 14.3s / 13.7s | 4.4s / 4.9s / 4.8s |
| Median app setup | 14.3s | 4.8s |
| Median runtime Apache package installation | 8.8s | omitted |

The added filesystem layer is about 11.4 MB according to Docker history.
All six setups passed rewritten-route and configuration-file protection
checks. Separate fresh prepared-image environments passed all 91 live API
tests and all 21 application browser tests, with exact baseline test
identity/outcome parity, no browser retries and an HTML report. Image build,
Bash syntax, config validation and spelling checks passed.

This is a local feasibility result. Pipeline 56's hosted app setup was
already 7.4s, and prepared-image pull/startup time is still unmeasured.
The local result justified a hosted trial; Docker Hub publication was
subsequently agreed and completed. The initial hosted results below measure startup and setup separately.

Pipeline [61](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/61)
passed all seven jobs at `25434d453bdabe85a4b0550a80a64e3caab6865f`
using the original executor image. This verifies the installation fallback,
not prepared-image hosted performance. PHPStan also reused pipeline 57's
branch snapshot on this new revision and passed in 1.6s, confirming the
cross-revision cache fallback from the previous trial.

Hosted pipeline [62](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/62)
passed all seven jobs at `e3e4973e654091036e1219c76feb37119bc68427`
with the published prepared image. Existing Actions CI, quality and CodeQL
checks passed. Downloaded JUnit preserves all 91 live API and 21 application
browser test identities/outcomes, without browser retries. HTML and server
logs are present. Startup logs confirm no runtime Apache package install.

| Hosted measurement | Pipeline 61: current base | Pipeline 62: prepared Apache |
| --- | --- | --- |
| Browser environment startup | 1.2s | 1.5s |
| Browser app setup | 8.6s | 2.1s |
| Browser job elapsed | 54.6s | 52.5s |
| Live API environment startup | 1.1s | 1.5s |
| Live API app setup | 7.9s | 2.0s |
| Live API job elapsed | 41.7s | 36.4s |
| Full workflow duration (Insights) | 82s | 72s |
| Full workflow credits (Insights) | 53 | 48 |

Prepared-image setup saves roughly six seconds in each integration job,
while startup adds about 0.4s in this first sample. Chromium installation
rose from 17.5s to 21.8s, offsetting part of the browser setup saving.
These are individual runs: the whole-workflow difference also includes
variation in unchanged jobs and does not establish sustained credit or
runtime gains. Retain the prepared image for the demonstrated setup saving
and removal of per-job Apache package downloads. Rollback requires restoring
the prior pinned `cimg/php:8.5.10-node` primary image; the script retains its
installation fallback. Publishing or rebuilding this tool image is separate
from normal validation runs and application release publishing.





### Frontend concurrency and resource trial

The production core, admin and client builders write separate output trees.
The CircleCI runner `.circleci/scripts/build-frontend.sh` runs the existing
`npm run check` first, launches those three existing build scripts together,
waits for every status, prints each log and fails if any build failed. Only
a successful build group proceeds to the existing `npm run pw:tsc`.
Per-build logs are retained as artifacts; the three frontend workspace paths
and default Actions/local `npm run build` behavior remain unchanged.

A clean pinned Node 24.9.0 container with locked dependencies compared
sequential/concurrent builds at two CPUs/4 GB and four CPUs/8 GB. Three rounds
reversed trial order in the middle round. Each run removed all three output
trees and completed every existing check. All 12 runs produced exactly the
same SHA-256 hashes for all 300 generated files.

| Local median build/check time | Two CPUs | Four CPUs |
| --- | --- | --- |
| Sequential | 6.84s | 6.58s |
| Concurrent | 5.75s | 4.15s |

A deliberate Sass error in the disposable admin source made the runner fail,
retain its error log, wait for the core/client builds and skip the browser
typecheck. Restoring the fixture restored success. These local measurements
exclude dependency installation, checkout, workspace upload and hosted CPU
performance. Pipeline 62's sequential build/check step was already 4.3s in a
14.0s frontend job, so whole-job savings may be small.

For the hosted trial, the standard workflow used `medium.gen2` and a temporary
separate workflow ran the identical frontend job on `large.gen2`. Its
workspace had no consumers and could not collide with the validation workspace.
[CircleCI pricing](https://circleci.com/pricing/price-list/) lists 12 and 24
credits/minute respectively. Compare complete frontend-job time and credits,
not build time alone. The temporary workflow/resource parameter is now
removed, retaining concurrency on `medium.gen2` and the single seven-job
validation workflow.

Pipeline [63](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/63)
passed both workflows at `4a0734bca56be860262474fe6215e35416628478`,
along with existing Actions CI, quality and CodeQL checks. Downloaded reports
match all 23 widget, 21 application and 91 live API test identities/outcomes.
Each resource class retained all three build logs.

| Hosted frontend measurement | Sequential medium, pipeline 62 | Concurrent medium, pipeline 63 | Concurrent large, pipeline 63 |
| --- | --- | --- | --- |
| Build/check step | 4.32s | 3.73s | 2.37s |
| Job elapsed from job details | 14.0s | 13.1s | 11.2s |
| Job duration reported by Insights | 17s | 15s | 13s |
| Job credits reported by Insights | 3 | 3 | 5 |

Insights durations use different start/stop boundaries from job-detail
elapsed times; compare within each measurement source. Large saved a further
1.9s of job elapsed time for two extra reported credits, so retain medium for
this short job. Concurrent medium saves about 0.6s in the build/check step
and retains useful failure diagnostics at the same reported credit usage.
The standard workflow recorded 68s/47 credits versus pipeline 62's 72s/48;
that larger difference also includes variation in unchanged jobs and is not
all attributable to frontend concurrency. These are individual hosted runs,
not evidence of sustained workflow or credit savings. Rollback is restoring
the original `NODE_ENV=production npm run build && npm run pw:tsc` command.

Final pipeline [66](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/66)
passed all seven jobs and existing Actions CI, quality and CodeQL checks at
`a6056ceef69cae337794163e4c5083d58c472c95`, after removing the benchmark
workflow/parameter. Expanded-config comparison confirms its seven jobs are
identical to the successful standard workflow in pipeline 63. The build/check
step repeated at 3.74s, with 13.6s job-detail elapsed time and three reported
job credits. The full workflow recorded 66s/45 credits in Insights; the small
frontend saving cannot explain that whole difference from pipeline 62.




### MariaDB image comparison

On 2026-10-03, the pinned upstream `mariadb:lts` image reported MariaDB
12.3.3. `cimg/mariadb:12.3` was not available, so changing providers would
also change database versions. Keep the current service image for now.

A disposable local comparison used MariaDB 11.8.7 from both providers,
with pinned digests, `linux/amd64`, fresh data directories, identical
`MARIADB_DATABASE`/`MARIADB_ROOT_PASSWORD` variables and authenticated TCP
queries. Three sequential trials per provider alternated execution order;
each verified the server version and an InnoDB write/read. Image pulls were
completed before timing began. Every disposable container and its anonymous
data volume was removed afterwards.

| Local diagnostic | Upstream MariaDB | CircleCI MariaDB |
| --- | --- | --- |
| SQL-ready time, three trials | 7.2s / 7.1s / 4.9s | 7.0s / 10.3s / 11.1s |
| Median SQL-ready time | 7.1s | 10.3s |
| Compressed amd64 layers | 103.8 MB | 451.7 MB |
| Unpacked local image | 453.4 MB | 1,962.8 MB |

The upstream 11.8.7 index digest was
`sha256:78185355dd49b54dd6909072531ce8d7e06aa0eccd7aa5b23c93ebb7e34c5aaa`;
the CircleCI digest was
`sha256:58b31276d40c929fd93efca8e594de457fdc8e79b5f0a27b39638afa02ba51a5`.
CircleCI's image includes its base toolchain; that is useful for a primary
executor but offers no demonstrated gain for this database service. The
Ubuntu bases differ (24.04 upstream, 22.04 CircleCI). These small local
Docker Desktop measurements do not predict hosted pull/cache timings or
full-suite performance. No config change or hosted run was made for this
comparison. Revisit if a matching version becomes available or measurements
identify database readiness as a bottleneck.

References:

- [CircleCI MariaDB image](https://circleci.com/developer/images/image/cimg/mariadb)
- [CircleCI MariaDB Dockerfile](https://github.com/CircleCI-Public/cimg-mariadb/blob/main/11.8/Dockerfile)
- [Playwright Docker and remote connections](https://playwright.dev/docs/docker)
- [PHP orb](https://circleci.com/developer/orbs/orb/circleci/php)
- [Node orb](https://circleci.com/developer/orbs/orb/circleci/node)
- [PHP convenience image](https://circleci.com/developer/images/image/cimg/php)
- [PHP image build specification](https://github.com/CircleCI-Public/cimg-php/blob/main/8.5/Dockerfile)
- [Native Docker executor and services](https://circleci.com/docs/guides/execution-managed/using-docker/)

Later phases: move PR quality checks, cut over required validation checks,
then migrate previews and assess releases separately. Keep GitHub labeling,
merge-hold checks, CodeQL and repository housekeeping on Actions initially.

## Further optimization trials (2026-10-03)

Playwright 1.63.0 is pinned in the root and standalone runner lock files.
The Actions Docker wrapper now derives its version from the root manifest,
and CircleCI checks that both manifests agree. The official widget image
is pinned to the matching release digest.

Widget fixture bundling moved to the frontend build, using the same shared
helper as the default local/Actions test path. CircleCI passes this fixture
through the workspace and installs only the standalone Playwright runner.
The fixture SHA256 matches the previous bundler byte for byte. Both the
minimal runner path and the default bundling path passed all 23 widget tests
locally without retries on 1.63.0.

Redundant-workflow auto-cancellation is already enabled on the active
`gh/FOSSBilling/FOSSBilling` project; no setting was changed.

Each existing job checks documentation-only branches after checkout, using
`circleci-agent step halt` to preserve successful job checks. The allowlist
is limited to root README/contribution/governance documents, this README,
and Markdown under `docs/`. The entire branch diff from the merge base of
freshly fetched canonical FOSSBilling `main` must qualify. Main, manual/API runs, empty diffs,
failed comparisons and mixed/code/config changes always run full validation.
Rename detection is disabled so deleted or moved executable paths remain
visible. All 12 local Git-repository cases passed, including a fork whose own main
contains unreviewed application changes. Run these regression checks with
`python3 .circleci/tests/test-validation-scope.py`. The pipeline parameter
`force-full-validation=true` explicitly overrides filtering. Actions checks,
including spellcheck, remain authoritative.

The application browser executor adds Chromium headless shell and its Linux
dependencies to the existing pinned PHP/Apache image. Live API jobs retain
the smaller Apache-only executor. The Dockerfile uses the locked runner to
install `--with-deps --only-shell chromium` under `/ms-playwright`, readable
by `circleci`; no application source or dependencies are baked into the image.
The runtime checks the browser can launch instead of downloading it.

```sh
docker build --platform linux/amd64 -f .circleci/images/php-apache-playwright/Dockerfile \
  -t fossbilling-circleci-php-apache-playwright:trial .
```

Rebuild and repin this image, the official widget image and both runner lock
files together when upgrading Playwright. Tests continue to use the same
headless Chromium mode, one application worker, two widget workers and the
same retry settings.

All 21 application tests passed without retries in the Apache-only baseline,
full-Chromium image and headless-only image on 1.63.0. Their JUnit test-name
sets match exactly. A deliberately broken prebuilt widget fixture failed
its selected test and retained HTML, screenshot, video and trace artifacts.
Local timings include Docker Desktop emulation and are not hosted performance
claims. The headless-only image is about 747 MB larger unpacked than the
Apache-only image, versus about 1,343 MB for the full-Chromium variant.

Hosted baseline pipeline 71 passed all seven jobs in 72s / 49 credits, with
19.546s installing Chromium, 17.746s running application tests, and 0.484s
installing widget packages. Scope checks took 0.281s in the application job
and 0.346s in the widget job. This updated baseline includes the Playwright
upgrade, prebuilt widget fixture and documentation filter.

Hosted prepared-image pipeline 72 passed all seven jobs in 54s / 41 credits.
A full forced repeat on the same revision, pipeline 73, passed in 59s / 46
credits. First-use browser environment startup was 6.855s versus baseline
5.699s; browser preparation fell from 19.546s to 0.334s, and actual test
execution was 18.505s versus 17.746s. Widget installation remained 0.475s.
These two samples show a net improvement after image startup, while their
5s workflow variation reinforces the need for broader cold/warm sampling.

- [Updated Playwright baseline: pipeline 71](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/71)
- [Prepared Chromium image: pipeline 72](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/72)
- [Same-revision full repeat: pipeline 73](https://app.circleci.com/pipelines/github/FOSSBilling/FOSSBilling/73)

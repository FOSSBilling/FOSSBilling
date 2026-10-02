<!-- cspell:words buildx zstd cimg FPM -->
# CircleCI validation

Each CircleCI pipeline runs the `validation` workflow: PHP 8.3/8.4/8.5 tests,
PHPStan on 8.3, frontend assets/typechecking, and separate live API and browser
jobs. No pilot activation parameter or stage filters remain. GitHub Actions
remain the required checks and the only publisher; cutover is still pending.

Keep experiments local until hosted verification is needed. Once this config
is pushed, the configured CircleCI push trigger will start all six jobs.

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
part of this pilot. Keep Playwright 1.62.1 and its matching browser
for parity with the recorded baseline; reconcile the package-lock version
separately so a dependency update is not mistaken for a migration effect.

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
been removed. Current pipelines run the complete six-job validation workflow
without activation parameters.

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

The `php-tests` job uses CircleCI's matrix syntax to create `php-tests-8.3`,
`php-tests-8.4` and `php-tests-8.5`. The `php-minor` parameter selects one of
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


References:

- [PHP orb](https://circleci.com/developer/orbs/orb/circleci/php)
- [Node orb](https://circleci.com/developer/orbs/orb/circleci/node)
- [PHP convenience image](https://circleci.com/developer/images/image/cimg/php)
- [PHP image build specification](https://github.com/CircleCI-Public/cimg-php/blob/main/8.5/Dockerfile)
- [Native Docker executor and services](https://circleci.com/docs/guides/execution-managed/using-docker/)

Later phases: move PR quality checks, cut over required validation checks,
then migrate previews and assess releases separately. Keep GitHub labeling,
merge-hold checks, CodeQL and repository housekeeping on Actions initially.

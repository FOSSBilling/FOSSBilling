# CircleCI validation pilot

This is an opt-in parallel validation pipeline. GitHub Actions remain the
required checks and the only publisher. The default pipeline parameter
`run-validation-pilot: false` compiles to no jobs. Enable it explicitly for
a pilot run; do not make it the default until the trigger design is verified.

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
redundant-run cancellation is enabled. The project metadata still reported
`master` although GitHub's verified default is `main`. Pilot runs use an
explicit branch; reconcile that metadata before applying PR-only filtering.

References:

- [Integration feature support](https://circleci.com/docs/guides/integration/version-control-system-integration-overview/)
- [Organization types and OAuth permissions](https://circleci.com/docs/guides/permissions-authentication/users-organizations-and-integrations-guide/)
- [OAuth and GitHub App pipelines in one organization](https://circleci.com/docs/guides/integration/using-the-circleci-github-app-in-an-oauth-org/)

## Pilot scope

- Build the existing Dockerfile `test` target for PHP 8.3, 8.4 and 8.5.
- Run PHPStan on 8.3 and the existing Pest unit/module suites on all versions.
- Pass the compressed PHP 8.5 image through a workflow workspace.
- Start live API and Playwright jobs after PHP 8.5 succeeds. PHP 8.3 and 8.4
  remain separate checks; integration success does not imply matrix success.
- Preserve MariaDB, Chromium, one browser worker, the existing browser retry,
  Node 24 typechecking and the admin asset build.
- Collect Pest and Playwright JUnit results and browser failure artifacts.

Linux machine executors preserve Docker Compose networking and host mounts.
The initial resource class is `medium`; compare resource consumption and
credit cost before increasing it. Registry caches are read-only and optional.
There is no registry login, cache export, publishing, deployment context,
Docker layer caching charge, test splitting or additional test coverage.

The browser script still deliberately uses its existing Playwright 1.62.1
container/runner pairing, while the package lock uses 1.63.0. Reconcile that
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
circleci config process .circleci/config.yml --pipeline-parameters 'run-validation-pilot: true'
bash -n .github/scripts/run-docker-live-tests.sh .github/scripts/run-docker-playwright-tests.sh
```

Config compilation verifies the job graph, not Docker execution. Run the
pilot on a dedicated branch after organization/project setup; retain Actions
checks during comparison. Roll back by keeping the parameter false or
disabling the pilot trigger. There are no published outputs to undo.

Local preparation checks on 2026-10-01: CircleCI compiled the default config
to zero jobs and the enabled config to all five intended jobs. Shell syntax
checks passed. The existing local PHP 8.5 image ran 3,795 unit/module tests
with zero failures and four skips, producing JUnit with file/class metadata.
The live report contained 84 tests and one failure in AutoPromoTest's admin
invoice promotion flow (`attach_order` rejected an immutable invoice). The
original committed script reproduced the same failure against that image;
the reporting change is not its cause. This image was not rebuilt for the
pilot, so fresh-image parity and the full browser run remain unverified.

Later phases: move PR quality checks, cut over required validation checks,
then migrate previews and assess releases separately. Keep GitHub labeling,
merge-hold checks, CodeQL and repository housekeeping on Actions initially.

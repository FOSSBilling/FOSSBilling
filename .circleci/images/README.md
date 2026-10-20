<!-- cspell:words cimg BuildKit buildx -->
# Prepared CI images

One Dockerfile builds two images: `apache` contains PHP/Node and Apache;
`browser` extends that exact stage with the Chromium headless shell selected by
the standalone Playwright lock file. Docker Bake builds both targets together.
The old intermediate Docker Hub FROM-digest update is no longer needed.
Validation still uses its existing published image digests until new candidates
have passed API and browser verification and their pins are reviewed.

Preview or build locally from the repository root (the default exporter loads
images locally and does not publish):

```sh
docker buildx bake -f .circleci/images/docker-bake.hcl --print
docker buildx bake -f .circleci/images/docker-bake.hcl
```

Publishing must be explicit. Select a repository and unique release prefix:

```sh
IMAGE_REPOSITORY=docker.io/fossbilling/circleci-php-apache RELEASE=example-001 \
  docker buildx bake -f .circleci/images/docker-bake.hcl \
  --set '*.output=type=registry'
```

Use a source revision and build identifier in release tags. Input fingerprints
alone are insufficient for package refreshes: the same inputs may resolve newer
system packages. Never overwrite an image release tag; runtime pins use digests.

## Hosted maintenance trial

The opt-in `image-maintenance` workflow runs three jobs on fresh remote Docker
VMs (`medium.gen2`), with CircleCI DLC disabled:

1. **Images (Cold publish):** rebuild both stages, publish unique candidate tags
   and export a registry cache with `mode=max`.
2. **Images (Warm publish):** import that registry cache into a fresh builder,
   publish both outputs again and export a separate warm cache.
3. **Images (Browser refresh):** import the warm cache into another fresh builder,
   rebuild and publish only the browser target, and verify it against the warm
   job's Apache image passed through a small workspace. The hosted client's Bake stage-filter override was ignored, so this path
   uses native `buildx build --no-cache-filter browser` with the shared Dockerfile.

Start a trial explicitly after pushing the reviewed source:

```sh
circleci run trigger --project gh/FOSSBilling/FOSSBilling \
  --branch codex/circleci-validation-pilot \
  --parameter run-image-maintenance=true --json
```

Ordinary validation is excluded from this opt-in trial and otherwise runs as
before. The workflow accepts only API triggers on the migration branch or main.
The `docker-hub` context is project-restricted and also requires those versioned
API branch conditions, excluding SSH reruns and unversioned API configuration.
It needs `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` with write access to
`fossbilling/circleci-php-apache`. Credentials are not project variables and are
not attached to contributor validation jobs. The Docker orb authenticates;
Bake handles the shared multi-target build graph.

To resume just a browser refresh against an existing warm trial, additionally set
`image-maintenance-refresh-only=true`, `image-maintenance-cache-source` to its
registry cache reference and `image-maintenance-apache-parent` to its published
Apache candidate. This starts one job and avoids repeating the successful cold
and warm work. Both references must belong to the configured image repository.

Each job stores phase timings, BuildKit logs, OCI metadata, cache descriptors,
published references and smoke-test output under `image-maintenance-<phase>`.
Publishing timings nested inside image exporters overlap; do not sum them.
Cache descriptor sizes describe available cache contents, not necessarily bytes
newly transferred. Cache imports can fetch metadata first and materialize layer
blobs lazily during later operations. Include full build and verification pull
steps when interpreting transfer costs. Insights supplies billed credits and
full job/workflow durations, including setup and artifact work.

The cache uses a trial-specific namespace so measurements cannot overwrite
production caches. Only the browser graph writes each `mode=max` cache; both
targets may read it. Cache export is timed as a separate operation in this trial.
Future production flow may combine it with publication after costs are known.

## Updating and promotion

| Change | Rebuild | Pins after validation |
| --- | --- | --- |
| PHP/Node base digest or Apache definition | Both stages | Apache and browser together |
| Playwright lock or browser definition | Browser instructions | Browser; matching official widget image on Playwright upgrades |
| Full installed-package refresh on unchanged inputs | `--no-cache` for both stages | Both together |

Invalidating only the Apache stage reused an older browser result in the local
trial. Refresh jobs must also prove the browser installation step actually executed.
Every published pair must pass the assertion that Apache's layer list is
an exact prefix of browser's. The exported cache must contain the published browser layers.
Both images must pass PHP/Node/Apache smoke tests
as `circleci`; the browser image must also launch matching Chromium using the
locked standalone runner. The hosted trial stops before pin promotion: full
API/browser suites must validate candidates before a reviewed pin update.

No periodic schedule or automated pin PR has been enabled. Publishing contexts,
cache retention and update triggers can be finalized after hosted measurements.
Trial image/cache tags are retained for inspection; local builder state is removed.

See [the local comparison](../experiments.md#image-maintenance-comparison-2026-10-20)
for measurements and limitations. References:
[Docker multi-stage targets](https://docs.docker.com/build/building/multi-stage/),
[registry cache](https://docs.docker.com/build/cache/backends/registry/).

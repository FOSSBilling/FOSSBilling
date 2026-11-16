# Coordination intermediate release

This is a release-maintainer backport for the intermediate release preceding the
Phase B/C upgrade. It is not an installation migration script. Do not apply it
to a current checkout or ask application users to apply it themselves.

Apply `0.8.9-coordination.patch` to the exact 0.8.9 source pinned in
`tests/Fixtures/upgrades/manifest.json`, assign an intermediate release version,
and build it with that release's dependencies and assets. The backport adds the
bootstrap request/cron gate, drains participating readers before extraction,
uses private download files outside the cache, protects installation configuration
and live lock files from archive replacement,
and defers finalization to the new code. Native PHP ZIP must be enabled. It does
not introduce the Phase B/C database changes. Build from a clean staging tree;
exclude `.update-lock`, `data/uploads/.update-runtime.lock`,
`data/update-finalization.*`, `config.php` and `config.old.php` from the archive,
as in the current release workflows. Runtime state must never ship in a release.

Release routing must offer this intermediate release before offering the Phase
B/C release. Publish and verify the bridge first; do not route old clients
directly to Phase B/C or offer the two releases as an immediate chained update.
Coordination-capable updaters advertise `update_protocol=1` and `current_version`
in the latest-version query. The release service must keep callers without that
capability on the bridge, including installations returning after a long time
offline; a temporary global change of the "latest" release is insufficient.
The service must also enforce the bridge rollout/lifetime condition below
before offering Phase B/C to bridge callers. Publishing and release-service
routing are separate release operations and are not performed by this patch.

The bridge cannot register processes that started before its installation.
Before enabling the next release, the release transition must establish that
those processes have ended. A bounded worker/request lifetime and a sufficiently
long bridge rollout can establish this where those bounds are enforced. There
is no universally safe waiting period for arbitrary third-party PHP/cron
configurations: installations without such bounds need a one-time controlled
stop of old workers. An intermediate release alone cannot prove that an
unregistered, indefinitely running old cron process has finished.

Thereafter, built-in updates automatically wait for participating requests and
cron jobs. All processes accessing one installation must use the same lock
storage with working advisory locks. Multi-node installations with independent
local storage and manual file replacement still need their own coordinated
rollout. Never unlink runtime lock files while application processes are live.

`BridgeUpgradeTest` applies the patch to frozen released sources and checks the
bootstrap gate, extraction, protected paths and new-code finalization handoff.
The main historical upgrade matrix separately imports released SQL/config and
runs the current patcher and finalization, including interruption and retry.

# Upgrade bridge test fixture

`0.8.9-coordination.patch` is a frozen fixture used by
`tests/Unit/FOSSBilling/Core/BridgeUpgradeTest.php`. It adds runtime coordination
to the published 0.8.9 baseline so the tests can exercise an upgrade from a
coordination-capable legacy release.

This patch is not an installation procedure. Users should install published
releases through the built-in updater. The maintenance-branch implementation
was merged into `0.8-next` in [#4699](https://github.com/FOSSBilling/FOSSBilling/pull/4699).

See [Built-in upgrades](../../src/core/Update/UPGRADING.md) for update behavior,
the first transition from older releases and interrupted-extraction recovery.

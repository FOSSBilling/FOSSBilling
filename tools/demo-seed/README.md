# Demo seed

Repeatable, additive test-data seeder for a FOSSBilling instance. All records use a `Demo` prefix so they can be found and removed with the `batch_delete` endpoints.

## Use

```bash
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... php tools/demo-seed/demo-seed.php
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... php tools/demo-seed/demo-seed.php --dry-run
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... php tools/demo-seed/demo-seed.php --limit-clients=5 --limit-orders=5 --limit-tickets=5
```

The admin API key must be provided through `TEST_API_KEY`; command-line arguments can be visible to other users on the system.

Writes `tools/demo-seed/manifest.json` with every created ID plus the shared
test passwords (`DemoPass123!` for clients and demo staff).

## Export / reuse

```bash
ddev export-db --file=.ddev/fossbilling-demo-<date>.sql.gz
ddev import-db --file=.ddev/fossbilling-demo-<date>.sql.gz
```

The SQL dump is the portable snapshot; this script is the repeatable recipe.
Re-running skips existing foundation/catalog records (emails/titles/codes),
but appends new orders, invoices and tickets each time.

## Safety

- Additive only; never wipes.
- `send_welcome_email=0` for clients.
- Only offline-safe gateways (Custom, ClientBalance) are enabled.
- Dummy hosting server uses TEST-NET `192.0.2.10` + Custom manager.

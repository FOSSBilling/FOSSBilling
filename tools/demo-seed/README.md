# Demo seed

Repeatable, additive test-data seeder for a FOSSBilling instance. All records use a `Demo` prefix so they can be found and removed with the `batch_delete` endpoints.

## Use

```bash
APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... php tools/demo-seed/demo-seed.php
php tools/demo-seed/demo-seed.php --url=... --key=... --dry-run
php tools/demo-seed/demo-seed.php --url=... --key=... --limit-clients=5 --limit-orders=5 --limit-tickets=5
```

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

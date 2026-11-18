# Previous-release upgrade fixtures

These are unmodified `structure.sql`, `content.sql`, and `config-sample.php`
files (PHP samples use the inert `.php.txt` suffix) from the repository tags listed in `manifest.json`. The manifest pins
each source commit and SHA-256 digest. They contain sample data, not customer
data. The original SQL dialect is intentional: these releases used MySQL.

The test imports each release into a disposable MySQL/MariaDB database and
invokes the current, real update finalization and patcher in an isolated PHP
process. Module files are copied into the temporary installation so historical
file-removal patches cannot modify the checkout. Composer loads those copied
module classes to keep Doctrine metadata paths consistent.

Coverage includes the documented 0.7.x transition (0.7.2), the two-phase updater
introduced in 0.8.1, and the latest published release used when these fixtures
were added (0.8.9). This is a migration test matrix, not a new support policy.
Both ordinary and interrupted/retried upgrades must preserve customer, invoice,
wallet, ticket, settings, encryption-key, and maintenance-mode data.

Run with `ATTACHMENT_TEST_MYSQL_PARAMS` pointing to a disposable test server.
The account needs permission to create and drop databases. Every test creates
its own randomly named database and removes it in a `finally` block.

The frozen 0.8.9 bootstrap and updater also validate the intermediate-release
backport. Their source hashes are pinned in the same manifest.

# MySQL/MariaDB Unicode support

FOSSBilling defaults MySQL/MariaDB connections and newly generated Doctrine tables to `utf8mb4`. This supports all Unicode characters, including emoji. Newly created databases also use `utf8mb4`. An explicit `db.charset` in `config.php` overrides the connection default; remove a legacy `utf8` override or change it to `utf8mb4` to enable full Unicode. PostgreSQL and SQLite settings are unaffected.

Changing a connection or database default does not convert existing columns. Patch 128 repairs the Custompages `title`, `description`, `keywords` and `content` columns when they use `utf8`/`utf8mb3`. It preserves column types, nullability, comments and the corresponding collation family (for example, `utf8_bin` becomes `utf8mb4_bin`). It leaves indexed `slug` values and their uniqueness rules unchanged. Other tables are not converted by this patch.

The migration skips fields with custom indexes, generated expressions, non-null defaults, unsupported types or collations without a supported `utf8mb4` counterpart. These skips are logged in the update log. Non-UTF-8 columns are left unchanged. Successful conversions are idempotent; an ALTER failure stops patching so the update can be retried after addressing the reported database error.

## Existing installations that still reject characters

Back up the database before changing column definitions. Inspect the actual definitions and indexes first:

```sql
SHOW FULL COLUMNS FROM custom_pages;
SHOW CREATE TABLE custom_pages;
SHOW INDEX FROM custom_pages;
SELECT @@character_set_client, @@character_set_connection, @@character_set_results;
```

Both the application connection and the destination column must support `utf8mb4`. Run the session query through the application connection when diagnosing it: a database administration tool may use a different charset.

For a standard, unindexed `content TEXT NOT NULL` column with no default or comment and the `utf8_general_ci` collation, the targeted repair is:

```sql
ALTER TABLE custom_pages
    MODIFY COLUMN content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
```

Adapt this statement to preserve the actual type, nullability, default, comment and collation family on a customized installation. Do not copy it over a different definition. Conversion may rebuild or lock the table; schedule it accordingly. Avoid converting the whole database or table just to repair page content: indexed columns, foreign keys and unique-key equality require separate review, and `CONVERT TO CHARACTER SET` can enlarge text types.

## Regression tests

`tests/E2E/Database/CustompagesCharsetTest.php` runs against an isolated disposable MySQL/MariaDB server on localhost. Set `CHARSET_TEST_MYSQL_PORT` to its port; the server must allow root access with an empty password. Tests create randomly named databases and drop only those databases afterward. Without this variable the tests are skipped.

```sh
CHARSET_TEST_MYSQL_PORT=3307 composer test -- tests/E2E/Database/CustompagesCharsetTest.php
```

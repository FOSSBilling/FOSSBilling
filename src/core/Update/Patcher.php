<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update;

use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\Driver;
use FOSSBilling\Core\Doctrine\DriverManagerFactory;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Doctrine\ModuleEntityScope;
use FOSSBilling\Core\Doctrine\SchemaSynchronizer;
use FOSSBilling\Core\Exception\BaseException;
use FOSSBilling\Core\Security\Crypt;
use FOSSBilling\Core\System\Config;
use FOSSBilling\Core\System\Environment;
use FOSSBilling\Core\System\Version;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

class Patcher implements InjectionAwareInterface
{
    private const string SCHEMA_METADATA_HASH_PARAM = 'schema_metadata_hash';
    private const string SCHEMA_METADATA_HASH_FAILED_PARAM = 'schema_metadata_hash_failed';
    private const int SCHEMA_SYNC_RETRY_COOLDOWN = 3600;
    public ?\Pimple\Container $di = null;
    public Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function availablePatches(): int
    {
        // These are MySQL/MariaDB-only patches (see applyCorePatches()) - never "pending" on
        // another platform, regardless of what the last_patch bookkeeping row says.
        if (!$this->isLegacyPatchDriver()) {
            return 0;
        }

        $patchLevel = $this->getPatchLevel();
        $patches = $this->getPatches($patchLevel);

        return count($patches);
    }

    public function latestPatchLevel(): int
    {
        $patches = $this->getPatches();
        $latestPatchLevel = array_key_last($patches);

        return is_int($latestPatchLevel) ? $latestPatchLevel : 0;
    }

    /**
     * Apply configuration file patches.
     */
    public function applyConfigPatches(bool $force = false): void
    {
        // Legacy auto-updaters call this after extracting new files.
        // Make it no-op unless the request is coming from the new post-update hello screen.
        // This makes old versions automatically defer to the new hello screen without running the patches.
        if (!$force) {
            return;
        }

        $currentConfig = Config::getConfig();

        if (empty($currentConfig)) {
            throw new BaseException('Unable to load existing configuration');
        }

        $newConfig = $currentConfig;
        $newConfig['security'] ??= [];
        $newConfig['security']['mode'] ??= 'strict';
        $newConfig['security']['force_https'] ??= true;
        $newConfig['security']['trusted_proxies'] ??= [];
        $newConfig['security']['trusted_proxies']['enabled'] ??= false;
        $newConfig['security']['trusted_proxies']['proxies'] ??= [];
        $newConfig['security']['trusted_proxies']['headers'] ??= 'x_forwarded';
        $newConfig['security']['session_lifespan'] ??= $newConfig['security']['cookie_lifespan'] ?? 7200;
        $newConfig['security']['session_regeneration_grace_period'] ??= 300;
        $newConfig['security']['perform_session_fingerprinting'] ??= true;
        $newConfig['security']['debug_fingerprint'] ??= false;
        $newConfig['update_branch'] ??= 'release';
        $newConfig['log_stacktrace'] ??= true;
        $newConfig['stacktrace_length'] ??= 25;
        $newConfig['maintenance_mode']['enabled'] ??= false;
        $newConfig['maintenance_mode']['allowed_urls'] ??= [];
        $newConfig['maintenance_mode']['allowed_ips'] ??= [];
        $newConfig['disable_auto_cron'] = !Version::isPreviewVersion() && !Environment::isDevelopment();
        $newConfig['i18n']['locale'] ??= $currentConfig['locale'] ?? 'en_US';
        $newConfig['i18n']['auto_detect_locale'] ??= true;
        $newConfig['i18n']['timezone'] ??= $currentConfig['timezone'] ?? 'UTC';
        $newConfig['i18n']['date_format'] ??= 'medium';
        $newConfig['i18n']['time_format'] ??= 'short';
        $newConfig['db']['driver'] ??= 'pdo_mysql';
        $rawDriver = $newConfig['db']['driver'] ?? 'pdo_mysql';
        $driver = Driver::tryFromAlias($rawDriver);
        $normalizedDriver = $driver instanceof Driver ? $driver->value : $rawDriver;
        $newConfig['db']['driver'] = $normalizedDriver;
        if ($driver instanceof Driver && $driver->isSqlite()) {
            unset($newConfig['db']['port']);
        } else {
            $defaultPort = $driver instanceof Driver && $driver->defaultPort() !== null ? $driver->defaultPort() : 3306;
            $newConfig['db']['port'] = \FOSSBilling\Core\Utils\Normalizer::normalizePort($newConfig['db']['port'] ?? null, $defaultPort);
        }
        unset(
            $newConfig['api']['rate_span'],
            $newConfig['api']['rate_limit'],
            $newConfig['api']['throttle_delay'],
            $newConfig['api']['rate_span_login'],
            $newConfig['api']['rate_limit_login'],
            $newConfig['api']['rate_limit_whitelist'],
        );
        $newConfig['api']['CSRFPrevention'] ??= true;
        $newConfig['rate_limiter']['enabled'] ??= true;
        $newConfig['rate_limiter']['whitelist_ips'] ??= [];
        $newConfig['rate_limiter']['policies'] ??= [];
        $newConfig['rate_limiter']['whitelist_ips'] = array_values(array_unique(array_merge($newConfig['rate_limiter']['whitelist_ips'], $currentConfig['api']['rate_limit_whitelist'] ?? [])));
        $newConfig['debug_and_monitoring'] ??= [];
        $newConfig['debug_and_monitoring']['debug'] ??= $newConfig['debug'] ?? false;
        $newConfig['debug_and_monitoring']['log_stacktrace'] ??= $newConfig['log_stacktrace'];
        $newConfig['debug_and_monitoring']['stacktrace_length'] ??= $newConfig['stacktrace_length'];
        $newConfig['debug_and_monitoring']['report_errors'] ??= false;

        // Instance ID handling
        $this->refreshComposerAutoloader();
        $newConfig['info']['instance_id'] ??= Uuid::v4()->toString();
        $newConfig['info']['salt'] ??= $newConfig['salt'];

        // Remove the hardcoded protocol
        $newConfig['url'] = str_replace(['https://', 'http://'], '', $newConfig['url']);

        // Remove deprecated config keys/subkeys.
        $deprecatedConfigKeys = ['guzzle', 'locale', 'locale_date_format', 'locale_time_format', 'timezone', 'sef_urls', 'salt', 'path_logs', 'log_to_db'];
        $deprecatedConfigSubkeys = [
            'security' => 'cookie_lifespan',
            'db' => 'type',
        ];
        $newConfig = array_diff_key($newConfig, array_flip($deprecatedConfigKeys));
        foreach ($deprecatedConfigSubkeys as $key => $subkey) {
            unset($newConfig[$key][$subkey]);
        }

        if ($currentConfig === $newConfig) {
            return;
        }

        Config::setConfig($newConfig);
    }

    /**
     * Apply all relevant patches to current FOSSBilling instance.
     */
    public function applyCorePatches(bool $force = false): void
    {
        // See applyConfigPatches(): no-argument calls are deferred to the new post-update screen.
        if (!$force) {
            return;
        }

        // The patches below are raw MySQL/MariaDB DDL (backtick identifiers, ENGINE=, SHOW COLUMNS
        // introspection, ...) with no PostgreSQL/SQLite equivalent, and
        // several of them are one-time data transformations tied to a specific historical release
        // (splitting/merging tables, rewriting existing rows) that can't be ported by rewriting SQL
        // syntax alone. Porting all of that is out of scope; see SchemaSynchronizer's docblock. On
        // PostgreSQL/SQLite there is nothing here to run at all.
        //
        // This guard matters beyond "there's nothing to run": getPatchLevel() returning null (e.g.
        // a restored/cloned database missing its `setting` row for last_patch) makes getPatches()
        // treat every patch as pending. Without this check, that combined with a missing/stale
        // update-finalization state would make the very next page load - see
        // UpdateFinalization::finalizePendingUpdate(), called unconditionally from every request -
        // start executing MySQL-only DDL against a non-MySQL database.
        if ($this->isLegacyPatchDriver()) {
            $patchLevel = $this->getPatchLevel();
            $patches = $this->getPatches($patchLevel);
            foreach ($patches as $patchLevel => $patch) {
                call_user_func($patch, $this);
                $this->setPatchLevel($patchLevel);
            }
        }

        // Portable (plain UPDATE ... WHERE, no MySQL-specific syntax) and idempotent, so it
        // runs on every platform rather than being folded into the MySQL-only patch loop above -
        // a PostgreSQL/SQLite install predating this change never runs Patch115 at all, and
        // would otherwise be left with a theme that never got renamed and orphaned saved settings
        // forever.
        $this->migrateThemePackageLayout();

        // Retired hook packages and listener registrations have no runtime consumer. Remove
        // their records on every driver so old installs do not retain invisible extensions.
        $this->removeRetiredHookData();

        // Same treatment for the debit-note settings rows content.sql seeds for fresh installs:
        // plain check-then-insert SQL, idempotent, so every platform gets them even though no
        // MySQL-only patch can run there.
        $this->seedInvoiceNoteSettings();

        // Portable invoice settings/rename steps, shared with the drift healer.
        $this->applyPortableInvoiceMigrations();

        // Additive structural sync runs on every platform, MySQL/MariaDB included: it picks up any
        // column/table/index that's on entity metadata but not yet applied, without needing a
        // hand-written patch for it - the only mechanism at all on PostgreSQL/SQLite, and on
        // MySQL/MariaDB a catch-all for anything the patches above didn't (or, going forward, for
        // structural changes that land on metadata without a patch being written at all).
        $this->syncPortableSchema();

        // Baseline the invoice journal on every driver, after the sync above: on non-MySQL
        // installs the invoice_event table only comes into existence there, and backfilling
        // first would find no table and leave existing invoices without baseline entries.
        // Deliberately outside the drift healer: a large backlog must not stall page loads.
        $this->backfillInvoiceJournal();
    }

    /**
     * Whether the legacy MySQL-only patch loop should run.
     *
     * The historical patches 25-116 are raw MySQL/MariaDB DDL — the only platform
     * {@see self::applyCorePatches()}'s legacy SQL patches are written for.
     */
    public function isLegacyPatchDriver(): bool
    {
        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? '');

            return $driver?->isMysql() === true;
        } catch (\Throwable) {
            // Can't determine the driver - don't guess. Fail safe by not running MySQL-only DDL.
            return false;
        }
    }

    /**
     * Brings the live schema up to date with current Doctrine entity metadata - see
     * {@see SchemaSynchronizer} for exactly what this does and does not cover (additive structural
     * changes only, never a substitute for the legacy patches' data transformations).
     *
     * Scoped to core-module entities plus whichever extensions are currently marked installed
     * ({@see ModuleEntityScope::isEagerNow()}) - the same gating {@see \FOSSBilling\Core\Doctrine\
     * SchemaInstaller} applies at fresh-install time. Running the unscoped {@see SchemaSynchronizer::
     * sync()} here instead would undo that gating: it compares every entity's table
     * unconditionally, so an inactive extension's table (custom_pages, mod_massmailer,
     * service_apikey, or any future one) would get silently recreated by this method - as if it
     * were activated - regardless of whether anyone ever installs that extension.
     *
     * Errors are logged, not thrown: this runs on every request via UpdateFinalization, and a
     * database this can't reach (or a metadata error) should degrade to "nothing changed", the same
     * outcome as before this method existed, rather than breaking the request.
     */
    public function syncPortableSchema(): ?array
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('em')) {
            return null;
        }

        $entityManager = $this->di['em'];

        // Scope discovery (the connection, the installed-extensions query, metadata loading) can
        // throw for the same reasons the sync itself can - an unreachable database above all -
        // so it has to share this method's one error boundary, not run ahead of it. Only the sync
        // itself used to be able to throw, back when this called SchemaSynchronizer::sync() with
        // no scope discovery beforehand at all.
        try {
            $connection = $entityManager->getConnection();

            // Fetched once and reused for every entity below, rather than one query per entity -
            // an unbounded number of extra queries per non-core module isn't a cost worth paying
            // just to derive a handful of booleans.
            $installedExtensionModules = ModuleEntityScope::installedExtensionModules($connection);

            $eagerEntityClasses = array_values(array_filter(
                array_map(
                    static fn ($classMetadata): string => $classMetadata->getName(),
                    $entityManager->getMetadataFactory()->getAllMetadata(),
                ),
                static function (string $entityClass) use ($installedExtensionModules): bool {
                    $module = ModuleEntityScope::moduleForEntityClass($entityClass);

                    return $module === null || ModuleEntityScope::isEagerNow($module, $installedExtensionModules);
                },
            ));

            if ($eagerEntityClasses === []) {
                return null;
            }

            $result = SchemaSynchronizer::syncEntities($entityManager, $eagerEntityClasses);
            $this->migrateClientGroupMemberships($connection);
            $this->storeSchemaMetadataHash(EntityManagerFactory::entityDefinitionsHash());
        } catch (\Throwable $e) {
            // Attach the metadata hash so the failure can be correlated with
            // the cooldown row (see lastSchemaSyncFailure()).
            $metadataHash = null;

            try {
                $metadataHash = EntityManagerFactory::entityDefinitionsHash();
            } catch (\Throwable) {
                // Hashing must not mask the original sync error.
            }

            $this->logUpdate('error', 'Schema sync against the configured database failed: ' . $e->getMessage(), [
                'metadata_hash' => $metadataHash,
            ]);

            return null;
        }

        if ($result['applied'] !== []) {
            $this->logUpdate('info', 'Synced database schema with current entity metadata.', ['statements' => $result['applied']]);
        }

        // Never one log line per skipped item: on MySQL especially, entity metadata and the live
        // schema can differ in ways that were never meant to be applied (see SchemaSynchronizer's
        // "never touches" guarantees) and there can legitimately be hundreds of them - logging each
        // on every request this runs would be pure noise. A single rolled-up count, with the detail
        // attached as structured context rather than the message, keeps this useful without
        // flooding the log.
        if ($result['skipped'] !== []) {
            $this->logUpdate(
                'info',
                sprintf('Schema sync left %d existing structural difference(s) from entity metadata untouched.', count($result['skipped'])),
                ['skipped' => $result['skipped']],
            );
        }

        return $result;
    }

    /**
     * Execute actions against the provided directories and files.
     *
     * @param array $files Array containing files and directories to perform action on and
     *                     the actions to perform. Valid options are 'rename' and 'unlink'.
     */
    public function executeFileActions(array $files): void
    {
        foreach ($files as $file => $action) {
            try {
                if ($action === 'unlink' && $this->filesystem->exists($file)) {
                    $this->filesystem->remove($file);
                } elseif ($this->filesystem->exists($file)) {
                    $this->filesystem->rename($file, $action);
                }
            } catch (IOException $e) {
                $this->logUpdate('error', $e->getMessage());
            }
        }
    }

    public function getPdo(): \PDO
    {
        // The first request after updating from 0.7.x still uses the old Composer autoloader.
        // Use PDO here because it is available before and after the archive is extracted.
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('pdo')) {
            throw new BaseException('Database connection is not available.');
        }

        return $this->di['pdo'];
    }

    public function prepareAndExecute(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Execute the given SQL statement.
     *
     * @param $sql The SQL statement to execute
     */
    public function executeSql(string $sql, array $params = []): void
    {
        try {
            $this->prepareAndExecute($sql, $params);
        } catch (\Exception $e) {
            // Log the error and then throw a user-friendly exception to prevent further patches from being applied.
            $this->logUpdate('error', $e->getMessage());

            throw new BaseException('There was an error while applying database patches. Please check the error log for information on the error, correct it, and then perform the backup patching method to complete the update.');
        }
    }

    public function logUpdate(string $level, string $message, array $context = []): void
    {
        try {
            if ($this->di instanceof \Pimple\Container && $this->di->offsetExists('logger')) {
                $this->di['logger']->withChannel('update')->log($level, $message, $context);

                return;
            }
        } catch (\Throwable) {
            // Logging must not hide the patch failure when the session schema
            // is still being migrated and the normal logger cannot initialize.
        }

        error_log('FOSSBilling update: ' . $message);
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->prepareAndExecute($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchOne(string $sql, array $params = []): mixed
    {
        return $this->prepareAndExecute($sql, $params)->fetchColumn();
    }

    public function fetchFirstColumn(string $sql, array $params = []): array
    {
        return $this->prepareAndExecute($sql, $params)->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function fetchKeyValue(string $sql, array $params = []): array
    {
        return $this->prepareAndExecute($sql, $params)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function updateTable(string $table, array $data, array $criteria): void
    {
        $set = [];
        $where = [];
        $params = [];

        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? 'pdo_mysql');
        } catch (\Throwable) {
            $driver = Driver::PdoMysql;
        }
        $isMysql = $driver?->isMysql() === true;
        $quote = static fn (string $id): string => $isMysql ? sprintf('`%s`', $id) : sprintf('"%s"', $id);

        foreach ($data as $column => $value) {
            $placeholder = "set_{$column}";
            $set[] = sprintf('%s = :%s', $quote($this->quoteIdentifier($column)), $placeholder);
            $params[$placeholder] = $value;
        }

        foreach ($criteria as $column => $value) {
            $placeholder = "where_{$column}";
            $where[] = sprintf('%s = :%s', $quote($this->quoteIdentifier($column)), $placeholder);
            $params[$placeholder] = $value;
        }

        $this->executeSql(
            sprintf('UPDATE %s SET %s WHERE %s', $quote($this->quoteIdentifier($table)), implode(', ', $set), implode(' AND ', $where)),
            $params
        );
    }

    public function tableHasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->getTableColumns($table), true);
    }

    public function tableExists(string $table): bool
    {
        $this->quoteIdentifier($table);

        if ($this->di instanceof \Pimple\Container && $this->di->offsetExists('em')) {
            try {
                $connection = $this->di['em']->getConnection();
                $schemaManager = $connection->createSchemaManager();

                return $schemaManager->tablesExist([$table]);
            } catch (\Throwable) {
                // fall through to driver-specific query
            }
        }

        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? 'pdo_mysql');
        } catch (\Throwable) {
            $driver = Driver::PdoMysql;
        }

        if ($driver === Driver::PdoPgsql) {
            return (bool) $this->fetchOne(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :table LIMIT 1",
                ['table' => $table],
            );
        }

        if ($driver === Driver::PdoSqlite) {
            return (bool) $this->fetchOne(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1",
                ['table' => $table],
            );
        }

        return (bool) $this->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1',
            ['table' => $table],
        );
    }

    public function computeInvoiceHashExpiration(): ?string
    {
        $value = $this->fetchOne("SELECT value FROM setting WHERE param = 'invoice_hash_lifetime_days'");
        $days = is_string($value) && $value !== '' ? (int) $value : 90;
        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }

    public function getTableColumns(string $table): array
    {
        $this->quoteIdentifier($table);

        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? 'pdo_mysql');
        } catch (\Throwable) {
            $driver = Driver::PdoMysql;
        }

        if ($driver?->isMysql() !== true) {
            if ($this->di instanceof \Pimple\Container && $this->di->offsetExists('em')) {
                try {
                    $schemaManager = $this->di['em']->getConnection()->createSchemaManager();
                    if (!$schemaManager->tablesExist([$table])) {
                        return [];
                    }
                    $columns = $schemaManager->listTableColumns($table);

                    return array_map(static fn ($col) => $col->getName(), array_values($columns));
                } catch (\Throwable) {
                    // fall through
                }
            }

            if ($driver === Driver::PdoPgsql) {
                return $this->fetchFirstColumn(
                    "SELECT column_name FROM information_schema.columns WHERE table_name = :table AND table_schema = 'public' ORDER BY ordinal_position",
                    ['table' => $table],
                );
            }

            if ($driver === Driver::PdoSqlite) {
                $rows = $this->fetchAll('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');

                return array_map(static fn (array $r): string => (string) ($r['name'] ?? ''), $rows);
            }
        }

        $columns = $this->fetchAll(sprintf('SHOW COLUMNS FROM `%s`', $this->quoteIdentifier($table)));

        return array_map(static fn (array $column): string => (string) $column['Field'], $columns);
    }

    public function getColumnLength(string $table, string $column): ?int
    {
        $this->quoteIdentifier($table);
        $this->quoteIdentifier($column);

        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? 'pdo_mysql');
        } catch (\Throwable) {
            $driver = Driver::PdoMysql;
        }

        if ($driver?->isMysql() !== true) {
            if ($this->di instanceof \Pimple\Container && $this->di->offsetExists('em')) {
                try {
                    $schemaManager = $this->di['em']->getConnection()->createSchemaManager();
                    if (!$schemaManager->tablesExist([$table])) {
                        return null;
                    }
                    $columns = $schemaManager->listTableColumns($table);
                    foreach ($columns as $col) {
                        if (strcasecmp((string) $col->getName(), $column) === 0) {
                            return $col->getLength();
                        }
                    }

                    return null;
                } catch (\Throwable) {
                    // fall through
                }
            }

            return null;
        }

        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM `%s` LIKE :column', $this->quoteIdentifier($table)), [
            'column' => $column,
        ]);

        if ($rows === []) {
            return null;
        }

        preg_match('/\((\d+)\)/', (string) $rows[0]['Type'], $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    public function getColumnType(string $table, string $column): ?string
    {
        $this->quoteIdentifier($table);
        $this->quoteIdentifier($column);

        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? 'pdo_mysql');
        } catch (\Throwable) {
            $driver = Driver::PdoMysql;
        }

        if ($driver?->isMysql() !== true) {
            if ($this->di instanceof \Pimple\Container && $this->di->offsetExists('em')) {
                try {
                    $schemaManager = $this->di['em']->getConnection()->createSchemaManager();
                    if (!$schemaManager->tablesExist([$table])) {
                        return null;
                    }
                    $columns = $schemaManager->listTableColumns($table);
                    foreach ($columns as $col) {
                        if (strcasecmp((string) $col->getName(), $column) === 0) {
                            $type = $col->getType()->getName();
                            $length = $col->getLength();

                            return $length !== null ? sprintf('%s(%d)', $type, $length) : $type;
                        }
                    }

                    return null;
                } catch (\Throwable) {
                    // fall through
                }
            }

            return null;
        }

        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM `%s` LIKE :column', $this->quoteIdentifier($table)), [
            'column' => $column,
        ]);

        return $rows === [] ? null : (string) $rows[0]['Type'];
    }

    public function tableHasIndex(string $table, string $indexName): bool
    {
        $this->quoteIdentifier($table);

        try {
            $driver = Driver::tryFrom(DriverManagerFactory::getDatabaseConfig()['driver'] ?? 'pdo_mysql');
        } catch (\Throwable) {
            $driver = Driver::PdoMysql;
        }

        if ($driver?->isMysql() !== true) {
            if ($this->di instanceof \Pimple\Container && $this->di->offsetExists('em')) {
                try {
                    $schemaManager = $this->di['em']->getConnection()->createSchemaManager();
                    if (!$schemaManager->tablesExist([$table])) {
                        return false;
                    }
                    $indexes = $schemaManager->listTableIndexes($table);
                    foreach ($indexes as $name => $index) {
                        if (strcasecmp((string) $name, $indexName) === 0 || strcasecmp((string) $index->getName(), $indexName) === 0) {
                            return true;
                        }
                    }

                    return false;
                } catch (\Throwable) {
                    // fall through
                }
            }

            if ($driver === Driver::PdoPgsql) {
                return (bool) $this->fetchOne(
                    "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND tablename = :table AND indexname = :index LIMIT 1",
                    ['table' => $table, 'index' => $indexName],
                );
            }

            if ($driver === Driver::PdoSqlite) {
                return (bool) $this->fetchOne(
                    "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :index LIMIT 1",
                    ['table' => $table, 'index' => $indexName],
                );
            }
        }

        $indexes = $this->fetchAll(sprintf('SHOW INDEX FROM `%s`', $this->quoteIdentifier($table)));
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    public function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new BaseException('Invalid database identifier: :identifier', [':identifier' => $identifier]);
        }

        return $identifier;
    }

    public function migrateEncryptedColumn(string $table, string $idColumn, string $valueColumn, string $where, array $params = []): void
    {
        $rawTable = $table;
        $rawIdColumn = $idColumn;
        $rawValueColumn = $valueColumn;
        $quotedTable = $this->quoteIdentifier($table);
        $idColumn = $this->quoteIdentifier($idColumn);
        $valueColumn = $this->quoteIdentifier($valueColumn);

        // This method expects a static SQL predicate fragment with bound parameters in $params.
        if (
            str_contains($where, ';')
            || str_contains($where, '--')
            || str_contains($where, '/*')
            || str_contains($where, '*/')
            || str_contains($where, '`')
            || !preg_match('/^[A-Za-z0-9_:\\s<>=!().,%+-]++$/', $where)
        ) {
            throw new BaseException('Invalid SQL WHERE clause fragment.');
        }

        $rows = $this->fetchAll("SELECT {$idColumn} AS id, {$valueColumn} AS encrypted_value FROM {$quotedTable} WHERE {$where}", $params);

        /** @var Crypt $crypt */
        $crypt = $this->di['crypt'];
        $salt = Config::getProperty('info.salt');

        $hasUpdatedAt = $this->tableHasColumn($rawTable, 'updated_at');

        foreach ($rows as $row) {
            $encryptedValue = $row['encrypted_value'] ?? null;
            if (!is_string($encryptedValue) || $encryptedValue === '' || str_starts_with($encryptedValue, Crypt::CURRENT_FORMAT_PREFIX)) {
                continue;
            }

            $decryptedValue = $crypt->decrypt($encryptedValue, $salt);
            if ($decryptedValue === false) {
                continue;
            }

            $updateData = [$rawValueColumn => $crypt->encrypt($decryptedValue, $salt)];
            if ($hasUpdatedAt) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
            }

            $this->updateTable($table, $updateData, [
                $rawIdColumn => $row['id'],
            ]);
        }
    }

    /**
     * Get the current patch level of FOSSBilling.
     *
     * @return int|null the current patch level
     */
    public function getPatchLevel(): ?int
    {
        $value = $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
            'param' => 'last_patch',
        ]);

        return intval($value) ?: null;
    }

    /**
     * Set the current patch level of FOSSBilling.
     *
     * @param int $patchLevel The last executed patch level
     */
    public function setPatchLevel(int $patchLevel): void
    {
        $now = date('Y-m-d H:i:s');

        $exists = $this->fetchOne('SELECT 1 FROM setting WHERE param = :param LIMIT 1', [
            'param' => 'last_patch',
        ]);

        if ($exists) {
            $this->executeSql(
                'UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param',
                [
                    'param' => 'last_patch',
                    'value' => $patchLevel,
                    'updated_at' => $now,
                ]
            );
        } else {
            $this->executeSql(
                'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)',
                [
                    'param' => 'last_patch',
                    'value' => $patchLevel,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Get patches to be applied.
     *
     * @param int|null $patchLevel the current patch level of FOSSBilling
     *
     * @return array array containing the patches to be executed, in order
     */
    private function getPatches(?int $patchLevel = 0): array
    {
        $patches = Patch\PatchRegistry::MAP;
        ksort($patches, SORT_NATURAL);

        $patchesToApply = array_filter($patches, fn ($key): bool => $key > $patchLevel, ARRAY_FILTER_USE_KEY);

        return array_map(fn (string $patchClass): array => [new $patchClass(), 'apply'], $patchesToApply);
    }

    public function refreshComposerAutoloader(): void
    {
        $uuidClass = Uuid::class;

        if (!class_exists($uuidClass)) {
            $autoloadPath = Path::join(PATH_VENDOR, 'autoload.php');
            if ($this->filesystem->exists($autoloadPath)) {
                require $autoloadPath;
            }
        }

        if (!class_exists($uuidClass)) {
            $this->registerSymfonyUidAutoloader();
        }

        if (!class_exists($uuidClass)) {
            throw new BaseException('Unable to load the Symfony UID package from Composer. Please reinstall dependencies and try again.');
        }
    }

    public function registerSymfonyUidAutoloader(): void
    {
        $uidPath = Path::join(PATH_VENDOR, 'symfony', 'uid');
        if (!$this->filesystem->exists($uidPath)) {
            return;
        }

        spl_autoload_register(function (string $class) use ($uidPath): void {
            $prefix = 'Symfony\\Component\\Uid\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $path = Path::join($uidPath, str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php');
            if ($this->filesystem->exists($path)) {
                require $path;
            }
        });
    }

    /**
     * Remove obsolete directories only when they contain no files, including hidden files.
     *
     * @param list<string> $directories
     */
    public function removeEmptyDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $finder = (new Finder())
                ->in($directory)
                ->depth('== 0')
                ->ignoreDotFiles(false)
                ->ignoreVCS(false);

            if ($finder->hasResults()) {
                continue;
            }

            // rmdir is intentional here: Filesystem::remove() is recursive and could
            // delete a file created between the emptiness check and the removal.
            if (!@rmdir($directory)) {
                $this->logUpdate('warning', sprintf('Unable to remove empty obsolete directory "%s".', $directory));
            }
        }
    }

    /**
     * Bundles the shipped themes into one package: admin_default -> default/admin,
     * huraga -> default/client. Third-party themes are untouched.
     *
     * Unlike the raw-DDL patches, this is plain, portable SQL (no backticks,
     * ENGINE=, or ON DUPLICATE KEY UPDATE) and a filesystem rename - neither is
     * MySQL-specific, so this is called both from Patch115 (for MySQL/MariaDB's
     * sequential patch-level bookkeeping) and unconditionally from
     * applyCorePatches() above, so PostgreSQL/SQLite installs - which never run the
     * patch loop at all - still get migrated. Every step is idempotent, so
     * running it twice on a MySQL/MariaDB install (once via Patch115, once via
     * the unconditional call) is a harmless no-op the second time.
     */
    public function migrateThemePackageLayout(): void
    {
        $filesystem = $this->filesystem;

        // Each shipped theme's old code, new code, and the setting param that selects it.
        $renames = [
            'admin_default' => ['newCode' => 'default/admin', 'settingParam' => 'admin_theme'],
            'huraga' => ['newCode' => 'default/client', 'settingParam' => 'theme'],
        ];

        foreach ($renames as $oldCode => $rename) {
            $oldPath = Path::join(PATH_THEMES, $oldCode);
            $newPath = Path::join(PATH_THEMES, $rename['newCode']);

            if ($filesystem->exists($oldPath) && !$filesystem->exists($newPath)) {
                $filesystem->mkdir(Path::getDirectory($newPath));
                $filesystem->rename($oldPath, $newPath);
            }

            // A code-only deploy (e.g. `git pull`) already moves every tracked file via the
            // checkout itself, before this ever runs - the rename above then finds $newPath
            // already there and skips. What's left behind at $oldPath at that point is mostly
            // gitignored leftovers (a rebuilt assets/build/, huraga's config/settings_data.json
            // cache, which regenerates on its own - the setting it holds is now in the database,
            // migrated below), but TwigLoader's `html_custom` override directory and extra files
            // dropped into `custom-icons` are genuinely untracked local customizations a checkout
            // never touches - discarding $oldPath outright would destroy them. Mirror anything not
            // already present at $newPath over first (never overwriting what the checkout already
            // placed there) so those customizations survive the rename, then discard what's left.
            if ($filesystem->exists($oldPath) && $filesystem->exists($newPath)) {
                $filesystem->mirror($oldPath, $newPath, null, ['override' => false]);
                $filesystem->remove($oldPath);
            }

            // Safe/no-op if the row doesn't currently hold the old value.
            $this->executeSql('UPDATE setting SET value = :new_value WHERE param = :param AND value = :old_value', [
                'new_value' => $rename['newCode'],
                'param' => $rename['settingParam'],
                'old_value' => $oldCode,
            ]);

            // Saved theme settings/presets live in extension_meta, keyed by the theme's
            // name string (Theme\Service::updateSettings()/setCurrentThemePreset()) -
            // 'settings' rows in rel_id, the 'preset'/'current' row in meta_key. Without
            // this, a staff member's customized theme settings would silently fall back
            // to the shipped defaults once the theme is renamed.
            $this->executeSql("UPDATE extension_meta SET rel_id = :new_code WHERE extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :old_code", [
                'new_code' => $rename['newCode'],
                'old_code' => $oldCode,
            ]);
            $this->executeSql("UPDATE extension_meta SET meta_key = :new_code WHERE extension = 'mod_theme' AND rel_type = 'preset' AND rel_id = 'current' AND meta_key = :old_code", [
                'new_code' => $rename['newCode'],
                'old_code' => $oldCode,
            ]);
        }
    }

    /**
     * Reports the database patch level against the code's patch list - the
     * shared source of truth behind availablePatches() for callers that need
     * the levels themselves (e.g. the finalization completion guard).
     *
     * @return array{current: ?int, latest: int, pending: ?int} pending is null
     *                                                          when the count cannot be determined (non-MySQL platform or unreadable database)
     */
    public function patchStatus(): array
    {
        $latest = $this->latestPatchLevel();

        if (!$this->isLegacyPatchDriver()) {
            return [
                'current' => null,
                'latest' => $latest,
                'pending' => null,
            ];
        }

        try {
            $current = $this->getPatchLevel();
            $pending = count($this->getPatches($current));
        } catch (\Throwable) {
            return [
                'current' => null,
                'latest' => $latest,
                'pending' => null,
            ];
        }

        return [
            'current' => $current,
            'latest' => $latest,
            'pending' => $pending,
        ];
    }

    /**
     * Brings the live schema up to date with current Doctrine entity metadata when the metadata
     * changed since the last sync - independent of the version-gated finalization flow, so a
     * code-only deploy (e.g. `git pull` to a commit that adds an entity column without bumping
     * Version::VERSION) still gets its schema updated instead of crashing on the next query.
     *
     * Runs the portable invoice migrations before the additive sync: renames
     * cannot be expressed by the sync, so without this a renamed column would
     * be synced as an empty duplicate (or left crashing).
     *
     * The gate is EntityManagerFactory::entityDefinitionsHash(), a content hash every node running
     * the same code computes identically - the last-synced value is kept in a plain setting row
     * (SCHEMA_METADATA_HASH_PARAM), so a match costs file reads plus a single SELECT and runs
     * outside the finalization lock (see Finalization::finalizePendingUpdate()).
     *
     * @return bool whether a sync attempt ran (even if it applied nothing)
     */
    public function ensureSchemaInSync(): bool
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('em')) {
            return false;
        }

        $currentHash = null;

        try {
            $currentHash = EntityManagerFactory::entityDefinitionsHash();
            if ($this->fetchStoredSchemaHash() === $currentHash || $this->isSyncCoolingDown($currentHash)) {
                return false;
            }

            // Same unconditional settings seeding applyCorePatches() performs, so the
            // same-version path it never runs on doesn't leave them missing either.
            $this->seedInvoiceNoteSettings();

            // Rename steps must precede the additive sync below, which cannot
            // express them (see applyPortableInvoiceMigrations()).
            $this->applyPortableInvoiceMigrations();

            if ($this->syncPortableSchema() === null) {
                $this->recordFailedSyncAttempt($currentHash);

                return false;
            }

            // Heal journal gaps left by drift-created tables or lost writes, bounded to
            // one page so a large backlog converges over runs instead of stalling this one.
            // Failure here must not fail the sync itself, which already succeeded.
            try {
                $this->healInvoiceJournal();
            } catch (\Throwable $e) {
                $this->logUpdate('error', 'Invoice journal healing failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            $this->logUpdate('error', 'Ambient schema sync failed: ' . $e->getMessage());
            $this->recordFailedSyncAttempt($currentHash);

            return false;
        }
    }

    /**
     * Whether the live schema may have drifted from current entity metadata - the hash-comparison
     * half of ensureSchemaInSync(), safe to call without holding the finalization lock. Never
     * throws: an unreadable database simply reports "in sync" and the next request checks again.
     * Also honors the sync retry cooldown, so a persistently failing sync doesn't take the
     * finalization lock on every request just to back off again inside it.
     */
    public function isSchemaOutOfSync(): bool
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('em')) {
            return false;
        }

        try {
            $currentHash = EntityManagerFactory::entityDefinitionsHash();

            return $this->fetchStoredSchemaHash() !== $currentHash
                && !$this->isSyncCoolingDown($currentHash);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether current entity metadata differs from the last synced state,
     * ignoring the retry cooldown - the reporting half of isSchemaOutOfSync(),
     * for status surfaces that must stay truthful while a failed sync backs
     * off. Never throws: an unreadable database reports "no drift".
     */
    public function isSchemaOutOfSyncIgnoringCooldown(): bool
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('em')) {
            return false;
        }

        try {
            return $this->fetchStoredSchemaHash() !== EntityManagerFactory::entityDefinitionsHash();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @throws \Exception when the database cannot be read
     */
    private function fetchStoredSchemaHash(): mixed
    {
        return $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
            'param' => self::SCHEMA_METADATA_HASH_PARAM,
        ]);
    }

    /**
     * Whether a sync for this exact metadata hash already failed within the retry cooldown.
     * Read failures fail open (no cooldown), leaving the outcome to the sync attempt itself.
     */
    private function isSyncCoolingDown(string $currentHash): bool
    {
        $failed = $this->lastSchemaSyncFailure();
        if ($failed === null || $failed['hash'] !== $currentHash) {
            return false;
        }

        $attemptedAt = strtotime($failed['attempted_at']);
        if ($attemptedAt === false) {
            return false;
        }

        return $attemptedAt + self::SCHEMA_SYNC_RETRY_COOLDOWN > time();
    }

    /**
     * The most recent failed portable-schema-sync attempt, if any. Never
     * throws: unreadable state reports "no failure". Surfaced via
     * {@see Finalization::getStatus()} so a repeatedly failing sync
     * is visible instead of living only in the update log.
     *
     * @return array{hash: string, attempted_at: string}|null
     */
    public function lastSchemaSyncFailure(): ?array
    {
        try {
            $rows = $this->fetchAll('SELECT value, updated_at FROM setting WHERE param = :param', [
                'param' => self::SCHEMA_METADATA_HASH_FAILED_PARAM,
            ]);
        } catch (\Throwable) {
            return null;
        }

        $failed = $rows[0] ?? null;
        if (!is_array($failed) || !is_string($failed['value'] ?? null) || $failed['value'] === '') {
            return null;
        }

        return [
            'hash' => $failed['value'],
            'attempted_at' => (string) ($failed['updated_at'] ?? ''),
        ];
    }

    /**
     * Remembers a failed sync attempt for the cooldown above. Never throws - failures here must
     * not mask the original error.
     */
    private function recordFailedSyncAttempt(?string $currentHash): void
    {
        if ($currentHash === null) {
            return;
        }

        try {
            $existing = $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
                'param' => self::SCHEMA_METADATA_HASH_FAILED_PARAM,
            ]);

            if ($existing === false) {
                $now = date('Y-m-d H:i:s');
                $this->executeSql(
                    'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)',
                    [
                        'param' => self::SCHEMA_METADATA_HASH_FAILED_PARAM,
                        'value' => $currentHash,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            } else {
                $this->executeSql('UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param', [
                    'value' => $currentHash,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'param' => self::SCHEMA_METADATA_HASH_FAILED_PARAM,
                ]);
            }
        } catch (\Throwable) {
            // Best effort only - the next request retries the bookkeeping too.
        }
    }

    /**
     * Seeds the debit-note settings rows content.sql gives fresh installs - existing installs
     * upgrading through the credit/debit-note releases never get those rows otherwise.
     * Plain check-then-insert SQL with no MySQL-specific syntax, so it runs unconditionally on
     * every platform; never overwrites a customized value.
     */
    private function seedInvoiceNoteSettings(): void
    {
        $defaults = [
            'invoice_dn_series' => 'DN-',
            'invoice_dn_starting_number' => '1',
        ];

        foreach ($defaults as $param => $value) {
            $existing = $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
                'param' => $param,
            ]);

            if ($existing !== false) {
                continue;
            }

            $now = date('Y-m-d H:i:s');
            $this->executeSql(
                'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)',
                [
                    'param' => $param,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Portable, idempotent invoice settings/rename steps shared by
     * applyCorePatches() and ensureSchemaInSync(). The additive schema sync
     * cannot express the approved-to-issued rename, so the drift-healing path
     * runs these first - otherwise a code-only deploy would sync an empty
     * issued column alongside the data-bearing approved one (or crash).
     */
    private function applyPortableInvoiceMigrations(): void
    {
        $this->migrateInvoiceImmutabilitySetting();

        // Paid invoices keep their issued number, so the retired paid-only
        // series has no reader left. Remove it unconditionally.
        $this->executeSql('DELETE FROM setting WHERE param = :param', ['param' => 'invoice_series_paid']);

        // The auto-approval toggle was renamed to match the issue terminology.
        $this->migrateInvoiceAutoIssueSetting();

        // The invoice approval flag was renamed to issued, matching the new
        // terminology. Runs before the structural sync so the sync sees
        // the renamed column instead of adding it alongside the legacy one.
        $this->renameInvoiceApprovedColumn();

        // The column rename leaves the legacy composite index name behind, and
        // the structural sync only ever adds - so without this the old name
        // lingers as a duplicate forever.
        $this->renameInvoiceStatusIndex();

        // The dead buyer_phone_cc column is gone from entity metadata, and the
        // structural sync only ever adds - so without this the column lingers
        // on existing installs forever.
        $this->dropInvoiceBuyerPhoneCcColumn();

        // Same story for the dead transaction.validate_ipn flag: nothing ever
        // read it, the entity no longer maps it, and the additive sync would
        // otherwise leave it behind on existing installs forever.
        $this->dropTransactionValidateIpnColumn();
    }

    /**
     * Folds the retired per-action invoice toggles (invoice_allow_edit_unpaid,
     * invoice_allow_delete_approved) into the single invoice_immutability
     * setting, then removes the legacy rows. Either legacy opt-in maps to
     * 'relaxed', preserving behavior; otherwise the install converges to
     * 'strict'. Plain portable SQL and idempotent like seedInvoiceNoteSettings(),
     * so it runs unconditionally from applyCorePatches() on every platform.
     */
    private function migrateInvoiceImmutabilitySetting(): void
    {
        $fetch = fn (string $param): mixed => $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
            'param' => $param,
        ]);

        $migrated = $fetch('invoice_immutability');
        if ($migrated === false) {
            $edit = $fetch('invoice_allow_edit_unpaid');
            $delete = $fetch('invoice_allow_delete_approved');
            if ($edit !== false || $delete !== false) {
                $now = date('Y-m-d H:i:s');
                $this->executeSql(
                    'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)',
                    [
                        'param' => 'invoice_immutability',
                        'value' => ($edit === '1' || $delete === '1') ? 'relaxed' : 'strict',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $this->executeSql('DELETE FROM setting WHERE param = :param', ['param' => 'invoice_allow_edit_unpaid']);
        $this->executeSql('DELETE FROM setting WHERE param = :param', ['param' => 'invoice_allow_delete_approved']);
    }

    /**
     * Carries the retired invoice_auto_approval toggle over to its renamed
     * invoice_auto_issue successor, then drops the legacy row. Like
     * migrateInvoiceImmutabilitySetting(), plain portable SQL and idempotent,
     * so it runs unconditionally from applyCorePatches() on every platform.
     */
    private function migrateInvoiceAutoIssueSetting(): void
    {
        $autoApproval = $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
            'param' => 'invoice_auto_approval',
        ]);
        if ($autoApproval === false) {
            return;
        }

        $autoIssue = $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
            'param' => 'invoice_auto_issue',
        ]);
        if ($autoIssue === false) {
            $now = date('Y-m-d H:i:s');
            $this->executeSql(
                'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)',
                [
                    'param' => 'invoice_auto_issue',
                    'value' => $autoApproval,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->executeSql('DELETE FROM setting WHERE param = :param', ['param' => 'invoice_auto_approval']);
    }

    /**
     * Renames invoice.approved to invoice.issued, preserving values. Portable
     * across drivers via the DBAL schema manager for the existence check;
     * MySQL/MariaDB uses CHANGE (works on versions without RENAME COLUMN
     * support), everything else uses RENAME COLUMN. Idempotent: a fresh
     * install already has issued, and a migrated one no longer has approved.
     */
    private function renameInvoiceApprovedColumn(): void
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('dbal')) {
            return;
        }

        $table = $this->di['dbal']->createSchemaManager()->introspectTableByUnquotedName('invoice');
        if (!$table->hasColumn('approved') || $table->hasColumn('issued')) {
            return;
        }

        if ($this->isLegacyPatchDriver()) {
            $this->executeSql('ALTER TABLE `invoice` CHANGE `approved` `issued` TINYINT(1) NOT NULL DEFAULT 0');
        } else {
            $this->executeSql('ALTER TABLE invoice RENAME COLUMN approved TO issued');
        }
    }

    /**
     * Swaps the legacy invoice_status_approved_due_at_idx composite index for
     * its issued-column successor. Portable across drivers via the DBAL schema
     * manager for the existence checks; runs unconditionally from
     * applyCorePatches() on every platform like the rest of this block.
     */
    private function renameInvoiceStatusIndex(): void
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('dbal')) {
            return;
        }

        $old = 'invoice_status_approved_due_at_idx';
        $new = 'invoice_status_issued_due_at_idx';

        $schemaManager = $this->di['dbal']->createSchemaManager();
        if ($schemaManager->introspectTableByUnquotedName('invoice')->hasIndex($old)) {
            $this->executeSql($this->isLegacyPatchDriver()
                ? "DROP INDEX `$old` ON `invoice`"
                : "DROP INDEX $old");
        }

        if (!$schemaManager->introspectTableByUnquotedName('invoice')->hasIndex($new)) {
            $this->executeSql('CREATE INDEX ' . $new . ' ON invoice (status, issued, due_at)');
        }
    }

    /**
     * Drops the dead invoice.buyer_phone_cc column, which the entity no
     * longer maps. Portable across drivers via the DBAL schema manager for
     * the existence check; every supported driver accepts DROP COLUMN.
     * Idempotent: fresh installs never have it, migrated ones no longer do.
     */
    private function dropInvoiceBuyerPhoneCcColumn(): void
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('dbal')) {
            return;
        }

        if (!$this->di['dbal']->createSchemaManager()->introspectTableByUnquotedName('invoice')->hasColumn('buyer_phone_cc')) {
            return;
        }

        $this->executeSql($this->isLegacyPatchDriver()
            ? 'ALTER TABLE `invoice` DROP COLUMN `buyer_phone_cc`'
            : 'ALTER TABLE invoice DROP COLUMN buyer_phone_cc');
    }

    /**
     * Drops the dead transaction.validate_ipn column, which nothing ever
     * read and the entity no longer maps. Portable across drivers via the
     * DBAL schema manager for the existence check; every supported driver
     * accepts DROP COLUMN. Idempotent: fresh installs never have it,
     * migrated ones no longer do.
     */
    private function dropTransactionValidateIpnColumn(): void
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('dbal')) {
            return;
        }

        if (!$this->di['dbal']->createSchemaManager()->introspectTableByUnquotedName('transaction')->hasColumn('validate_ipn')) {
            return;
        }

        // `transaction` is reserved on every driver: quote it, or the ALTER
        // is a syntax error outside MySQL's backticks.
        $this->executeSql($this->isLegacyPatchDriver()
            ? 'ALTER TABLE `transaction` DROP COLUMN `validate_ipn`'
            : 'ALTER TABLE "transaction" DROP COLUMN "validate_ipn"');
    }

    /**
     * Writes one baseline journal entry per invoice that has none, typed by
     * its actual current state (drafts as created, the rest by status). No
     * history is fabricated: installs upgrading to the journal get a truthful
     * starting point, and every later transition appends live entries.
     * Portable across drivers; idempotent: invoices that already have
     * journal rows are skipped. Runs from applyCorePatches() only, never from the drift
     * healer, so a large backlog can't stall page loads.
     */
    public function backfillInvoiceJournal(int $maxPages = 0): void
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('dbal')) {
            return;
        }

        $dbal = $this->di['dbal'];
        if (!$dbal->createSchemaManager()->tablesExist(['invoice_event'])) {
            return;
        }

        $padding = $dbal->fetchOne("SELECT value FROM setting WHERE param = 'invoice_number_padding'");
        $padding = is_numeric($padding) && (int) $padding > 0 ? (int) $padding : 5;

        // Keyset pages, not one unbounded read: on the first upgrade no invoice has a
        // journal row, so an unpaged SELECT would load every invoice (including its
        // notes/text blobs) into memory at once. Only the snapshot columns are read.
        // Idempotent: written rows drop out of later pages via the LEFT JOIN.
        // $maxPages bounds periodic healing (cron, drift sync): 0 means no limit.
        $lastId = 0;
        $pages = 0;
        do {
            $rows = $dbal->fetchAllAssociative(
                'SELECT i.id, i.nr, i.serie, i.status, i.issued, i.client_id,
                    i.buyer_first_name, i.buyer_last_name, i.buyer_company, i.buyer_company_vat,
                    i.buyer_company_number, i.buyer_address, i.buyer_city, i.buyer_state,
                    i.buyer_country, i.buyer_phone, i.buyer_email, i.buyer_zip,
                    i.seller_company, i.seller_company_vat, i.seller_company_number,
                    i.seller_address, i.seller_phone, i.seller_email,
                    i.paid_at, i.due_at, i.created_at
                FROM invoice i LEFT JOIN invoice_event e ON e.invoice_id = i.id
                WHERE e.id IS NULL AND i.id > :last ORDER BY i.id LIMIT 500',
                ['last' => $lastId]
            );

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $this->writeBackfillJournalEntry($dbal, $row, $padding);
            }
            ++$pages;
        } while ($rows !== [] && ($maxPages === 0 || $pages < $maxPages));
    }

    /**
     * Heal invoices missing every journal row (a lost journal write, or a table created
     * after its invoices by drift healing). Bounded to one page per call so periodic
     * callers converge without stalling; reruns pick up where this one stopped.
     */
    public function healInvoiceJournal(int $maxPages = 1): void
    {
        $this->backfillInvoiceJournal($maxPages);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function writeBackfillJournalEntry(\Doctrine\DBAL\Connection $dbal, array $row, int $padding): void
    {
        $invoiceId = (int) $row['id'];

        // Claim the baseline first: concurrent healers (overlapping cron runs, or drift
        // healing racing cron) select the same event-less rows. This conditional UPDATE
        // is atomic on every driver, so exactly one winner per invoice proceeds while
        // losers - including runs that arrive after a live event landed - skip instead
        // of writing a duplicate baseline.
        $claimed = (bool) $dbal->executeStatement(
            'UPDATE invoice SET updated_at = updated_at WHERE id = :id AND NOT EXISTS (SELECT 1 FROM invoice_event WHERE invoice_id = :id)',
            ['id' => $invoiceId]
        );
        if (!$claimed) {
            return;
        }

        $nr = is_numeric($row['nr'] ?? null) ? (int) $row['nr'] : $invoiceId;
        $snapshot = [
            'serie_nr' => ($row['serie'] ?? '') . sprintf('%0' . $padding . 's', $nr),
            'status' => $row['status'] ?? null,
            'issued' => !empty($row['issued']),
            'subtotal' => null,
            'tax' => null,
            'total' => null,
            'buyer' => [
                'first_name' => $row['buyer_first_name'] ?? null,
                'last_name' => $row['buyer_last_name'] ?? null,
                'company' => $row['buyer_company'] ?? null,
                'company_vat' => $row['buyer_company_vat'] ?? null,
                'company_number' => $row['buyer_company_number'] ?? null,
                'address' => $row['buyer_address'] ?? null,
                'city' => $row['buyer_city'] ?? null,
                'state' => $row['buyer_state'] ?? null,
                'country' => $row['buyer_country'] ?? null,
                'phone' => $row['buyer_phone'] ?? null,
                'email' => $row['buyer_email'] ?? null,
                'zip' => $row['buyer_zip'] ?? null,
            ],
            'seller' => [
                'company' => $row['seller_company'] ?? null,
                'company_vat' => $row['seller_company_vat'] ?? null,
                'company_number' => $row['seller_company_number'] ?? null,
                'address' => $row['seller_address'] ?? null,
                'phone' => $row['seller_phone'] ?? null,
                'email' => $row['seller_email'] ?? null,
            ],
            'paid_at' => $this->normalizeBackfillDate($row['paid_at'] ?? null),
            'due_at' => $this->normalizeBackfillDate($row['due_at'] ?? null),
            'created_at' => $this->normalizeBackfillDate($row['created_at'] ?? null),
        ];

        $type = 'created';
        if (!empty($row['issued'])) {
            $type = match ($row['status'] ?? null) {
                'paid' => 'paid',
                'canceled' => 'canceled',
                'refunded' => 'refunded',
                default => 'issued',
            };
        }

        $dbal->executeStatement(
            'INSERT INTO invoice_event (invoice_id, type, client_id, snapshot, created_at) VALUES (:invoice_id, :type, :client_id, :snapshot, :created_at)',
            [
                'invoice_id' => $invoiceId,
                'type' => $type,
                'client_id' => $row['client_id'] !== null ? (int) $row['client_id'] : null,
                // Substitute rather than throw on legacy bytes that cannot be encoded:
                // a single bad row must not wedge the whole patch run.
                'snapshot' => json_encode($snapshot, JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => $snapshot['created_at'] ?? date('Y-m-d H:i:s'),
            ]
        );
    }

    private function normalizeBackfillDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * Records the metadata identity the schema was last synced against, in the same plain
     * setting row ensureSchemaInSync() compares. Portable check-then-write, no
     * ON DUPLICATE KEY UPDATE, so it works on every driver.
     */
    private function storeSchemaMetadataHash(string $hash): void
    {
        $existing = $this->fetchOne('SELECT value FROM setting WHERE param = :param', [
            'param' => self::SCHEMA_METADATA_HASH_PARAM,
        ]);

        if ($existing === false) {
            $now = date('Y-m-d H:i:s');
            $this->executeSql(
                'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)',
                [
                    'param' => self::SCHEMA_METADATA_HASH_PARAM,
                    'value' => $hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        } elseif ($existing !== $hash) {
            $this->executeSql('UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param', [
                'value' => $hash,
                'updated_at' => date('Y-m-d H:i:s'),
                'param' => self::SCHEMA_METADATA_HASH_PARAM,
            ]);
        }
    }

    /**
     * Run once: non-MySQL installs retain the legacy column, so replaying the
     * copy would restore memberships removed by administrators.
     */
    private function migrateClientGroupMemberships(\Doctrine\DBAL\Connection $connection): void
    {
        $marker = 'client_group_memberships_migrated';
        if ($connection->fetchOne('SELECT value FROM setting WHERE param = :param', ['param' => $marker]) !== false) {
            return;
        }

        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['client'])
            || !$schemaManager->introspectTableByUnquotedName('client')->hasColumn('client_group_id')) {
            return;
        }

        $connection->transactional(static function (\Doctrine\DBAL\Connection $connection) use ($marker): void {
            $connection->executeStatement(
                'INSERT INTO client_group_members (client_id, client_group_id) '
                . 'SELECT c.id, c.client_group_id FROM client c '
                . 'INNER JOIN client_group g ON g.id = c.client_group_id '
                . 'WHERE NOT EXISTS (SELECT 1 FROM client_group_members m '
                . 'WHERE m.client_id = c.id AND m.client_group_id = c.client_group_id)'
            );
            $now = date('Y-m-d H:i:s');
            $connection->insert('setting', [
                'param' => $marker,
                'value' => '1',
                'public' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * @return list<string>
     */
    public function getColumnForeignKeys(string $table, string $column): array
    {
        $rows = $this->fetchAll(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['table' => $table, 'column' => $column],
        );

        return array_values(array_unique(array_map(static fn (array $row): string => (string) $row['CONSTRAINT_NAME'], $rows)));
    }

    private function tableHasForeignKey(string $table, string $constraintName): bool
    {
        return (bool) $this->fetchOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = :type LIMIT 1',
            ['table' => $table, 'constraint' => $constraintName, 'type' => 'FOREIGN KEY'],
        );
    }

    public function addForeignKeyIfMissing(string $table, string $constraintName, string $column, string $referencedTable, string $referencedColumn): void
    {
        if ($this->tableHasForeignKey($table, $constraintName)) {
            return;
        }

        $this->executeSql(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE CASCADE',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($constraintName),
            $this->quoteIdentifier($column),
            $this->quoteIdentifier($referencedTable),
            $this->quoteIdentifier($referencedColumn),
        ));
    }

    private function removeRetiredHookData(): void
    {
        $this->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND meta_key = 'listener'");
        $this->executeSql("DELETE FROM extension WHERE type = 'hook'");
    }
}

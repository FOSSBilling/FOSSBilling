<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update;

use FOSSBilling\Container\InjectionAwareInterface;
use FOSSBilling\Doctrine\DriverManagerFactory;
use FOSSBilling\Doctrine\ModuleEntityScope;
use FOSSBilling\Doctrine\SchemaSynchronizer;
use FOSSBilling\Exception\BaseException;
use FOSSBilling\Security\Crypt;
use FOSSBilling\System\Config;
use FOSSBilling\System\Environment;
use FOSSBilling\System\Version;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

class Patcher implements InjectionAwareInterface
{
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
        if (!$this->isMysqlDriver()) {
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
        $newConfig['db']['port'] = \FOSSBilling\Utils\Normalizer::normalizePort($newConfig['db']['port'] ?? null, 3306);
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
        // introspection, ON DUPLICATE KEY UPDATE, ...) with no PostgreSQL/SQLite equivalent, and
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
        // start executing MySQL-only DDL against a non-MySQL database. That fails partway through
        // (setPatchLevel() itself uses ON DUPLICATE KEY UPDATE), leaving the schema in a state no
        // later patch or install can cleanly recover from.
        if ($this->isMysqlDriver()) {
            $patchLevel = $this->getPatchLevel();
            $patches = $this->getPatches($patchLevel);
            foreach ($patches as $patchLevel => $patch) {
                call_user_func($patch, $this);
                $this->setPatchLevel($patchLevel);
            }
        }

        // Additive structural sync runs on every platform, MySQL/MariaDB included: it picks up any
        // column/table/index that's on entity metadata but not yet applied, without needing a
        // hand-written patch for it - the only mechanism at all on PostgreSQL/SQLite, and on
        // MySQL/MariaDB a catch-all for anything the patches above didn't (or, going forward, for
        // structural changes that land on metadata without a patch being written at all).
        $this->syncPortableSchema();
    }

    /**
     * Whether the configured database driver is MySQL/MariaDB - the only platform
     * {@see self::applyCorePatches()}'s raw SQL patches are written for.
     */
    public function isMysqlDriver(): bool
    {
        try {
            return DriverManagerFactory::getDatabaseConfig()['driver'] === 'pdo_mysql';
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
     * ({@see ModuleEntityScope::isEagerNow()}) - the same gating {@see \FOSSBilling\Doctrine\
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
    public function syncPortableSchema(): void
    {
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('em')) {
            return;
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
                return;
            }

            $result = SchemaSynchronizer::syncEntities($entityManager, $eagerEntityClasses);
        } catch (\Throwable $e) {
            $this->logUpdate('error', 'Schema sync against the configured database failed: ' . $e->getMessage());

            return;
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

        foreach ($data as $column => $value) {
            $placeholder = "set_{$column}";
            $set[] = sprintf('`%s` = :%s', $this->quoteIdentifier($column), $placeholder);
            $params[$placeholder] = $value;
        }

        foreach ($criteria as $column => $value) {
            $placeholder = "where_{$column}";
            $where[] = sprintf('`%s` = :%s', $this->quoteIdentifier($column), $placeholder);
            $params[$placeholder] = $value;
        }

        $this->executeSql(
            sprintf('UPDATE `%s` SET %s WHERE %s', $this->quoteIdentifier($table), implode(', ', $set), implode(' AND ', $where)),
            $params
        );
    }

    public function tableHasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->getTableColumns($table), true);
    }

    public function tableExists(string $table): bool
    {
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
        $columns = $this->fetchAll(sprintf('SHOW COLUMNS FROM `%s`', $this->quoteIdentifier($table)));

        return array_map(static fn (array $column): string => (string) $column['Field'], $columns);
    }

    public function getColumnLength(string $table, string $column): ?int
    {
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
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM `%s` LIKE :column', $this->quoteIdentifier($table)), [
            'column' => $column,
        ]);

        return $rows === [] ? null : (string) $rows[0]['Type'];
    }

    public function tableHasIndex(string $table, string $indexName): bool
    {
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

        $this->executeSql(
            'INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE value = :value, updated_at = :updated_at',
            [
                'param' => 'last_patch',
                'value' => $patchLevel,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
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
}

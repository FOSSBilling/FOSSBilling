<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Box\Mod\Extension\Entity\Extension;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

class UpdatePatcher implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;
    private Filesystem $filesystem;
    private array $downloadableStorageMigrationMap = [];

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
            throw new Exception('Unable to load existing configuration');
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
        $newConfig['db']['port'] = Tools::normalizePort($newConfig['db']['port'] ?? null, 3306);
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

        $patchLevel = $this->getPatchLevel();
        $patches = $this->getPatches($patchLevel);
        foreach ($patches as $patchLevel => $patch) {
            call_user_func($patch);
            $this->setPatchLevel($patchLevel);
        }
    }

    /**
     * Execute actions against the provided directories and files.
     *
     * @param array $files Array containing files and directories to perform action on and
     *                     the actions to perform. Valid options are 'rename' and 'unlink'.
     */
    private function executeFileActions(array $files): void
    {
        foreach ($files as $file => $action) {
            try {
                if ($action === 'unlink' && $this->filesystem->exists($file)) {
                    $this->filesystem->remove($file);
                } elseif ($this->filesystem->exists($file)) {
                    $this->filesystem->rename($file, $action);
                }
            } catch (IOException $e) {
                error_log($e->getMessage());
            }
        }
    }

    private function getPdo(): \PDO
    {
        // The first request after updating from 0.7.x still uses the old Composer autoloader.
        // Use PDO here because it is available before and after the archive is extracted.
        if (!$this->di instanceof \Pimple\Container || !$this->di->offsetExists('pdo')) {
            throw new Exception('Database connection is not available.');
        }

        return $this->di['pdo'];
    }

    /**
     * Execute the given SQL statement.
     *
     * @param $sql The SQL statement to execute
     */
    private function executeSql(string $sql, array $params = []): void
    {
        try {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($params);
        } catch (\Exception $e) {
            // Log the error and then throw a user-friendly exception to prevent further patches from being applied.
            error_log($e->getMessage());

            throw new Exception('There was an error while applying database patches. Please check the error log for information on the error, correct it, and then perform the backup patching method to complete the update.');
        }
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchOne(string $sql, array $params = []): mixed
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    private function fetchFirstColumn(string $sql, array $params = []): array
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function fetchKeyValue(string $sql, array $params = []): array
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function updateTable(string $table, array $data, array $criteria): void
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

    private function tableHasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->getTableColumns($table), true);
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1',
            ['table' => $table],
        );
    }

    private function computeInvoiceHashExpiration(): ?string
    {
        $value = $this->fetchOne("SELECT value FROM setting WHERE param = 'invoice_hash_lifetime_days'");
        $days = is_string($value) && $value !== '' ? (int) $value : 90;
        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }

    private function getTableColumns(string $table): array
    {
        $columns = $this->fetchAll(sprintf('SHOW COLUMNS FROM `%s`', $this->quoteIdentifier($table)));

        return array_map(static fn (array $column): string => (string) $column['Field'], $columns);
    }

    private function getColumnLength(string $table, string $column): ?int
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

    private function tableHasIndex(string $table, string $indexName): bool
    {
        $indexes = $this->fetchAll(sprintf('SHOW INDEX FROM `%s`', $this->quoteIdentifier($table)));
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new Exception('Invalid database identifier: :identifier', [':identifier' => $identifier]);
        }

        return $identifier;
    }

    private function migrateEncryptedColumn(string $table, string $idColumn, string $valueColumn, string $where, array $params = []): void
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
            throw new Exception('Invalid SQL WHERE clause fragment.');
        }

        $rows = $this->fetchAll("SELECT {$idColumn} AS id, {$valueColumn} AS encrypted_value FROM {$quotedTable} WHERE {$where}", $params);

        /** @var \Box_Crypt $crypt */
        $crypt = $this->di['crypt'];
        $salt = Config::getProperty('info.salt');

        $hasUpdatedAt = $this->tableHasColumn($rawTable, 'updated_at');

        foreach ($rows as $row) {
            $encryptedValue = $row['encrypted_value'] ?? null;
            if (!is_string($encryptedValue) || $encryptedValue === '' || str_starts_with($encryptedValue, \Box_Crypt::CURRENT_FORMAT_PREFIX)) {
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
    private function getPatchLevel(): ?int
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
    private function setPatchLevel(int $patchLevel): void
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
        $patches = [
            25 => 'patch25',
            26 => 'patch26',
            27 => 'patch27',
            28 => 'patch28',
            29 => 'patch29',
            30 => 'patch30',
            31 => 'patch31',
            32 => 'patch32',
            33 => 'patch33',
            34 => 'patch34',
            35 => 'patch35',
            36 => 'patch36',
            37 => 'patch37',
            38 => 'patch38',
            39 => 'patch39',
            40 => 'patch40',
            41 => 'patch41',
            42 => 'patch42',
            43 => 'patch43',
            44 => 'patch44',
            45 => 'patch45',
            46 => 'patch46',
            47 => 'patch47',
            48 => 'patch48',
            49 => 'patch49',
            50 => 'patch50',
            51 => 'patch51',
            52 => 'patch52',
            53 => 'patch53',
            54 => 'patch54',
            55 => 'patch55',
            56 => 'patch56',
            57 => 'patch57',
            58 => 'patch58',
            59 => 'patch59',
            60 => 'patch60',
            61 => 'patch61',
            62 => 'patch62',
            63 => 'patch63',
            64 => 'patch64',
            65 => 'patch65',
            66 => 'patch66',
            67 => 'patch67',
            68 => 'patch68',
            69 => 'patch69',
            70 => 'patch70',
            71 => 'patch71',
            72 => 'patch72',
            73 => 'patch73',
            74 => 'patch74',
            75 => 'patch75',
            76 => 'patch76',
            77 => 'patch77',
            78 => 'patch78',
            79 => 'patch79',
            80 => 'patch80',
            81 => 'patch81',
            82 => 'patch82',
            83 => 'patch83',
            84 => 'patch84',
            85 => 'patch85',
            86 => 'patch86',
            87 => 'patch87',
        ];
        ksort($patches, SORT_NATURAL);

        $patchesToApply = array_filter($patches, fn ($key): bool => $key > $patchLevel, ARRAY_FILTER_USE_KEY);

        return array_map(fn ($method): array => [$this, $method], $patchesToApply);
    }

    private function patch25(): void
    {
        $this->executeSql('UPDATE email_template SET content = REPLACE(content, :old_filter, :new_filter)', [
            'old_filter' => '{% filter markdown %}',
            'new_filter' => '{% apply markdown_to_html %}',
        ]);

        $this->executeSql('UPDATE email_template SET content = REPLACE(content, :old_endfilter, :new_endfilter)', [
            'old_endfilter' => '{% endfilter %}',
            'new_endfilter' => '{% endapply %}',
        ]);
    }

    private function patch26(): void
    {
        // Migration steps from BoxBilling to FOSSBilling - added favicon settings.
        $this->executeSql(
            'INSERT INTO setting (param, value, public, category, hash, created_at, updated_at) VALUES (:param, :value, 0, :category, :hash, :created_at, :updated_at)',
            [
                'param' => 'company_favicon',
                'value' => 'public/branding/favicon.ico',
                'category' => null,
                'hash' => null,
                'created_at' => '2023-01-08 12:00:00',
                'updated_at' => '2023-01-08 12:00:00',
            ]
        );
    }

    private function patch27(): void
    {
        // Migration steps to create table to allow admin users to do password reset.
        $q = 'CREATE TABLE `admin_password_reset` ( `id` bigint(20) NOT NULL AUTO_INCREMENT, `admin_id` bigint(20) DEFAULT NULL, `hash` varchar(100) DEFAULT NULL, `ip` varchar(45) DEFAULT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `admin_id_idx` (`admin_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
        $this->executeSql($q);
    }

    private function patch28(): void
    {
        // Patch to remove .html from email templates action code.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/863
        $this->executeSql('UPDATE email_template SET action_code = REPLACE(action_code, :search, :replace)', [
            'search' => '.html',
            'replace' => '',
        ]);
    }

    private function patch29(): void
    {
        // Patch to update email templates to use format_date/format_datetime filters
        // instead of removed bb_date/bb_datetime filters.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/948
        $this->executeSql('UPDATE email_template SET content = REPLACE(content, :search, :replace)', [
            'search' => 'bb_date',
            'replace' => 'format_date',
        ]);

        $this->executeSql('UPDATE email_template SET content = REPLACE(content, :search, :replace)', [
            'search' => 'bb_datetime',
            'replace' => 'format_datetime',
        ]);
    }

    private function patch30(): void
    {
        // Patch to remove the old guzzlehttp package, as we no longer use it.
        $fileActions = [
            Path::join(PATH_VENDOR, 'guzzlehttp') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch31(): void
    {
        // Patch to remove the old htaccess.txt file, and any old config.php backup.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1075
        $fileActions = [
            Path::join(PATH_ROOT, 'htaccess.txt') => 'unlink',
            Path::join(PATH_ROOT, 'config.php.old') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch32(): void
    {
        // Patch to remove the old phpmailer package, some leftover admin_default files, and old Box_ classes.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1091
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1063
        $fileActions = [
            Path::join(PATH_VENDOR, 'phpmailer') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'images') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'scss', 'bb-deprecated.scss') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'scss', 'dataTable-deprecated.scss') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'scss', 'main-deprecated.scss') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Mail.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Ftp.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'FileCacheExcption.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Zip.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Requirements.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Version.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Extension.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Cookie.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'ExceptionAuth.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Response.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Config.php') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch33(): void
    {
        // Patch to remove the old FileCache class that was replaced with Symfony's Cache component.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1184
        $fileActions = [
            Path::join(PATH_LIBRARY, 'FileCache.php') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch34(): void
    {
        // Adds the new "fingerprint" to the session table, to allow us to fingerprint devices and help prevent against attacks such as session hijacking.
        $q = 'ALTER TABLE session ADD fingerprint TEXT;';
        $this->executeSql($q);
    }

    private function patch35(): void
    {
        // Adds the new "created_at" to the session table, to ensure sessions are destroyed after they reach their maximum age.
        $q = 'ALTER TABLE session ADD created_at int(11);';
        $this->executeSql($q);
    }

    private function patch36(): void
    {
        // Patch to complete merging the Kb and Support modules.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1180

        // Renames the "kb_article" and "kb_article_category" tables to "support_kb_article" and "support_kb_article_category", respectively.
        $q = 'RENAME TABLE kb_article TO support_kb_article, kb_article_category TO support_kb_article_category;';
        $this->executeSql($q);

        // An error here can pretty safely be ignored.
        try {
            // If the Kb extension is currently active, set enabled in Support settings.
            $ext_service = $this->di['mod_service']('extension');
            if ($ext_service->isExtensionActive('mod', 'kb')) {
                $support_ext_config = $ext_service->getConfig('mod_support');
                $support_ext_config['kb_enable'] = true;
                $ext_service->setConfig($support_ext_config);
            }

            // If the Kb extension exists, uninstall it.
            $kb_ext = $ext_service->getExtensionRepository()->findOneByTypeAndName('mod', 'kb');
            if ($kb_ext instanceof Extension) {
                $ext_service->deactivate($kb_ext);
                $ext_service->uninstall('mod', 'kb');
            }
        } catch (\Exception) {
        }

        // Finally, remove old Kb extension files/folders.
        $fileActions = [
            Path::join(PATH_MODS, 'Kb') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch37(): void
    {
        // Patch to completely remove the outdated queue module.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1777

        try {
            $ext_service = $this->di['mod_service']('extension');
            // If the queue extension exists, uninstall it.
            $queue_ext = $ext_service->getExtensionRepository()->findOneByTypeAndName('mod', 'queue');
            if ($queue_ext instanceof Extension) {
                $ext_service->deactivate($queue_ext);
                $ext_service->uninstall('mod', 'queue');
            }
        } catch (\Exception) {
        }

        // Finally, remove old queue module from the disk.
        $fileActions = [
            Path::join(PATH_MODS, 'Queue') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch38(): void
    {
        // We need to remove the old ISPConfig3 and Virtualmin server managers from disk or else the leftover files could prevent the "hosting plans and servers" page from being loaded.
        $fileActions = [
            Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Ispconfig3.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Virtualmin.php') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch39(): void
    {
        // The Serbian language was incorrectly placed into a folder named `srp` by Crowdin which is now corrected for via the locale repo and as such we need to delete the old directory.
        // @see https://github.com/FOSSBilling/locale/issues/212
        $fileActions = [
            Path::join(PATH_LANGS, 'srp') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch40(): void
    {
        // Added `passwordLength` field to server managers
        $q = 'ALTER TABLE service_hosting_server ADD COLUMN `password_length` TINYINT DEFAULT NULL;';
        $this->executeSql($q);
    }

    private function patch41(): void
    {
        // Remove the  `manifest` column from the extensions table since it's no longer used
        $q = 'ALTER TABLE extension DROP COLUMN manifest;';
        $this->executeSql($q);
    }

    private function patch42(): void
    {
        // This patch will migrate previous currency exchange rate data provider settings to the new ones
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/2189
        $ext_service = $this->di['mod_service']('extension');

        $pairs = $this->fetchKeyValue('SELECT param, value FROM setting');

        $config = $ext_service->getConfig('mod_currency');
        $config['ext'] = 'mod_currency'; // This should automatically be set, but some appear to be having cache issues that causes it to not be

        // Migrate the old currency exchange rate sync settings
        $key = $pairs['currencylayer'] ?? '';
        if ($key) {
            $config['provider'] = 'currency_data_api';
            $config['currencydata_key'] = $key;
        }

        // Now migrate the cron setting
        $cron = $pairs['currency_cron_enabled'] ?? 0;
        if ($cron == '1') {
            $config['sync_rate'] = 'auto';
        } else {
            $config['sync_rate'] = 'never';
        }

        $ext_service->setConfig($config);
    }

    private function patch43(): void
    {
        $fileActions = [
            Path::join(PATH_LIBRARY, 'GeoLite2-Country.mmdb') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch44(): void
    {
        // Add ipn_hash column to transaction table and index it for fast duplicate detection.
        $q = 'ALTER TABLE `transaction`
                ADD COLUMN `ipn_hash` VARCHAR(64) DEFAULT NULL,
                ADD INDEX `transaction_ipn_hash_idx` (`gateway_id`, `ipn_hash`(64));';
        $this->executeSql($q);
    }

    private function patch45(): void
    {
        // Drop updated_at column from activity tables
        // Activity logs are never meant to be updated, only created
        foreach (['activity_admin_history', 'activity_client_email', 'activity_client_history', 'activity_system'] as $table) {
            if ($this->tableHasColumn($table, 'updated_at')) {
                $this->executeSql(sprintf('ALTER TABLE `%s` DROP COLUMN `updated_at`;', $this->quoteIdentifier($table)));
            }
        }
    }

    private function patch46(): void
    {
        // Normalize legacy values before converting to restrictive ENUM columns.
        $q = 'UPDATE `client`
                SET `gender` = NULL
                WHERE `gender` IS NOT NULL
                AND `gender` NOT IN (\'male\', \'female\', \'nonbinary\', \'other\');';
        $this->executeSql($q);

        // Change gender column to ENUM type
        $q = 'ALTER TABLE `client`
                MODIFY COLUMN `gender` ENUM(\'male\', \'female\', \'nonbinary\', \'other\') DEFAULT NULL;';

        $this->executeSql($q);
    }

    private function patch47(): void
    {
        // Migrate "membership" product type to "custom" product type
        // This is part of removing the Servicemembership module
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/3066

        // Migrate products to the 'custom' product type
        $q = 'UPDATE `product` SET `type` = "custom" WHERE `type` = "membership";';
        $this->executeSql($q);

        // Before migrating existing orders to the 'custom' product type,
        // set service_id to NULL for orders with service_type = "membership"
        $q = 'UPDATE `client_order` SET `service_id` = NULL WHERE `service_type` = "membership";';
        $this->executeSql($q);
        // Migrate existing orders to the 'custom' product type
        $q = 'UPDATE `client_order` SET `service_type` = "custom" WHERE `service_type` = "membership";';
        $this->executeSql($q);

        // Drop the service_membership table as it's no longer needed
        $q = 'DROP TABLE IF EXISTS `service_membership`;';
        $this->executeSql($q);
    }

    private function patch48(): void
    {
        $filesystem = $this->filesystem;

        $oldUploadsPath = Path::join(PATH_ROOT, 'uploads');
        $newUploadsPath = Path::join(PATH_ROOT, 'data', 'uploads');

        if ($filesystem->exists($oldUploadsPath) && $filesystem->exists($newUploadsPath)) {
            foreach (glob($oldUploadsPath . '/*') ?: [] as $oldFile) {
                if (is_file($oldFile)) {
                    $filename = basename($oldFile);
                    $newFilePath = Path::join($newUploadsPath, $filename);
                    if (!$filesystem->exists($newFilePath)) {
                        $filesystem->rename($oldFile, $newFilePath);
                    }
                }
            }
        }

        $products = $this->fetchAll("SELECT p.id, p.config FROM product p WHERE p.type = 'downloadable'");

        foreach ($products as $product) {
            $productConfig = json_decode((string) $product['config'], true) ?: [];

            if (!empty($productConfig['filename'])) {
                continue;
            }

            $foundFilename = null;

            $orders = $this->fetchAll('SELECT co.id, co.config, co.service_id FROM client_order co WHERE co.product_id = :product_id', ['product_id' => $product['id']]);

            foreach ($orders as $order) {
                $orderConfig = json_decode($order['config'] ?? '', true);
                if (!is_array($orderConfig) || !isset($orderConfig['filename'])) {
                    continue;
                }

                $filePath = Path::join(PATH_UPLOADS, md5((string) $orderConfig['filename']));
                if ($filesystem->exists($filePath)) {
                    $foundFilename = $orderConfig['filename'];

                    break;
                }
            }

            if ($foundFilename === null) {
                $services = $this->fetchAll('SELECT sd.id, sd.filename FROM service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id WHERE co.product_id = :product_id AND sd.filename IS NOT NULL AND sd.filename != ""', ['product_id' => $product['id']]);

                foreach ($services as $service) {
                    $filePath = Path::join(PATH_UPLOADS, md5((string) $service['filename']));
                    if ($filesystem->exists($filePath)) {
                        $foundFilename = $service['filename'];

                        break;
                    }
                }
            }

            if ($foundFilename !== null) {
                $productConfig['filename'] = $foundFilename;
                $this->executeSql('UPDATE product SET config = :config, updated_at = :updated_at WHERE id = :id', [
                    'config' => json_encode($productConfig),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $product['id'],
                ]);

                $this->executeSql('UPDATE service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id SET sd.filename = :filename WHERE co.product_id = :product_id', ['filename' => $foundFilename, 'product_id' => $product['id']]);

                $ordersToUpdate = $this->fetchAll('SELECT id, config FROM client_order WHERE product_id = :product_id AND config LIKE "%filename%"', ['product_id' => $product['id']]);

                foreach ($ordersToUpdate as $orderToUpdate) {
                    $orderConfig = json_decode($orderToUpdate['config'] ?? '', true);
                    if (is_array($orderConfig) && isset($orderConfig['filename'])) {
                        $orderConfig['filename'] = $foundFilename;
                        $this->executeSql('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                            'config' => json_encode($orderConfig),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'id' => $orderToUpdate['id'],
                        ]);
                    }
                }
            }
        }

        $orphans = $this->fetchAll('SELECT sd.id, co.config as order_config FROM service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id WHERE sd.filename IS NULL OR sd.filename = ""');

        foreach ($orphans as $orphan) {
            $orderConfig = json_decode($orphan['order_config'] ?? '', true);
            if (isset($orderConfig['filename']) && !empty($orderConfig['filename'])) {
                $filePath = Path::join(PATH_UPLOADS, md5((string) $orderConfig['filename']));
                if ($filesystem->exists($filePath)) {
                    $this->executeSql('UPDATE service_downloadable SET filename = :filename WHERE id = :id', ['filename' => $orderConfig['filename'], 'id' => $orphan['id']]);
                }
            }
        }
    }

    private function patch49(): void
    {
        $q = "UPDATE setting SET value = 'public/branding/logo.svg' WHERE param = 'company_logo' AND value = 'themes/huraga/assets/img/logo.svg';";
        $this->executeSql($q);

        $q = "UPDATE setting SET value = 'public/branding/logo-dark.svg' WHERE param = 'company_logo_dark' AND value = 'themes/huraga/assets/img/logo_white.svg';";
        $this->executeSql($q);

        $q = "UPDATE setting SET value = 'public/branding/favicon.ico' WHERE param = 'company_favicon' AND value = 'themes/huraga/assets/favicon.ico';";
        $this->executeSql($q);
    }

    private function patch50(): void
    {
        $this->migrateEncryptedColumn('email_template', 'id', 'vars', 'vars IS NOT NULL AND vars != :empty', [
            'empty' => '',
        ]);
        $this->migrateEncryptedColumn('extension_meta', 'id', 'meta_value', 'meta_key = :meta_key AND meta_value IS NOT NULL AND meta_value != :empty', [
            'meta_key' => 'config',
            'empty' => '',
        ]);
    }

    private function patch51(): void
    {
        $oldDir = Path::join(PATH_MODS, 'Invoice', 'pdf_template');
        $newDir = Path::join(PATH_MODS, 'Invoice', 'templates', 'pdf');

        if (!$this->filesystem->exists($oldDir)) {
            return;
        }

        $fileActions = [
            Path::join($oldDir, 'custom-pdf.twig') => Path::join($newDir, 'custom-invoice.twig'),
            Path::join($oldDir, 'custom-pdf.css') => Path::join($newDir, 'custom-invoice.css'),
            Path::join($oldDir, 'default-pdf.twig') => 'unlink',
            Path::join($oldDir, 'default-pdf.css') => 'unlink',
        ];
        $this->executeFileActions($fileActions);

        $finder = new Finder();
        if (!$finder->in($oldDir)->depth('== 0')->hasResults()) {
            $this->filesystem->remove($oldDir);
        }
    }

    private function patch52(): void
    {
        $columns = $this->getTableColumns('email_template');

        if (!in_array('is_custom', $columns, true)) {
            $this->executeSql("ALTER TABLE `email_template` ADD COLUMN `is_custom` TINYINT(1) DEFAULT '0' AFTER `enabled`;");
        }

        if (!in_array('is_overridden', $columns, true)) {
            $this->executeSql("ALTER TABLE `email_template` ADD COLUMN `is_overridden` TINYINT(1) DEFAULT '0' COMMENT 'Whether subject/content have been customized from file defaults' AFTER `is_custom`;");
        }

        $templates = $this->fetchAll('SELECT id, action_code, subject, content FROM email_template');
        foreach ($templates as $template) {
            $default = $this->getDefaultEmailTemplateData((string) ($template['action_code'] ?? ''));
            if ($default === null) {
                $this->executeSql('UPDATE email_template SET is_custom = :is_custom WHERE id = :id', [
                    'is_custom' => 1,
                    'id' => $template['id'],
                ]);

                continue;
            }

            $subject = (string) ($template['subject'] ?? '');
            $content = (string) ($template['content'] ?? '');

            $isOverridden = (trim($subject) !== trim((string) $default['subject'])) || (trim($content) !== trim((string) $default['content']));

            if (!$isOverridden) {
                $subject = $default['subject'];
                $content = $default['content'];
            }

            $this->executeSql('UPDATE email_template SET is_custom = :is_custom, is_overridden = :is_overridden, subject = :subject, content = :content WHERE id = :id', [
                'is_custom' => 0,
                'is_overridden' => $isOverridden ? 1 : 0,
                'subject' => $subject,
                'content' => $content,
                'id' => $template['id'],
            ]);
        }
    }

    private function patch53(): void
    {
        $pdo = $this->getPdo();
        $tools = $this->di['tools'];
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $batchSize = 1000;
            $adminUpdateStmt = $pdo->prepare('UPDATE admin SET api_token = :api_token, updated_at = :updated_at WHERE id = :id');
            $clientUpdateStmt = $pdo->prepare('UPDATE client SET api_token = :api_token, updated_at = :updated_at WHERE id = :id');

            $lastAdminId = 0;
            do {
                $adminIds = $this->fetchFirstColumn("SELECT id FROM admin WHERE id > :lastId ORDER BY id ASC LIMIT {$batchSize}", [
                    'lastId' => $lastAdminId,
                ]);

                foreach ($adminIds as $adminId) {
                    $adminUpdateStmt->bindValue('api_token', $tools->generatePassword(32));
                    $adminUpdateStmt->bindValue('updated_at', $now);
                    $adminUpdateStmt->bindValue('id', (int) $adminId, \PDO::PARAM_INT);
                    $adminUpdateStmt->execute();
                }

                if (!empty($adminIds)) {
                    $lastAdminId = (int) end($adminIds);
                }
            } while (!empty($adminIds));

            $lastClientId = 0;
            do {
                $clientIds = $this->fetchFirstColumn("SELECT id FROM client WHERE id > :lastId ORDER BY id ASC LIMIT {$batchSize}", [
                    'lastId' => $lastClientId,
                ]);

                foreach ($clientIds as $clientId) {
                    $clientUpdateStmt->bindValue('api_token', $tools->generatePassword(32));
                    $clientUpdateStmt->bindValue('updated_at', $now);
                    $clientUpdateStmt->bindValue('id', (int) $clientId, \PDO::PARAM_INT);
                    $clientUpdateStmt->execute();
                }

                if (!empty($clientIds)) {
                    $lastClientId = (int) end($clientIds);
                }
            } while (!empty($clientIds));

            $this->executeSql('DELETE FROM session');

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    private function patch54(): void
    {
        if (!$this->tableHasIndex('api_request', 'api_request_ip_created')) {
            $this->executeSql('ALTER TABLE `api_request` ADD INDEX `api_request_ip_created` (`ip`, `created_at`);');
        }

        $fileActions = [
            Path::join(PATH_LIBRARY, 'Model', 'ClientPasswordResetTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ApiRequestTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ApiRequest.php') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch55(): void
    {
        // Migrate Spamchecker module to Anti-Spam module
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/2700

        try {
            $extService = $this->di['mod_service']('extension');

            $oldConfig = $extService->getConfig('mod_spamchecker');
            $spamcheckerSettings = array_diff_key($oldConfig, ['ext' => true]);
            if (!empty($spamcheckerSettings)) {
                $existingAntispamConfig = $extService->getConfig('mod_antispam');
                $existingAntispamSettings = array_diff_key($existingAntispamConfig, ['ext' => true]);
                $newConfig = array_merge($spamcheckerSettings, $existingAntispamSettings);
                $newConfig['ext'] = 'mod_antispam';
                $newConfig['honeypot_enabled'] ??= true;
                $newConfig['honeypot_field'] ??= 'bio';
                $extService->setConfig($newConfig);
            }

            $this->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND rel_type = 'mod' AND rel_id = 'spamchecker' AND meta_key = 'listener'");

            $hookService = $this->di['mod_service']('hook');
            $hookService->batchConnect('antispam');

            $this->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_spamchecker' AND meta_key = 'config'");

            $spamcheckerExt = $extService->getExtensionRepository()->findOneByTypeAndName('mod', 'spamchecker');
            if ($spamcheckerExt instanceof Extension) {
                $extService->deactivate($spamcheckerExt);
                $extService->uninstall('mod', 'spamchecker');
            }

            $this->di['cache']->delete('config_mod_spamchecker');
            $this->di['cache']->delete('config_mod_antispam');
        } catch (\Exception $e) {
            error_log('Spamchecker to Anti-Spam migration error: ' . $e->getMessage());
        }

        $fileActions = [
            Path::join(PATH_MODS, 'Spamchecker') => 'unlink',
        ];
        $this->executeFileActions($fileActions);
    }

    private function patch56(): void
    {
        $length = $this->getColumnLength('tld', 'tld');

        if ($length !== null && $length < 64) {
            $this->executeSql('ALTER TABLE `tld` MODIFY `tld` VARCHAR(64) DEFAULT NULL;');
        }

        $this->executeSql("UPDATE `setting` SET `public` = 0 WHERE `param` = 'last_patch';");

        try {
            $finder = new Finder();
            $finder->directories()->in(PATH_MODS)->depth('== 1')->name('/^html_(admin|client|email)$/');

            foreach ($finder as $dir) {
                $modulePath = Path::getDirectory($dir->getPathname());
                $area = substr($dir->getFilename(), 5);
                $replacementPath = Path::join($modulePath, 'templates', $area);

                if (!$this->filesystem->exists($replacementPath)) {
                    continue;
                }

                try {
                    $this->filesystem->remove($dir->getPathname());
                } catch (IOException $e) {
                    error_log($e->getMessage());
                }
            }
        } catch (\Symfony\Component\Finder\Exception\DirectoryNotFoundException) {
            throw new Exception('The modules directory does not exist. Cannot apply patch 56.');
        }

        $this->executeFileActions([
            Path::join(PATH_LIBRARY, 'Box', 'TwigLoader.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'TwigExtensions.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'TwigExtensions', 'DebugBar.php') => 'unlink',
        ]);
    }

    private function patch57(): void
    {
        $legacyHashes = [
            'mod_client_confirm' => ['7473f9235472556c07fbb28ca81cd32d5ba059251520938a113322cf9d63e9cf', 'f16aa27ac0c8c504aee96f76177aae7f57c3165d9207b1a4367c1d60da7b44b5'],
            'mod_client_password_reset_information' => ['f4e810c0a880b72dac35d7d7c1317fabdbc253c94b2c40387e72300fc502e75b', '90b35823f1a4dd26452e7fda276b3d73e9a84efb93ad36b70dfe31b11ef533f9'],
            'mod_client_password_reset_request' => ['7afb81438ba5fe02b0cb1de5e2a0adafa1d54163fa6db3c594b052ae41d7a702', 'b21dd141467890e4c95a4d1adbf52d692905f352d80db77467ed40f8280e0d58'],
            'mod_client_signup' => ['6f2cbf4dce2868d78962dd5f558782dc39932b94eff5d94a74b4c0b25bd5bc11', '5bee627ec67d303672375552c78b87e6126c25cffb935f44aa18db1493ffda8f'],
            'mod_email_test' => ['4027353f3eb85a550b896902875f774342102ca7acfd2b12f13facc4caca8a54', '148ef2a8b29e8d8a8fee6bfb793444185388c7887c690699065fbd336b4f4ea0'],
            'mod_invoice_created' => ['60efe042a16f3841b2194dc2324847d7d0ba9dcf91c4dee4ced6c9fb8fc7ff85', '7a34fabdb113b81c6f9e83624518ae669ae4e83bb3c4e6a0115aaa5c902830d2'],
            'mod_invoice_due_after' => ['25f93a3b7c96de7817ed0a39e7e881bc3cfe502e882a31bd821ee7ddfc90fce6', 'db783631386791d6289f446b61faef3725457a3aaa20b8af6c078280b1b3b9d9'],
            'mod_invoice_paid' => ['f652477160cbed14cd4721f1d07f6b1013dfa5f07a14f6917fca929a906b82cf', '2eea663a8302b99800b384ccec9b9cc1766c461904914d0591cbae8e307b97ed'],
            'mod_invoice_payment_reminder' => ['6384d7498e061a983796a0b6a0e6cff59f88c19512be044538d4f34809325fc9', 'd589ca24eaae34ebed6df5102739e4a4d5dc0f8744772a9b4d602096e377b910'],
            'mod_serviceapikey_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'de9258ac2a48938f47f51f8e4b1c97731137b68d33e7109f576f2b6a89c4986d'],
            'mod_serviceapikey_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_serviceapikey_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_serviceapikey_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_serviceapikey_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicecustom_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'ab6cfb1c88010cdd10ca310ca41c8ed4ae3d128199ea2be7abdd597bd5f33aff'],
            'mod_servicecustom_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_servicecustom_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicecustom_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicecustom_unsuspended' => ['c3db0c91caadb6fa1b107613271aada2ec237a209ad6743b5e574ef9827c7eb4', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicedomain_activated' => ['86bae76df848375254eb517edd07e7427ad3592e826198b1c5b6acc802feb9d2', '168e68757aa53b3f301f04d878df0dfdf0ea4213a1b37d595be885156c6a24bf'],
            'mod_servicedomain_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicedomain_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicedomain_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicedownloadable_activated' => ['8b6b568e1c5e7cef0c431ef488d9ed26d846fdf44770f48c9665cce81b6f89c9', '2edf67abf09e55967c70a7157d3d988c6ac9d32fd67edaf911c6b65ae8d118bc'],
            'mod_servicehosting_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', '35b4d6cd75ce2fbca69244da44d23f00c823e354d5538818dd1313f1af3ffc8c'],
            'mod_servicehosting_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_servicehosting_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicehosting_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicehosting_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicelicense_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'b005b720b43aab8299220e3a5a6fe547c1d5ad9755adc6e3c6efe5fe4546adb6'],
            'mod_servicelicense_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_servicelicense_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicelicense_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicelicense_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_staff_client_order' => ['070ec5ab4051913d7e4d62904da33f5fddbc35cf6a7a2d71df281d43baa4254b', '4846afa1c791dfcbf17e6e001f1565254a29d9ca0829ff89853b14ecf8f89294'],
            'mod_staff_client_signup' => ['13018c8f888668bbf731367ca3388a34f7170ff213bf1fa07771c375c4aae775', 'a573783ce28183abe13257c5b2663f750f8e37eb9056aad0c6abf203573d2f83'],
            'mod_staff_password_reset_approve' => ['f4e810c0a880b72dac35d7d7c1317fabdbc253c94b2c40387e72300fc502e75b', '8e3a3e5e4ffb9b393c08bcef9c07818a73c70d8c25a6cbed3f9ef339008e32bb'],
            'mod_staff_password_reset_request' => ['7afb81438ba5fe02b0cb1de5e2a0adafa1d54163fa6db3c594b052ae41d7a702', 'f6b1527a328af6b4e1c7a0f4d96d4cea7fa328a053508dc614385d39d78cc925'],
            'mod_staff_ticket_close' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'd54e0d97b88218404c731929eb2a39857ee422070e857802374626b72bf84b2f'],
            'mod_staff_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'dbe1dee98606a36285a79d019e6180e62ee04cc7e3c3f16d34045d6c70aaf78e'],
            'mod_staff_ticket_reply' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '87238cbde59fd9faa31c8e0d6039924ad6068f1ca3858ad91b06cc4633243153'],
            'mod_support_helpdesk_ticket_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '709e2ff17c01e1056ef4824e507e4474a6eeb1e3f35d5acd1c646074d98cc5d6'],
            'mod_support_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '78fe28d46cba9f344b9a2045114b1a7d723dc8a62602690413ecac30287bcae2'],
            'mod_support_ticket_staff_close' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '965d0369564e2edc832db28564e414b960f9559213750d7f1f1baf0b1cceb2a7'],
            'mod_support_ticket_staff_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '98ff5f36b64fb0f874048c7dd44310e98ecf2b08f49e8dab701d9d4bb5e81ba5'],
            'mod_support_ticket_staff_reply' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '74aea13a2cbe71aee7b8071480fb4b6767d5690589070d1a06721002618ed29f'],
        ];

        $templates = $this->fetchAll(
            'SELECT id, action_code, subject, content FROM email_template WHERE is_overridden = 1 AND is_custom = 0'
        );

        foreach ($templates as $template) {
            $code = (string) ($template['action_code'] ?? '');
            if (!isset($legacyHashes[$code])) {
                continue;
            }

            $subject = (string) ($template['subject'] ?? '');
            $content = (string) ($template['content'] ?? '');

            [$oldSubjectHash, $oldContentHash] = $legacyHashes[$code];
            if (hash('sha256', $subject) !== $oldSubjectHash || hash('sha256', $content) !== $oldContentHash) {
                continue;
            }

            $default = $this->getDefaultEmailTemplateData($code);
            if ($default === null) {
                continue;
            }

            $this->executeSql(
                'UPDATE email_template SET is_overridden = 0, subject = :subject, content = :content WHERE id = :id',
                [
                    'subject' => $default['subject'],
                    'content' => $default['content'],
                    'id' => $template['id'],
                ]
            );
        }
    }

    private function patch58(): void
    {
        $gateways = $this->fetchAll(
            "SELECT id, config FROM pay_gateway WHERE gateway = 'Custom'"
        );

        foreach ($gateways as $gateway) {
            $config = json_decode($gateway['config'] ?? '', true);
            if (!is_array($config)) {
                continue;
            }

            $fields = ['single', 'recurrent'];
            $needsSave = false;
            foreach ($fields as $field) {
                if (isset($config[$field]) && is_string($config[$field]) && preg_match('/\b(function|include|import|extends|range|max|min|dump|system|guest\.|admin\.|client\.)\b/i', $config[$field])) {
                    $this->di['logger']->setChannel('update')->warning('Custom payment adapter template for gateway ID %s contained incompatible Twig syntax and has been cleared. Please re-create it with compatible syntax.', $gateway['id']);
                    unset($config[$field]);
                    $needsSave = true;
                }
            }

            if ($needsSave) {
                $this->updateTable('pay_gateway', [
                    'config' => json_encode($config, JSON_UNESCAPED_SLASHES),
                ], ['id' => $gateway['id']]);
            }
        }
    }

    private function patch59(): void
    {
        try {
            $this->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_wysiwyg' OR (rel_type = 'mod' AND rel_id = 'wysiwyg')");
            $this->executeSql("DELETE FROM extension WHERE type = 'mod' AND name = 'wysiwyg'");
            $this->di['cache']->delete('config_mod_wysiwyg');
        } catch (\Exception $e) {
            error_log('Wysiwyg cleanup migration error: ' . $e->getMessage());
        }

        $this->executeFileActions([
            Path::join(PATH_MODS, 'Wysiwyg') => 'unlink',
        ]);
    }

    private function refreshComposerAutoloader(): void
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
            throw new Exception('Unable to load the Symfony UID package from Composer. Please reinstall dependencies and try again.');
        }
    }

    private function registerSymfonyUidAutoloader(): void
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

    private function getDefaultEmailTemplateData(string $code): ?array
    {
        $path = $this->getDefaultEmailTemplatePath($code);
        if ($path === null) {
            return null;
        }

        $template = $this->filesystem->readFile($path);

        $subject = ucwords(str_replace('_', ' ', $code));
        preg_match('#{%\\s*block subject\\s*%}(.*?){%\\s*endblock\\s*%}#s', $template, $subjectMatches);
        if (isset($subjectMatches[1])) {
            $subject = $subjectMatches[1];
        }

        $content = '';
        preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $template, $contentMatches);
        if (isset($contentMatches[1])) {
            $content = $contentMatches[1];
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    private function getDefaultEmailTemplatePath(string $code): ?string
    {
        $matches = [];
        if (!preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', $code, $matches)) {
            return null;
        }

        $path = Path::join(PATH_MODS, ucfirst($matches[1]), 'templates/email', "{$code}.html.twig");

        return $this->filesystem->exists($path) ? $path : null;
    }

    private function patch60(): void
    {
        $this->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND rel_type = 'mod' AND rel_id = 'Paidsupport' AND meta_key = 'listener'");
        $this->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_paidsupport' AND meta_key = 'config'");

        if ($this->di !== null && $this->di->offsetExists('cache')) {
            $this->di['cache']->delete('config_mod_paidsupport');
        }
    }

    private function patch61(): void
    {
        $columns = [];

        if ($this->tableHasColumn('currency', 'format')) {
            $columns[] = 'DROP COLUMN format';
        }

        if ($this->tableHasColumn('currency', 'price_format')) {
            $columns[] = 'DROP COLUMN price_format';
        }

        if ($columns !== []) {
            $this->executeSql('ALTER TABLE currency ' . implode(', ', $columns));
        }
    }

    private function patch62(): void
    {
        $this->executeSql("UPDATE invoice_item SET period = NULL WHERE period IN ('0', '')");
        $this->executeSql("UPDATE client_order SET period = NULL WHERE period IN ('0', '')");
        $this->executeSql("UPDATE subscription SET period = NULL WHERE period IN ('0', '')");
        $this->executeSql("UPDATE transaction SET s_period = NULL WHERE s_period IN ('0', '')");
    }

    private function patch63(): void
    {
        if ($this->tableHasColumn('currency', 'title')) {
            $this->executeSql('ALTER TABLE currency DROP COLUMN title');
        }
    }

    private function patch64(): void
    {
        $this->migrateGatewayAssetsToPublicDirectory();
        $this->migrateDefaultBrandingAssetsToPublicDirectory();

        $this->executeFileActions([
            Path::join(PATH_LIBRARY, 'Api', 'API.js') => 'unlink',
            Path::join(PATH_MODS, 'Wysiwyg') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'html', 'mod_wysiwyg_js.html.twig') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'html', 'mod_wysiwyg_js.html.twig') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.js.map') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.css') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.css.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.js.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.css') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.css.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'css', 'markdown.css') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'css', 'markdown.css') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'css', 'markdown.css.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'img', 'logo.png') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'img', 'logo.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'img', 'logo_white.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'favicon.ico') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'img', 'logo.png') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'img', 'logo.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'img', 'logo_white.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'favicon.ico') => 'unlink',
        ]);
    }

    private function patch65(): void
    {
        if (!$this->tableHasColumn('service_downloadable', 'stored_filename')) {
            $this->executeSql('ALTER TABLE `service_downloadable` ADD COLUMN `stored_filename` VARCHAR(100) DEFAULT NULL AFTER `filename`;');
        }

        $this->downloadableStorageMigrationMap = [];
        $this->migrateDownloadableProductStorageKeys();
        $this->migrateDownloadableServiceStorageKeys();
        $this->migrateDownloadableOrderStorageKeys();
    }

    private function patch66(): void
    {
        // The original removal/migration patches for these modules only handled their data
        // migrations. Installations that already ran those patches need a new patch level
        // to clean up stale extension records and module directories left on disk.
        $this->executeSql("DELETE FROM extension_meta WHERE extension IN ('mod_paidsupport', 'mod_servicemembership') OR (rel_type = 'mod' AND LOWER(rel_id) IN ('paidsupport', 'servicemembership'))");
        $this->executeSql("DELETE FROM extension WHERE type = 'mod' AND LOWER(name) IN ('paidsupport', 'servicemembership')");

        $this->executeFileActions([
            Path::join(PATH_MODS, 'Paidsupport') => 'unlink',
            Path::join(PATH_MODS, 'Servicemembership') => 'unlink',
        ]);
    }

    private function patch67(): void
    {
        // Add hash_expires_at column to invoice table. New invoices (and resends of
        // existing ones) get a hash_expires_at value computed from the
        // invoice_hash_lifetime_days system setting. NULL means "never expires"
        // and is the default for pre-existing rows.
        if (!$this->tableHasColumn('invoice', 'hash_expires_at')) {
            $this->executeSql('ALTER TABLE `invoice` ADD COLUMN `hash_expires_at` DATETIME DEFAULT NULL AFTER `updated_at`');
        }

        $this->executeSql(
            'INSERT INTO setting (param, value, public, category, hash, created_at, updated_at)
             VALUES (:param, :value, 0, :category, :hash, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE value = :value, updated_at = :updated_at',
            [
                'param' => 'invoice_hash_lifetime_days',
                'value' => '90',
                'category' => null,
                'hash' => null,
                'created_at' => '2026-06-01 12:00:00',
                'updated_at' => '2026-06-01 12:00:00',
            ]
        );

        // Regenerate invoice hashes that fall outside the modern 30-60 lowercase
        // hex format enforced by the new guest API regex validation. The client
        // area, gateway return URLs, and email links all build URLs from the
        // hash, so NULLing it (as an earlier revision did) broke those URLs —
        // see https://github.com/FOSSBilling/FOSSBilling/issues/3791.
        $expires = $this->computeInvoiceHashExpiration();
        $rows = $this->fetchAll(
            "SELECT id FROM invoice WHERE hash IS NOT NULL
               AND (LENGTH(hash) < 30 OR LENGTH(hash) > 60 OR CONVERT(hash USING utf8mb4) COLLATE utf8mb4_bin NOT REGEXP '^[a-f0-9]+$')"
        );
        foreach ($rows as $row) {
            $this->executeSql(
                'UPDATE invoice SET hash = :hash, hash_expires_at = :expires WHERE id = :id',
                [
                    'hash' => bin2hex(random_bytes(random_int(15, 30))),
                    'expires' => $expires,
                    'id' => $row['id'],
                ]
            );
        }
    }

    private function patch68(): void
    {
        $row = $this->fetchOne(
            "SELECT meta_value FROM extension_meta WHERE extension = 'mod_cron' AND meta_key = 'config'",
        );

        if (!is_string($row) || $row === '') {
            return;
        }

        $configJson = $this->di['crypt']->decrypt($row, Config::getProperty('info.salt'));
        if (!is_string($configJson)) {
            return;
        }

        $config = json_decode($configJson, true);
        if (!is_array($config) || empty($config['guest_cron']) || !empty($config['cron_hash'])) {
            return;
        }

        $config['cron_hash'] = bin2hex(random_bytes(32));
        $encrypted = $this->di['crypt']->encrypt(json_encode($config, JSON_THROW_ON_ERROR), Config::getProperty('info.salt'));

        $this->executeSql(
            "UPDATE extension_meta SET meta_value = :config WHERE extension = 'mod_cron' AND meta_key = 'config'",
            ['config' => $encrypted],
        );
    }

    private function patch69(): void
    {
        if (!$this->tableHasColumn('email_template', 'last_error')) {
            $this->executeSql('ALTER TABLE `email_template` ADD COLUMN `last_error` TEXT DEFAULT NULL');
        }

        if (!$this->tableHasColumn('email_template', 'error_checked_at')) {
            $this->executeSql('ALTER TABLE `email_template` ADD COLUMN `error_checked_at` DATETIME DEFAULT NULL');
        }
    }

    private function patch70(): void
    {
        $this->executeSql(
            "UPDATE client_order co
             LEFT JOIN invoice i ON i.id = co.unpaid_invoice_id AND i.status = 'unpaid'
             SET co.unpaid_invoice_id = NULL
             WHERE co.unpaid_invoice_id IS NOT NULL
               AND i.id IS NULL"
        );
    }

    private function patch71(): void
    {
        // Ensure the invoice table has the gateway_id, text_1, and text_2
        // columns. These have been part of structure.sql for a long time, but
        // databases upgraded from very old installations (e.g. BoxBilling era)
        // may be missing them, which produces PHP "Undefined array key"
        // warnings in Invoice\Service::toApiArray().
        if (!$this->tableHasColumn('invoice', 'gateway_id')) {
            $this->executeSql('ALTER TABLE `invoice` ADD COLUMN `gateway_id` int(11) DEFAULT NULL');
        }

        if (!$this->tableHasColumn('invoice', 'text_1')) {
            $this->executeSql('ALTER TABLE `invoice` ADD COLUMN `text_1` text');
        }

        if (!$this->tableHasColumn('invoice', 'text_2')) {
            $this->executeSql('ALTER TABLE `invoice` ADD COLUMN `text_2` text');
        }
    }

    private function patch72(): void
    {
        $this->executeFileActions([
            Path::join(PATH_LIBRARY, 'Model', 'Product.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductCategory.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductCustom.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductDomain.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductDomainTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductDownloadable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductHosting.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductLicense.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductPayment.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Promo.php') => 'unlink',
            Path::join(PATH_CACHE, 'classMap.php') => 'unlink',
            Path::join(PATH_CACHE, 'fallbackClassMap.php') => 'unlink',
        ]);

        if (!$this->tableExists('promo_redemption')) {
            $this->executeSql(
                "CREATE TABLE `promo_redemption` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `promo_id` bigint(20) DEFAULT NULL,
                    `client_id` bigint(20) DEFAULT NULL,
                    `client_order_id` bigint(20) DEFAULT NULL,
                    `invoice_id` bigint(20) DEFAULT NULL,
                    `phase` varchar(30) NOT NULL DEFAULT 'checkout',
                    `status` varchar(30) NOT NULL DEFAULT 'reserved',
                    `discount_amount` decimal(18,2) DEFAULT NULL,
                    `currency` varchar(20) DEFAULT NULL,
                    `committed_at` datetime DEFAULT NULL,
                    `released_at` datetime DEFAULT NULL,
                    `release_reason` varchar(100) DEFAULT NULL,
                    `created_at` datetime DEFAULT NULL,
                    `updated_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `promo_id_idx` (`promo_id`),
                    KEY `client_id_idx` (`client_id`),
                    KEY `client_order_id_idx` (`client_order_id`),
                    KEY `invoice_id_idx` (`invoice_id`),
                    KEY `phase_idx` (`phase`),
                    KEY `status_idx` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
            );
        }

        $schemaManager = $this->di['dbal']->createSchemaManager();
        $table = $schemaManager->introspectTable('promo_redemption');
        $columns = [];

        if (!$table->hasColumn('status')) {
            $columns[] = "ADD COLUMN `status` varchar(30) NOT NULL DEFAULT 'reserved' AFTER `phase`";
        }

        if (!$table->hasColumn('committed_at')) {
            $columns[] = 'ADD COLUMN `committed_at` datetime DEFAULT NULL AFTER `currency`';
        }

        if (!$table->hasColumn('released_at')) {
            $columns[] = 'ADD COLUMN `released_at` datetime DEFAULT NULL AFTER `committed_at`';
        }

        if (!$table->hasColumn('release_reason')) {
            $columns[] = 'ADD COLUMN `release_reason` varchar(100) DEFAULT NULL AFTER `released_at`';
        }

        if ($columns !== []) {
            $this->executeSql('ALTER TABLE promo_redemption ' . implode(', ', $columns));
        }

        $table = $schemaManager->introspectTable('promo_redemption');
        $expectedIndexes = [
            'promo_id_idx' => 'promo_id',
            'client_id_idx' => 'client_id',
            'client_order_id_idx' => 'client_order_id',
            'invoice_id_idx' => 'invoice_id',
            'phase_idx' => 'phase',
            'status_idx' => 'status',
        ];

        foreach ($expectedIndexes as $indexName => $columnName) {
            if (!$table->hasIndex($indexName)) {
                $this->executeSql(sprintf(
                    'ALTER TABLE promo_redemption ADD INDEX `%s` (`%s`)',
                    $indexName,
                    $columnName
                ));
            }
        }

        $existingRedemptions = (int) $this->di['dbal']->executeQuery('SELECT COUNT(id) FROM promo_redemption')->fetchOne();
        if ($existingRedemptions === 0) {
            $orders = $this->di['dbal']->executeQuery(
                'SELECT id, promo_id, client_id, promo_used, discount, currency, created_at, updated_at
                 FROM client_order
                 WHERE promo_id IS NOT NULL'
            )->fetchAllAssociative();

            foreach ($orders as $order) {
                $checkoutCreatedAt = $order['created_at'] ?? date('Y-m-d H:i:s');
                $renewalCreatedAt = $order['updated_at'] ?: $checkoutCreatedAt;
                $discountAmount = $order['discount'] !== null ? (float) $order['discount'] : null;

                $this->di['dbal']->insert('promo_redemption', [
                    'promo_id' => $order['promo_id'],
                    'client_id' => $order['client_id'],
                    'client_order_id' => $order['id'],
                    'invoice_id' => null,
                    'phase' => 'checkout',
                    'status' => 'committed',
                    'discount_amount' => $discountAmount,
                    'currency' => $order['currency'],
                    'committed_at' => $checkoutCreatedAt,
                    'released_at' => null,
                    'release_reason' => null,
                    'created_at' => $checkoutCreatedAt,
                    'updated_at' => $checkoutCreatedAt,
                ]);

                $renewalCount = max(0, ((int) ($order['promo_used'] ?? 1)) - 1);
                for ($i = 0; $i < $renewalCount; ++$i) {
                    $this->di['dbal']->insert('promo_redemption', [
                        'promo_id' => $order['promo_id'],
                        'client_id' => $order['client_id'],
                        'client_order_id' => $order['id'],
                        'invoice_id' => null,
                        'phase' => 'renewal',
                        'status' => 'committed',
                        'discount_amount' => $discountAmount,
                        'currency' => $order['currency'],
                        'committed_at' => $renewalCreatedAt,
                        'released_at' => null,
                        'release_reason' => null,
                        'created_at' => $renewalCreatedAt,
                        'updated_at' => $renewalCreatedAt,
                    ]);
                }
            }
        }

        // Normalize rows written by a previous partial migration run that pre-dated the
        // status column; those rows have a NULL/empty status.  Legitimate 'reserved'
        // rows created by concurrent checkouts are left untouched so the payment flow
        // can commit or release them normally.
        $this->executeSql("
            UPDATE promo_redemption
            SET
                status = 'committed',
                committed_at = COALESCE(committed_at, created_at),
                release_reason = NULL
            WHERE status IS NULL OR status = ''
        ");
    }

    private function patch73(): void
    {
        // Merge guest/public support tickets into the regular support ticket tables.
        // See https://github.com/FOSSBilling/FOSSBilling/pull/3799
        if (!$this->tableHasColumn('support_ticket', 'access_hash')) {
            $this->executeSql('ALTER TABLE `support_ticket` ADD COLUMN `access_hash` VARCHAR(255) DEFAULT NULL AFTER `client_id`;');
        }

        if (!$this->tableHasColumn('support_ticket', 'author_name')) {
            $this->executeSql('ALTER TABLE `support_ticket` ADD COLUMN `author_name` VARCHAR(255) DEFAULT NULL AFTER `access_hash`;');
        }

        if (!$this->tableHasColumn('support_ticket', 'author_email')) {
            $this->executeSql('ALTER TABLE `support_ticket` ADD COLUMN `author_email` VARCHAR(255) DEFAULT NULL AFTER `author_name`;');
        }

        if (!$this->tableHasIndex('support_ticket', 'access_hash_idx')) {
            $this->executeSql('ALTER TABLE `support_ticket` ADD INDEX `access_hash_idx` (`access_hash`);');
        }

        $this->executeSql("
            DELETE FROM email_template
            WHERE action_code IN (
                'mod_staff_pticket_close',
                'mod_staff_pticket_open',
                'mod_staff_pticket_reply',
                'mod_support_pticket_open',
                'mod_support_pticket_staff_close',
                'mod_support_pticket_staff_open',
                'mod_support_pticket_staff_reply'
            )
        ");

        // Remove obsolete guest/public ticket email templates from extracted update archives.
        $this->executeFileActions([
            Path::join(PATH_MODS, 'Staff', 'templates', 'email', 'mod_staff_pticket_close.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Staff', 'templates', 'email', 'mod_staff_pticket_open.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Staff', 'templates', 'email', 'mod_staff_pticket_reply.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_open.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_staff_close.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_staff_open.html.twig') => 'unlink',
            Path::join(PATH_MODS, 'Support', 'templates', 'email', 'mod_support_pticket_staff_reply.html.twig') => 'unlink',
        ]);

        $row = $this->fetchOne(
            "SELECT meta_value FROM extension_meta WHERE extension = 'mod_support' AND meta_key = 'config'",
        );

        if (is_string($row) && $row !== '') {
            $configJson = $this->di['crypt']->decrypt($row, Config::getProperty('info.salt'));
            if (is_string($configJson)) {
                $config = json_decode($configJson, true);
                if (is_array($config) && isset($config['disable_public_tickets']) && !isset($config['disable_guest_tickets'])) {
                    $config['disable_guest_tickets'] = $config['disable_public_tickets'];
                    unset($config['disable_public_tickets']);
                    $encrypted = $this->di['crypt']->encrypt(json_encode($config, JSON_THROW_ON_ERROR), Config::getProperty('info.salt'));
                    $this->executeSql(
                        "UPDATE extension_meta SET meta_value = :config WHERE extension = 'mod_support' AND meta_key = 'config'",
                        ['config' => $encrypted],
                    );
                }
            }
        }

        if (!$this->tableExists('support_p_ticket')) {
            return;
        }

        // Move legacy support_p_ticket rows into support_ticket, preserving guest access hashes.
        $defaultHelpdeskId = $this->fetchOne('SELECT id FROM support_helpdesk ORDER BY id ASC LIMIT 1');
        if (!$defaultHelpdeskId) {
            $now = date('Y-m-d H:i:s');
            $this->executeSql(
                'INSERT INTO support_helpdesk (name, close_after, can_reopen, created_at, updated_at) VALUES (:name, :close_after, :can_reopen, :created_at, :updated_at)',
                [
                    'name' => 'General',
                    'close_after' => 24,
                    'can_reopen' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $defaultHelpdeskId = $this->fetchOne('SELECT id FROM support_helpdesk ORDER BY id ASC LIMIT 1');
        }

        $publicTickets = $this->fetchAll('SELECT * FROM support_p_ticket ORDER BY id ASC');
        foreach ($publicTickets as $publicTicket) {
            $this->executeSql(
                'INSERT INTO support_ticket (support_helpdesk_id, client_id, access_hash, author_name, author_email, subject, status, created_at, updated_at)
                 VALUES (:support_helpdesk_id, NULL, :access_hash, :author_name, :author_email, :subject, :status, :created_at, :updated_at)',
                [
                    'support_helpdesk_id' => $defaultHelpdeskId,
                    'access_hash' => $publicTicket['hash'],
                    'author_name' => $publicTicket['author_name'],
                    'author_email' => $publicTicket['author_email'],
                    'subject' => $publicTicket['subject'],
                    'status' => $publicTicket['status'],
                    'created_at' => $publicTicket['created_at'],
                    'updated_at' => $publicTicket['updated_at'],
                ]
            );

            $ticketId = (int) $this->getPdo()->lastInsertId();
            $messages = $this->tableExists('support_p_ticket_message')
                ? $this->fetchAll('SELECT * FROM support_p_ticket_message WHERE support_p_ticket_id = :id ORDER BY id ASC', [
                    'id' => $publicTicket['id'],
                ]) : [];

            foreach ($messages as $message) {
                $this->executeSql(
                    'INSERT INTO support_ticket_message (support_ticket_id, admin_id, content, ip, created_at, updated_at)
                     VALUES (:support_ticket_id, :admin_id, :content, :ip, :created_at, :updated_at)',
                    [
                        'support_ticket_id' => $ticketId,
                        'admin_id' => $message['admin_id'],
                        'content' => $message['content'],
                        'ip' => $message['ip'],
                        'created_at' => $message['created_at'],
                        'updated_at' => $message['updated_at'],
                    ]
                );
            }
        }

        if ($this->tableExists('support_p_ticket_message')) {
            $this->executeSql('DROP TABLE `support_p_ticket_message`;');
        }

        $this->executeSql('DROP TABLE `support_p_ticket`;');
    }

    private function patch74(): void
    {
        // Backfill hashes NULLed by the original revision of patch67.
        $expires = $this->computeInvoiceHashExpiration();
        $rows = $this->fetchAll(
            "SELECT id FROM invoice WHERE hash IS NULL OR LENGTH(hash) < 30 OR LENGTH(hash) > 60 OR CONVERT(hash USING utf8mb4) COLLATE utf8mb4_bin NOT REGEXP '^[a-f0-9]+$'"
        );
        foreach ($rows as $row) {
            $this->executeSql(
                'UPDATE invoice SET hash = :hash, hash_expires_at = :expires WHERE id = :id',
                [
                    'hash' => bin2hex(random_bytes(random_int(15, 30))),
                    'expires' => $expires,
                    'id' => $row['id'],
                ]
            );
        }
    }

    private function patch75(): void
    {
        // Drop the legacy `client.document_type` and `client.document_nr` columns.
        // Existing values are copied into the first free `custom_N` slot on each client.
        if (!$this->tableHasColumn('client', 'document_nr')) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT id, document_nr FROM `client` WHERE `document_nr` IS NOT NULL AND `document_nr` <> ''"
        );

        $customSlots = ['custom_1', 'custom_2', 'custom_3', 'custom_4', 'custom_5', 'custom_6', 'custom_7', 'custom_8', 'custom_9', 'custom_10'];

        foreach ($rows as $row) {
            $clientId = (int) $row['id'];
            $documentNr = (string) $row['document_nr'];

            $existing = $this->fetchAll(
                'SELECT custom_1, custom_2, custom_3, custom_4, custom_5, custom_6, custom_7, custom_8, custom_9, custom_10 FROM client WHERE id = :id',
                ['id' => $clientId]
            );
            $clientRow = $existing[0] ?? [];

            $targetSlot = null;
            foreach ($customSlots as $slot) {
                if (($clientRow[$slot] ?? null) === null || $clientRow[$slot] === '') {
                    $targetSlot = $slot;

                    break;
                }
            }

            if ($targetSlot !== null) {
                $this->executeSql(
                    sprintf('UPDATE `client` SET `%s` = :value WHERE id = :id', $targetSlot),
                    ['value' => $documentNr, 'id' => $clientId]
                );
            } else {
                $this->di['logger']->setChannel('update')->warning('patch75: client #%d has no free custom field slot; unmigrated document_nr was "%s".', $clientId, $documentNr);
            }
        }

        $this->executeSql('ALTER TABLE `client` DROP COLUMN `document_type`, DROP COLUMN `document_nr`;');
    }

    private function patch76(): void
    {
        // The `activity_client_email` table was missing an `updated_at` column,
        // but the Doctrine entity for it now expects one. Add the column for
        // installations that were created before the entity was migrated.
        if (!$this->tableHasColumn('activity_client_email', 'updated_at')) {
            $this->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `updated_at` datetime DEFAULT NULL AFTER `created_at`');
        }
    }

    private function patch77(): void
    {
        // The email queue table was renamed from `mod_email_queue` to
        // `email_queue` when the `QueuedEmail` Doctrine entity was introduced.
        // Rename it for installations that still use the legacy table name.
        if ($this->tableExists('mod_email_queue') && !$this->tableExists('email_queue')) {
            $this->executeSql('RENAME TABLE `mod_email_queue` TO `email_queue`');
        }
    }

    private function patch78(): void
    {
        // Rework admin groups and permissions
        //
        // This patch migrates from individual admin-scoped permissions to group permissions.
        // It allows admins to be a part of multiple groups,
        // sets up a hierarchy between admin groups and creates
        // a new protected group named Super Administrator.
        //
        // See https://github.com/FOSSBilling/FOSSBilling/pull/3821.
        if (!$this->tableHasColumn('admin_group', 'system_name')) {
            $this->executeSql('ALTER TABLE `admin_group` ADD COLUMN `system_name` VARCHAR(100) DEFAULT NULL AFTER `name`;');
        }

        if (!$this->tableHasColumn('admin_group', 'parent_id')) {
            $this->executeSql('ALTER TABLE `admin_group` ADD COLUMN `parent_id` bigint(20) DEFAULT NULL AFTER `system_name`;');
        }

        if (!$this->tableHasColumn('admin_group', 'permissions')) {
            $this->executeSql('ALTER TABLE `admin_group` ADD COLUMN `permissions` JSON AFTER `parent_id`;');
        }

        if (!$this->tableHasColumn('admin_group', 'protected')) {
            $this->executeSql("ALTER TABLE `admin_group` ADD COLUMN `protected` TINYINT(1) DEFAULT '0' AFTER `permissions`;");
        }

        if (!$this->tableHasIndex('admin_group', 'system_name')) {
            $this->executeSql('ALTER TABLE `admin_group` ADD UNIQUE INDEX `system_name` (`system_name`);');
        }

        if (!$this->tableHasIndex('admin_group', 'admin_group_parent_id_idx')) {
            $this->executeSql('ALTER TABLE `admin_group` ADD KEY `admin_group_parent_id_idx` (`parent_id`);');
        }

        if (!$this->tableExists('admin_group_member')) {
            $this->executeSql('CREATE TABLE `admin_group_member` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `admin_id` bigint(20) NOT NULL,
                `admin_group_id` bigint(20) NOT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `admin_group_member_unique` (`admin_id`, `admin_group_id`),
                KEY `admin_group_member_admin_id_idx` (`admin_id`),
                KEY `admin_group_member_group_id_idx` (`admin_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }

        // Convert the existing admin group into the protected Super Administrator group.
        $now = date('Y-m-d H:i:s');
        $superAdminGroupId = $this->fetchOne("SELECT id FROM admin_group WHERE system_name = 'super_admin' LIMIT 1");
        if (!$superAdminGroupId) {
            $firstGroupId = $this->fetchOne('SELECT id FROM admin_group WHERE id = 1 LIMIT 1');
            if ($firstGroupId) {
                $this->executeSql(
                    "UPDATE admin_group SET name = 'Super Administrator', system_name = 'super_admin', permissions = NULL, protected = 1, updated_at = :updated_at WHERE id = 1",
                    ['updated_at' => $now],
                );
                $superAdminGroupId = 1;
            } else {
                $this->executeSql(
                    "INSERT INTO admin_group (name, system_name, permissions, protected, created_at, updated_at) VALUES ('Super Administrator', 'super_admin', NULL, 1, :created_at, :updated_at)",
                    ['created_at' => $now, 'updated_at' => $now],
                );
                $superAdminGroupId = (int) $this->getPdo()->lastInsertId();
            }
        } else {
            $this->executeSql(
                'UPDATE admin_group SET protected = 1, permissions = NULL, updated_at = :updated_at WHERE id = :id',
                ['updated_at' => $now, 'id' => $superAdminGroupId],
            );
        }

        $this->executeSql(
            "INSERT INTO admin_group (name, system_name, parent_id, permissions, protected, created_at, updated_at) VALUES ('Migrated staff', 'migrated_staff', :parent_id, NULL, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = 'Migrated staff', parent_id = :parent_id, permissions = NULL, protected = 0, updated_at = :updated_at",
            ['parent_id' => $superAdminGroupId, 'created_at' => $now, 'updated_at' => $now],
        );
        $migratedStaffGroupId = (int) $this->fetchOne("SELECT id FROM admin_group WHERE system_name = 'migrated_staff' LIMIT 1");

        // Move legacy one-group-per-admin assignments into the new membership table before dropping the column.
        $this->executeSql('
            INSERT IGNORE INTO admin_group_member (admin_id, admin_group_id, created_at)
            SELECT id, admin_group_id, :created_at
            FROM admin
            WHERE admin_group_id IS NOT NULL
              AND (admin_group_id != :super_admin_group_id OR role = :role)
        ', [
            'created_at' => $now,
            'super_admin_group_id' => $superAdminGroupId,
            'role' => 'admin',
        ]);

        $this->executeSql('
            INSERT IGNORE INTO admin_group_member (admin_id, admin_group_id, created_at)
            SELECT id, :admin_group_id, :created_at
            FROM admin
            WHERE admin_group_id = :super_admin_group_id
              AND (role IS NULL OR role != :role)
        ', [
            'admin_group_id' => $migratedStaffGroupId,
            'created_at' => $now,
            'super_admin_group_id' => $superAdminGroupId,
            'role' => 'admin',
        ]);

        $this->executeSql('
            INSERT IGNORE INTO admin_group_member (admin_id, admin_group_id, created_at)
            SELECT id, :admin_group_id, :created_at
            FROM admin
            WHERE role = :role
        ', [
            'admin_group_id' => $superAdminGroupId,
            'created_at' => $now,
            'role' => 'admin',
        ]);

        // Drop legacy staff-level group ID columns after their data has been migrated.
        if ($this->tableHasColumn('admin', 'admin_group_id')) {
            if ($this->tableHasIndex('admin', 'admin_group_id_idx')) {
                $this->executeSql('ALTER TABLE `admin` DROP INDEX `admin_group_id_idx`;');
            }

            $this->executeSql('ALTER TABLE `admin` DROP COLUMN `admin_group_id`;');
        }

        if (!$this->tableHasColumn('admin', 'system_name')) {
            $this->executeSql('ALTER TABLE `admin` ADD COLUMN `system_name` varchar(100) DEFAULT NULL AFTER `id`;');
        }

        if ($this->tableHasColumn('admin', 'role')) {
            $this->executeSql("UPDATE `admin` SET `system_name` = 'cron' WHERE `role` = 'cron' AND (`system_name` IS NULL OR `system_name` = '') ORDER BY `id` ASC LIMIT 1;");
            $this->executeSql('ALTER TABLE `admin` DROP COLUMN `role`;');
        }

        if (!$this->tableHasIndex('admin', 'system_name')) {
            $this->executeSql('ALTER TABLE `admin` ADD UNIQUE KEY `system_name` (`system_name`);');
        }

        if ($this->tableHasColumn('admin', 'permissions')) {
            $this->executeSql('ALTER TABLE `admin` DROP COLUMN `permissions`;');
        }

        if ($this->tableHasColumn('admin', 'protected')) {
            $this->executeSql('ALTER TABLE `admin` DROP COLUMN `protected`;');
        }
    }

    private function patch79(): void
    {
        // Create better default groups with sensible permissions
        //
        // This patch creates new default groups named Support Lead and Support Staff
        // Support Lead is allowed to create/edit staff members and appoint them to groups below itself (in this case, the Support Staff group)
        // Support Staff is allowed to access and manage support tickets without any additional permissions
        //
        // This is part of the admin groups and permissions rework. See https://github.com/FOSSBilling/FOSSBilling/pull/3821
        $now = date('Y-m-d H:i:s');
        $superAdminGroupId = $this->fetchOne("SELECT id FROM admin_group WHERE system_name = 'super_admin' LIMIT 1");
        $supportLeadPermissions = json_encode([
            'support' => [
                'access' => true,
                'view' => true,
                'manage_tickets' => true,
                'manage_helpdesk' => true,
                'manage_canned' => true,
                'manage_kb' => true,
            ],
            'staff' => [
                'access' => true,
                'view' => true,
                'create_and_edit_staff' => true,
                'reset_staff_password' => true,
                'manage_groups' => true,
                'manage_settings' => true,
            ],
        ], JSON_THROW_ON_ERROR);
        $this->executeSql(
            "INSERT INTO admin_group (name, system_name, parent_id, permissions, protected, created_at, updated_at) VALUES ('Support Lead', 'support_lead', :parent_id, :permissions, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = 'Support Lead', parent_id = :parent_id, permissions = :permissions, protected = 0, updated_at = :updated_at",
            ['parent_id' => $superAdminGroupId, 'permissions' => $supportLeadPermissions, 'created_at' => $now, 'updated_at' => $now],
        );
        $supportLeadGroupId = (int) $this->fetchOne("SELECT id FROM admin_group WHERE system_name = 'support_lead' LIMIT 1");

        $supportStaffPermissions = json_encode([
            'support' => [
                'access' => true,
                'view' => true,
                'manage_tickets' => true,
            ],
        ], JSON_THROW_ON_ERROR);
        $this->executeSql(
            "INSERT INTO admin_group (name, system_name, parent_id, permissions, protected, created_at, updated_at) VALUES ('Support Staff', 'support_staff', :parent_id, :permissions, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = 'Support Staff', parent_id = :parent_id, permissions = :permissions, protected = 0, updated_at = :updated_at",
            ['parent_id' => $supportLeadGroupId, 'permissions' => $supportStaffPermissions, 'created_at' => $now, 'updated_at' => $now],
        );
    }

    private function patch80(): void
    {
        // #3856 started requiring an explicit "manage_settings" permission to view or edit a
        // module's settings (e.g. Scheduled Tasks), but existing staff groups that already had
        // general access to those modules never had the new permission granted, silently
        // locking non-super-admin staff out of settings pages they could previously use.
        // Grant it wherever a group already had module access, to preserve prior behavior.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3873
        //
        // The staff group form only submits checked checkboxes, so a group edited on or after
        // #3856 (2026-06-28, when the manage_settings checkbox first existed) that has no
        // manage_settings key made a deliberate choice to leave it unchecked, not a legacy gap.
        // Restrict the backfill to groups untouched since before that date so we don't clobber
        // an intentional choice, including the default groups patch79 just created above.
        $modules = [
            'activity', 'antispam', 'cookieconsent', 'cron', 'formbuilder',
            'invoice', 'massmailer', 'order', 'orderbutton', 'seo', 'support', 'theme',
        ];

        $groups = $this->fetchAll('SELECT id, permissions FROM admin_group WHERE permissions IS NOT NULL AND updated_at < :cutoff', [
            'cutoff' => '2026-06-28 00:00:00',
        ]);

        foreach ($groups as $group) {
            $permissions = json_decode((string) $group['permissions'], true);
            if (!is_array($permissions)) {
                continue;
            }

            $changed = false;
            foreach ($modules as $module) {
                if (($permissions[$module]['access'] ?? false) && !isset($permissions[$module]['manage_settings'])) {
                    $permissions[$module]['manage_settings'] = true;
                    $changed = true;
                }
            }

            if ($changed) {
                $this->executeSql('UPDATE admin_group SET permissions = :permissions, updated_at = :updated_at WHERE id = :id', [
                    'permissions' => json_encode($permissions),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $group['id'],
                ]);
            }
        }
    }

    private function patch81(): void
    {
        // Per-user timezone for clients and staff. NULL falls back to the system `i18n.timezone` config.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1028
        if (!$this->tableHasColumn('client', 'timezone')) {
            $this->executeSql('ALTER TABLE `client` ADD COLUMN `timezone` VARCHAR(64) DEFAULT NULL AFTER `lang`');
        }
        if (!$this->tableHasColumn('admin', 'timezone')) {
            $this->executeSql('ALTER TABLE `admin` ADD COLUMN `timezone` VARCHAR(64) DEFAULT NULL AFTER `api_token`');
        }
    }

    private function patch82(): void
    {
        // Per-staff-group restriction of "sent to staff" email notifications.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1247
        if (!$this->tableExists('email_template_group')) {
            $this->executeSql('CREATE TABLE `email_template_group` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `email_template_id` bigint(20) NOT NULL,
                `admin_group_id` bigint(20) NOT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email_template_group_unique` (`email_template_id`, `admin_group_id`),
                KEY `email_template_group_template_id_idx` (`email_template_id`),
                KEY `email_template_group_group_id_idx` (`admin_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }

        // Backfill: templates already sent to staff must keep reaching everyone
        // they used to reach, so link them to every staff group that already
        // exists. Templates created after this patch runs are auto-assigned by
        // Email\Service::assignAllGroupsToTemplate() when their row is first created.
        $this->executeSql("INSERT INTO email_template_group (email_template_id, admin_group_id, created_at)
            SELECT et.id, ag.id, NOW()
            FROM email_template et
            CROSS JOIN admin_group ag
            WHERE et.action_code IN ('mod_staff_client_order', 'mod_staff_ticket_open', 'mod_staff_ticket_reply', 'mod_staff_ticket_close', 'mod_staff_client_signup')
            AND NOT EXISTS (
                SELECT 1 FROM email_template_group etg
                WHERE etg.email_template_id = et.id AND etg.admin_group_id = ag.id
            )");
    }

    private function patch83(): void
    {
        if (!$this->tableHasIndex('invoice_item', 'invoice_item_pending_renewal_idx')) {
            $this->executeSql('ALTER TABLE `invoice_item` ADD INDEX `invoice_item_pending_renewal_idx` (`rel_id`(20), `type`, `task`, `status`, `invoice_id`)');
        }
    }

    private function patch84(): void
    {
        // Admins can now edit ticket replies; this table snapshots a message's prior content on each edit.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/2317
        if (!$this->tableExists('support_ticket_message_history')) {
            $this->executeSql('CREATE TABLE `support_ticket_message_history` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `support_ticket_message_id` bigint(20) NOT NULL,
                `admin_id` bigint(20) DEFAULT NULL,
                `content` text,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `support_ticket_message_id_idx` (`support_ticket_message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }
    }

    private function patch85(): void
    {
        // Raises the client custom field cap from 10 to 20.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3174
        for ($i = 11; $i <= 20; ++$i) {
            if (!$this->tableHasColumn('client', "custom_{$i}")) {
                $this->executeSql("ALTER TABLE `client` ADD COLUMN `custom_{$i}` text");
            }
        }
    }

    private function patch86(): void
    {
        // Allows queued emails (e.g. invoice notifications) to carry a file attachment such as a PDF copy.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1724
        if (!$this->tableHasColumn('email_queue', 'attachment_name')) {
            $this->executeSql('ALTER TABLE `email_queue` ADD COLUMN `attachment_name` varchar(255) DEFAULT NULL');
        }

        if (!$this->tableHasColumn('email_queue', 'attachment_content')) {
            $this->executeSql('ALTER TABLE `email_queue` ADD COLUMN `attachment_content` longblob DEFAULT NULL');
        }

        if (!$this->tableHasColumn('email_queue', 'attachment_mime')) {
            $this->executeSql('ALTER TABLE `email_queue` ADD COLUMN `attachment_mime` varchar(100) DEFAULT NULL');
        }
    }

    private function patch87(): void
    {
        // Carries the same attachment (e.g. a PDF invoice) into the sent-email activity log, so
        // resending a logged email (client or admin "resend") can reattach it.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1724
        if (!$this->tableHasColumn('activity_client_email', 'attachment_name')) {
            $this->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `attachment_name` varchar(255) DEFAULT NULL');
        }

        if (!$this->tableHasColumn('activity_client_email', 'attachment_content')) {
            $this->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `attachment_content` longblob DEFAULT NULL');
        }

        if (!$this->tableHasColumn('activity_client_email', 'attachment_mime')) {
            $this->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `attachment_mime` varchar(100) DEFAULT NULL');
        }
    }

    private function generateDownloadableStoredFilename(): string
    {
        do {
            $storedFilename = bin2hex(random_bytes(32));
            $filePath = Path::join(PATH_UPLOADS, $storedFilename);
        } while ($this->filesystem->exists($filePath));

        return $storedFilename;
    }

    private function copyLegacyDownloadableFile(string $filename): ?string
    {
        if (isset($this->downloadableStorageMigrationMap[$filename])) {
            return $this->downloadableStorageMigrationMap[$filename];
        }

        $legacyPath = Path::join(PATH_UPLOADS, md5($filename));
        if (!$this->filesystem->exists($legacyPath)) {
            return null;
        }

        $storedFilename = $this->generateDownloadableStoredFilename();
        $this->filesystem->copy($legacyPath, Path::join(PATH_UPLOADS, $storedFilename));
        $this->downloadableStorageMigrationMap[$filename] = $storedFilename;

        return $storedFilename;
    }

    private function migrateDownloadableProductStorageKeys(): void
    {
        $products = $this->fetchAll("SELECT id, config FROM product WHERE type = 'downloadable'");

        foreach ($products as $product) {
            $config = json_decode((string) $product['config'], true) ?: [];
            if (!isset($config['filename']) || isset($config['stored_filename'])) {
                continue;
            }

            $storedFilename = $this->copyLegacyDownloadableFile((string) $config['filename']);
            if ($storedFilename === null) {
                continue;
            }

            $config['stored_filename'] = $storedFilename;
            $this->executeSql('UPDATE product SET config = :config, updated_at = :updated_at WHERE id = :id', [
                'config' => json_encode($config),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $product['id'],
            ]);
        }
    }

    private function migrateDownloadableServiceStorageKeys(): void
    {
        $services = $this->fetchAll('SELECT sd.id, sd.filename, sd.stored_filename, co.id AS order_id, co.config AS order_config FROM service_downloadable sd LEFT JOIN client_order co ON sd.id = co.service_id AND co.service_type = "downloadable" WHERE sd.filename IS NOT NULL AND sd.filename != ""');
        $processedServiceUpdates = [];

        foreach ($services as $service) {
            if (!empty($service['stored_filename'])) {
                $storedFilename = (string) $service['stored_filename'];
            } else {
                $serviceId = (int) $service['id'];
                if (isset($processedServiceUpdates[$serviceId])) {
                    $storedFilename = $this->copyLegacyDownloadableFile((string) $service['filename']);
                } else {
                    $storedFilename = $this->copyLegacyDownloadableFile((string) $service['filename']);
                    if ($storedFilename === null) {
                        continue;
                    }

                    $this->executeSql('UPDATE service_downloadable SET stored_filename = :stored_filename, updated_at = :updated_at WHERE id = :id', [
                        'stored_filename' => $storedFilename,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id' => $service['id'],
                    ]);
                    $processedServiceUpdates[$serviceId] = true;
                }
            }

            if (empty($service['order_id'])) {
                continue;
            }

            $orderConfig = json_decode($service['order_config'] ?? '', true) ?: [];
            if (isset($orderConfig['stored_filename'])) {
                continue;
            }

            $orderConfig['filename'] ??= $service['filename'];
            $orderConfig['stored_filename'] = $storedFilename;
            $this->executeSql('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                'config' => json_encode($orderConfig),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $service['order_id'],
            ]);
        }
    }

    private function migrateDownloadableOrderStorageKeys(): void
    {
        $orders = $this->fetchAll("SELECT id, config FROM client_order WHERE service_type = 'downloadable' AND config LIKE '%filename%'");

        foreach ($orders as $order) {
            $config = json_decode($order['config'] ?? '', true) ?: [];
            if (!isset($config['filename']) || isset($config['stored_filename'])) {
                continue;
            }

            $storedFilename = $this->copyLegacyDownloadableFile((string) $config['filename']);
            if ($storedFilename === null) {
                continue;
            }

            $config['stored_filename'] = $storedFilename;
            $this->executeSql('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                'config' => json_encode($config),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $order['id'],
            ]);
        }
    }

    private function migrateDefaultBrandingAssetsToPublicDirectory(): void
    {
        $settings = [
            'company_logo' => [
                'public/branding/logo.svg',
                'themes/huraga/assets/img/logo.svg',
                'themes/huraga/assets/build/img/logo.svg',
            ],
            'company_logo_dark' => [
                'public/branding/logo-dark.svg',
                'themes/huraga/assets/img/logo_white.svg',
                'themes/huraga/assets/build/img/logo_white.svg',
            ],
            'company_favicon' => [
                'public/branding/favicon.ico',
                'themes/huraga/assets/favicon.ico',
                'themes/huraga/assets/build/favicon.ico',
            ],
        ];

        foreach ($settings as $param => $values) {
            $newValue = $values[0];
            $oldValues = array_slice($values, 1);

            foreach ($oldValues as $oldValue) {
                $this->executeSql('UPDATE setting SET value = :new_value WHERE param = :param AND value = :old_value', [
                    'new_value' => $newValue,
                    'param' => $param,
                    'old_value' => $oldValue,
                ]);
            }
        }
    }

    private function migrateGatewayAssetsToPublicDirectory(): void
    {
        $publicGatewayAssetsPath = Path::join(PATH_ROOT, 'public', 'gateways');
        $oldGatewayAssetPaths = array_unique([
            Path::join(PATH_ROOT, 'data', 'assets', 'gateways'),
            Path::join(PATH_DATA, 'assets', 'gateways'),
            Path::join(PATH_ROOT, 'public', 'assets', 'gateways'),
        ]);

        foreach ($oldGatewayAssetPaths as $oldGatewayAssetsPath) {
            if (!$this->filesystem->exists($oldGatewayAssetsPath)) {
                continue;
            }

            $this->filesystem->mkdir($publicGatewayAssetsPath, 0o755);

            $finder = new Finder();
            $finder->files()->in($oldGatewayAssetsPath)->depth('== 0');

            foreach ($finder as $file) {
                $target = Path::join($publicGatewayAssetsPath, $file->getFilename());
                if (!$this->filesystem->exists($target)) {
                    $this->filesystem->copy($file->getPathname(), $target);
                }
            }

            $this->executeFileActions([
                $oldGatewayAssetsPath => 'unlink',
            ]);
        }
    }
}

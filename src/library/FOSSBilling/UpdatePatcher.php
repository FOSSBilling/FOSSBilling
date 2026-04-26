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

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

class UpdatePatcher implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
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

    /**
     * Apply configuration file patches.
     */
    public function applyConfigPatches(): void
    {
        $currentConfig = Config::getConfig();

        if (empty($currentConfig)) {
            throw new Exception('Unable to load existing configuration');
        }

        $newConfig = $currentConfig;
        $newConfig['security']['mode'] ??= 'strict';
        $newConfig['security']['force_https'] ??= true;
        $newConfig['security']['session_lifespan'] ??= $newConfig['security']['cookie_lifespan'] ?? 7200;
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
        $newConfig['i18n']['timezone'] ??= $currentConfig['timezone'] ?? 'UTC';
        $newConfig['i18n']['date_format'] ??= 'medium';
        $newConfig['i18n']['time_format'] ??= 'short';
        $newConfig['db']['driver'] ??= 'pdo_mysql';
        $newConfig['db']['port'] ??= '3306';
        $newConfig['api']['throttle_delay'] ??= 2;
        $newConfig['api']['rate_span_login'] ??= 60;
        $newConfig['api']['rate_limit_login'] ??= 20;
        $newConfig['api']['CSRFPrevention'] ??= true;
        $newConfig['api']['rate_limit_whitelist'] ??= [];
        $newConfig['debug_and_monitoring'] ??= [];
        $newConfig['debug_and_monitoring']['debug'] ??= $newConfig['debug'] ?? false;
        $newConfig['debug_and_monitoring']['log_stacktrace'] ??= $newConfig['log_stacktrace'] ?? true;
        $newConfig['debug_and_monitoring']['stacktrace_length'] ??= $newConfig['stacktrace_length'] ?? 25;
        $newConfig['debug_and_monitoring']['report_errors'] ??= false;

        // Instance ID handling
        if (!class_exists(Uuid::class)) {
            $this->registerFallbackAutoloader();
        }
        $newConfig['info']['instance_id'] ??= Uuid::v4()->toString();
        $newConfig['info']['salt'] ??= $newConfig['salt'];

        // Remove the hardcoded protocol
        $newConfig['url'] = str_replace(['https://', 'http://'], '', $newConfig['url']);

        // Remove depreciated config keys/subkeys.
        $depreciatedConfigKeys = ['guzzle', 'locale', 'locale_date_format', 'locale_time_format', 'timezone', 'sef_urls', 'salt', 'path_logs', 'log_to_db'];
        $depreciatedConfigSubkeys = [
            'security' => 'cookie_lifespan',
            'db' => 'type',
        ];
        $newConfig = array_diff_key($newConfig, array_flip($depreciatedConfigKeys));
        foreach ($depreciatedConfigSubkeys as $key => $subkey) {
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
    public function applyCorePatches(): void
    {
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
                if ($action == 'unlink' && $this->filesystem->exists($file)) {
                    $this->filesystem->remove($file);
                } elseif ($this->filesystem->exists($file)) {
                    $this->filesystem->rename($file, $action);
                }
            } catch (IOException $e) {
                error_log($e->getMessage());
            }
        }
    }

    /**
     * Execute the given SQL statement.
     *
     * @param $sql The SQL statement to execute
     */
    private function executeSql($sql): void
    {
        try {
            $this->di['dbal']->executeStatement($sql);
        } catch (\Exception $e) {
            // Log the error and then throw a user-friendly exception to prevent further patches from being applied.
            error_log($e->getMessage());

            throw new Exception('There was an error while applying database patches. Please check the error log for information on the error, correct it, and then perform the backup patching method to complete the update.');
        }
    }

    private function migrateEncryptedColumn(string $table, string $idColumn, string $valueColumn, string $where, array $params = []): void
    {
        $rows = $this->di['dbal']
            ->executeQuery("SELECT {$idColumn} AS id, {$valueColumn} AS encrypted_value FROM {$table} WHERE {$where}", $params)
            ->fetchAllAssociative();

        /** @var \Box_Crypt $crypt */
        $crypt = $this->di['crypt'];
        $salt = Config::getProperty('info.salt');

        $hasUpdatedAt = $this->di['dbal']->createSchemaManager()->introspectTable($table)->hasColumn('updated_at');

        foreach ($rows as $row) {
            $encryptedValue = $row['encrypted_value'] ?? null;
            if (!is_string($encryptedValue) || $encryptedValue === '' || str_starts_with($encryptedValue, \Box_Crypt::CURRENT_FORMAT_PREFIX)) {
                continue;
            }

            $decryptedValue = $crypt->decrypt($encryptedValue, $salt);
            if ($decryptedValue === false) {
                continue;
            }

            $updateData = [$valueColumn => $crypt->encrypt($decryptedValue, $salt)];
            if ($hasUpdatedAt) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
            }

            $this->di['dbal']->update($table, $updateData, [
                $idColumn => $row['id'],
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
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('value')
            ->from('setting')
            ->where('param = :param')
            ->setParameter('param', 'last_patch');

        $result = $query->executeQuery();
        $value = $result->fetchOne();

        return intval($value) ?: null;
    }

    /**
     * Set the current patch level of FOSSBilling.
     *
     * @param int $patchLevel The last executed patch level
     */
    private function setPatchLevel(int $patchLevel): void
    {
        $query = $this->di['dbal']->createQueryBuilder();

        if (is_null($this->getPatchLevel())) {
            $query
                ->insert('setting')
                ->values([
                    'param' => ':param',
                    'value' => ':value',
                    'public' => '1',
                    'created_at' => ':created_at',
                    'updated_at' => ':updated_at',
                ])
                ->setParameter('param', 'last_patch')
                ->setParameter('value', $patchLevel)
                ->setParameter('created_at', date('Y-m-d H:i:s'))
                ->setParameter('updated_at', date('Y-m-d H:i:s'));
        } else {
            $query
                ->update('setting')
                ->set('value', ':value')
                ->set('updated_at', ':updated_at')
                ->where('param = :param')
                ->setParameter('param', 'last_patch')
                ->setParameter('value', $patchLevel)
                ->setParameter('updated_at', date('Y-m-d H:i:s'));
        }

        $query->executeStatement();
    }

    /**
     * Get patches to be applied.
     *
     * @param int|null $patchLevel the current patch level of FOSSBilling
     *
     * @return array array containing the patches to be executed, in order
     */
    private function getPatches($patchLevel = 0): array
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
        ];
        ksort($patches, SORT_NATURAL);

        $patchesToApply = array_filter($patches, fn ($key): bool => $key > $patchLevel, ARRAY_FILTER_USE_KEY);

        return array_map(fn ($method): array => [$this, $method], $patchesToApply);
    }

    private function patch25(): void
    {
        // Migrate email templates to be compatible with Twig 3.x.
        $this->di['dbal']->createQueryBuilder()
            ->update('email_template')
            ->set('content', 'REPLACE(content, \'{% filter markdown %}\', \'{% apply markdown %}\')')
            ->executeStatement();

        $this->di['dbal']->createQueryBuilder()
            ->update('email_template')
            ->set('content', 'REPLACE(content, \'{% endfilter %}\', \'{% endapply %}\')')
            ->executeStatement();
    }

    private function patch26(): void
    {
        // Migration steps from BoxBilling to FOSSBilling - added favicon settings.
        $this->di['dbal']->createQueryBuilder()
            ->insert('setting')
            ->values([
                'param' => ':param',
                'value' => ':value',
                'public' => '0',
                'category' => ':category',
                'hash' => ':hash',
                'created_at' => ':created_at',
                'updated_at' => ':updated_at',
            ])
            ->setParameter('param', 'company_favicon')
            ->setParameter('value', 'themes/huraga/assets/favicon.ico')
            ->setParameter('category', null)
            ->setParameter('hash', null)
            ->setParameter('created_at', '2023-01-08 12:00:00')
            ->setParameter('updated_at', '2023-01-08 12:00:00')
            ->executeStatement();
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
        $this->di['dbal']->createQueryBuilder()
            ->update('email_template')
            ->set('action_code', 'REPLACE(action_code, \'.html\', \'\')')
            ->executeStatement();
    }

    private function patch29(): void
    {
        // Patch to update email templates to use format_date/format_datetime filters
        // instead of removed bb_date/bb_datetime filters.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/948
        $this->di['dbal']->createQueryBuilder()
            ->update('email_template')
            ->set('content', 'REPLACE(content, \'bb_date\', \'format_date\')')
            ->executeStatement();

        $this->di['dbal']->createQueryBuilder()
            ->update('email_template')
            ->set('content', 'REPLACE(content, \'bb_datetime\', \'format_datetime\')')
            ->executeStatement();
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

        // An error here can pretty safely be ignore.
        try {
            // If the Kb extension is currently active, set enabled in Support settings.
            $ext_service = $this->di['mod_service']('extension');
            if ($ext_service->isExtensionActive('mod', 'kb')) {
                $support_ext_config = $ext_service->getConfig('mod_support');
                $support_ext_config['kb_enable'] = 'on';
                $ext_service->setConfig($support_ext_config);
            }

            // If the Kb extension exists, uninstall it.
            $kb_ext = $ext_service->findExtension('mod', 'kb');
            if ($kb_ext instanceof \Model_Extension) {
                $ext_service->uninstall($kb_ext);
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
        // Patch to complete remove the outdated queue module.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1777

        try {
            $ext_service = $this->di['mod_service']('extension');
            // If the queue extension exists, uninstall it.
            $queue_ext = $ext_service->findExtension('mod', 'queue');
            if ($queue_ext instanceof \Model_Extension) {
                $ext_service->uninstall($queue_ext);
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

        $query = $this->di['dbal']->createQueryBuilder()
            ->select('param', 'value')
            ->from('setting')
            ->executeQuery();

        $pairs = $query->fetchAllKeyValue();

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
        $q = 'ALTER TABLE `activity_admin_history` DROP COLUMN `updated_at`;';
        $this->executeSql($q);

        $q = 'ALTER TABLE `activity_client_email` DROP COLUMN `updated_at`;';
        $this->executeSql($q);

        $q = 'ALTER TABLE `activity_client_history` DROP COLUMN `updated_at`;';
        $this->executeSql($q);

        $q = 'ALTER TABLE `activity_system` DROP COLUMN `updated_at`;';
        $this->executeSql($q);
    }

    private function patch46(): void
    {
        // Change gender column to ENUM type
        $q1 = 'ALTER TABLE `client`
            MODIFY COLUMN `gender` ENUM("male", "female", "nonbinary", "other") DEFAULT NULL;';

        // Change document_type column to ENUM type
        $q2 = 'ALTER TABLE `client`
            MODIFY COLUMN `document_type` ENUM("passport") DEFAULT NULL;';

        $this->executeSql($q1);
        $this->executeSql($q2);
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
        $filesystem = new Filesystem();
        $dbal = $this->di['dbal'];

        $oldUploadsPath = Path::join(PATH_ROOT, 'uploads');
        $newUploadsPath = Path::join(PATH_ROOT, 'data', 'uploads');

        if ($filesystem->exists($oldUploadsPath) && $filesystem->exists($newUploadsPath)) {
            foreach (glob($oldUploadsPath . '/*') as $oldFile) {
                if (is_file($oldFile)) {
                    $filename = basename($oldFile);
                    $newFilePath = Path::join($newUploadsPath, $filename);
                    if (!$filesystem->exists($newFilePath)) {
                        $filesystem->rename($oldFile, $newFilePath);
                    }
                }
            }
        }

        $products = $dbal->executeQuery("SELECT p.id, p.config FROM product p WHERE p.type = 'downloadable'")->fetchAllAssociative();

        foreach ($products as $product) {
            $productConfig = json_decode((string) $product['config'], true) ?: [];

            if (isset($productConfig['filename']) && !empty($productConfig['filename'])) {
                continue;
            }

            $foundFilename = null;

            $orders = $dbal->executeQuery('SELECT co.id, co.config, co.service_id FROM client_order co WHERE co.product_id = :product_id', ['product_id' => $product['id']])->fetchAllAssociative();

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
                $services = $dbal->executeQuery('SELECT sd.id, sd.filename FROM service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id WHERE co.product_id = :product_id AND sd.filename IS NOT NULL AND sd.filename != ""', ['product_id' => $product['id']])->fetchAllAssociative();

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
                $dbal->executeStatement('UPDATE product SET config = :config, updated_at = :updated_at WHERE id = :id', [
                    'config' => json_encode($productConfig),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $product['id'],
                ]);

                $dbal->executeStatement('UPDATE service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id SET sd.filename = :filename WHERE co.product_id = :product_id', ['filename' => $foundFilename, 'product_id' => $product['id']]);

                $ordersToUpdate = $dbal->executeQuery('SELECT id, config FROM client_order WHERE product_id = :product_id AND config LIKE "%filename%"', ['product_id' => $product['id']])->fetchAllAssociative();

                foreach ($ordersToUpdate as $orderToUpdate) {
                    $orderConfig = json_decode($orderToUpdate['config'] ?? '', true);
                    if (is_array($orderConfig) && isset($orderConfig['filename'])) {
                        $orderConfig['filename'] = $foundFilename;
                        $dbal->executeStatement('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                            'config' => json_encode($orderConfig),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'id' => $orderToUpdate['id'],
                        ]);
                    }
                }
            }
        }

        $orphans = $dbal->executeQuery('SELECT sd.id, co.config as order_config FROM service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id WHERE sd.filename IS NULL OR sd.filename = ""')->fetchAllAssociative();

        foreach ($orphans as $orphan) {
            $orderConfig = json_decode($orphan['order_config'] ?? '', true);
            if (isset($orderConfig['filename']) && !empty($orderConfig['filename'])) {
                $filePath = Path::join(PATH_UPLOADS, md5((string) $orderConfig['filename']));
                if ($filesystem->exists($filePath)) {
                    $dbal->executeStatement('UPDATE service_downloadable SET filename = :filename WHERE id = :id', ['filename' => $orderConfig['filename'], 'id' => $orphan['id']]);
                }
            }
        }
    }

    private function patch49(): void
    {
        $q = "UPDATE setting SET value = 'themes/huraga/assets/build/img/logo.svg' WHERE param = 'company_logo' AND value = 'themes/huraga/assets/img/logo.svg';";
        $this->executeSql($q);

        $q = "UPDATE setting SET value = 'themes/huraga/assets/build/img/logo_white.svg' WHERE param = 'company_logo_dark' AND value = 'themes/huraga/assets/img/logo_white.svg';";
        $this->executeSql($q);

        $q = "UPDATE setting SET value = 'themes/huraga/assets/build/favicon.ico' WHERE param = 'company_favicon' AND value = 'themes/huraga/assets/favicon.ico';";
        $this->executeSql($q);
    }

    private function patch50(): void
    {
        $this->migrateEncryptedColumn('email_template', 'id', 'vars', "vars IS NOT NULL AND vars != ''");
        $this->migrateEncryptedColumn('extension_meta', 'id', 'meta_value', "meta_key = :meta_key AND meta_value IS NOT NULL AND meta_value != ''", [
            'meta_key' => 'config',
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
        $schemaManager = $this->di['dbal']->createSchemaManager();
        $columns = array_map(static fn ($column) => $column->getName(), $schemaManager->listTableColumns('email_template'));

        if (!in_array('is_custom', $columns, true)) {
            $this->executeSql("ALTER TABLE `email_template` ADD COLUMN `is_custom` TINYINT(1) DEFAULT '0' AFTER `enabled`;");
        }

        if (!in_array('is_overridden', $columns, true)) {
            $this->executeSql("ALTER TABLE `email_template` ADD COLUMN `is_overridden` TINYINT(1) DEFAULT '0' COMMENT 'Whether subject/content have been customized from file defaults' AFTER `is_custom`;");
        }

        $templates = $this->di['dbal']->executeQuery('SELECT id, action_code, subject, content FROM email_template')->fetchAllAssociative();
        foreach ($templates as $template) {
            $default = $this->getDefaultEmailTemplateData((string) ($template['action_code'] ?? ''));
            if ($default === null) {
                $this->di['dbal']->executeStatement('UPDATE email_template SET is_custom = :is_custom WHERE id = :id', [
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

            $this->di['dbal']->executeStatement('UPDATE email_template SET is_custom = :is_custom, is_overridden = :is_overridden, subject = :subject, content = :content WHERE id = :id', [
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
        $dbal = $this->di['dbal'];
        $tools = $this->di['tools'];
        $now = date('Y-m-d H:i:s');

        $dbal->beginTransaction();

        try {
            $batchSize = 1000;
            $adminUpdateStmt = $dbal->prepare('UPDATE admin SET api_token = :api_token, updated_at = :updated_at WHERE id = :id');
            $clientUpdateStmt = $dbal->prepare('UPDATE client SET api_token = :api_token, updated_at = :updated_at WHERE id = :id');

            $lastAdminId = 0;
            do {
                $adminIds = $dbal->createQueryBuilder()
                    ->select('id')
                    ->from('admin')
                    ->where('id > :lastId')
                    ->orderBy('id', 'ASC')
                    ->setMaxResults($batchSize)
                    ->setParameter('lastId', $lastAdminId)
                    ->executeQuery()
                    ->fetchFirstColumn();

                foreach ($adminIds as $adminId) {
                    $adminUpdateStmt->bindValue('api_token', $tools->generatePassword(32));
                    $adminUpdateStmt->bindValue('updated_at', $now);
                    $adminUpdateStmt->bindValue('id', (int) $adminId, ParameterType::INTEGER);
                    $adminUpdateStmt->executeStatement();
                }

                if (!empty($adminIds)) {
                    $lastAdminId = (int) end($adminIds);
                }
            } while (!empty($adminIds));

            $lastClientId = 0;
            do {
                $clientIds = $dbal->createQueryBuilder()
                    ->select('id')
                    ->from('client')
                    ->where('id > :lastId')
                    ->orderBy('id', 'ASC')
                    ->setMaxResults($batchSize)
                    ->setParameter('lastId', $lastClientId)
                    ->executeQuery()
                    ->fetchFirstColumn();

                foreach ($clientIds as $clientId) {
                    $clientUpdateStmt->bindValue('api_token', $tools->generatePassword(32));
                    $clientUpdateStmt->bindValue('updated_at', $now);
                    $clientUpdateStmt->bindValue('id', (int) $clientId, ParameterType::INTEGER);
                    $clientUpdateStmt->executeStatement();
                }

                if (!empty($clientIds)) {
                    $lastClientId = (int) end($clientIds);
                }
            } while (!empty($clientIds));

            $dbal->createQueryBuilder()
                ->delete('session')
                ->executeStatement();

            $dbal->commit();
        } catch (\Throwable $e) {
            $dbal->rollBack();

            throw $e;
        }
    }

    private function patch54(): void
    {
        try {
            $finder = new Finder();
            $finder->directories()->in(PATH_MODS)->depth('== 1')->name('/^html_(admin|client|email)$/');

            foreach ($finder as $dir) {
                $modulePath = Path::getDirectory($dir->getPathname());
                $area = substr($dir->getFilename(), 5);
                $replacementPath = Path::join($modulePath, 'templates', $area);

                // Only remove legacy directories for modules that have already been migrated
                // to the new templates/<area> layout. This avoids deleting user-installed
                // modules that still depend on the legacy structure.
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
            throw new Exception('The modules directory does not exist. Cannot apply patch 54.');
        }

        $this->executeFileActions([
            Path::join(PATH_LIBRARY, 'Box', 'TwigLoader.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'TwigExtensions.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'TwigExtensions', 'DebugBar.php') => 'unlink',
        ]);
    }

    private function patch55(): void
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
            'mod_staff_pticket_close' => ['649f36bc186928c2581f27e017dcad39ee38a01100bb38e0458e02cab1d06cb8', '49b9aced9a16bb81ce961a097c945a9c6dd828b3d8aab3b3c3557bb5d12ec85e'],
            'mod_staff_pticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'd30deb555b30c5a85f25b23bb4c83a76e49d8a8379ad09d4aff9968888e637ce'],
            'mod_staff_pticket_reply' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '22bbf90befc02e3da6211c7036362fb296a38eb4be686d18752552b3cc0ae63d'],
            'mod_staff_ticket_close' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'd54e0d97b88218404c731929eb2a39857ee422070e857802374626b72bf84b2f'],
            'mod_staff_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'dbe1dee98606a36285a79d019e6180e62ee04cc7e3c3f16d34045d6c70aaf78e'],
            'mod_staff_ticket_reply' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '87238cbde59fd9faa31c8e0d6039924ad6068f1ca3858ad91b06cc4633243153'],
            'mod_support_helpdesk_ticket_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '709e2ff17c01e1056ef4824e507e4474a6eeb1e3f35d5acd1c646074d98cc5d6'],
            'mod_support_pticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '9735ea9dfe213f0880f95fabf82cc0530bac78feeeab9b6ec21fee501c3e9e3f'],
            'mod_support_pticket_staff_close' => ['649f36bc186928c2581f27e017dcad39ee38a01100bb38e0458e02cab1d06cb8', '40a10a0a559dd5e3f69d8464df979db6bc5109696c4a1df6f60ea6802ca3376f'],
            'mod_support_pticket_staff_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '7dcba8e4fae63dd9ef4815a5db8cfb0cf6a510d48b947ec64f9e8163b8078e03'],
            'mod_support_pticket_staff_reply' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '4afbeb7038d64bd1a53277902139019fa219554cdb32fb1ec74b07e85b40e83d'],
            'mod_support_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '78fe28d46cba9f344b9a2045114b1a7d723dc8a62602690413ecac30287bcae2'],
            'mod_support_ticket_staff_close' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '965d0369564e2edc832db28564e414b960f9559213750d7f1f1baf0b1cceb2a7'],
            'mod_support_ticket_staff_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '98ff5f36b64fb0f874048c7dd44310e98ecf2b08f49e8dab701d9d4bb5e81ba5'],
            'mod_support_ticket_staff_reply' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '74aea13a2cbe71aee7b8071480fb4b6767d5690589070d1a06721002618ed29f'],
        ];

        $templates = $this->di['dbal']->executeQuery(
            'SELECT id, action_code, subject, content FROM email_template WHERE is_overridden = 1 AND is_custom = 0'
        )->fetchAllAssociative();

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

            $this->di['dbal']->executeStatement(
                'UPDATE email_template SET is_overridden = 0, subject = :subject, content = :content WHERE id = :id',
                [
                    'subject' => $default['subject'],
                    'content' => $default['content'],
                    'id' => $template['id'],
                ]
            );
        }
    }

    private function patch56(): void
    {
        $gateways = $this->di['dbal']->executeQuery(
            "SELECT id, config FROM pay_gateway WHERE gateway = 'Custom'"
        )->fetchAllAssociative();

        foreach ($gateways as $gateway) {
            $config = json_decode($gateway['config'] ?? '', true);
            if (!is_array($config)) {
                continue;
            }

            $fields = ['single', 'recurrent'];
            $needsSave = false;
            foreach ($fields as $field) {
                if (isset($config[$field]) && is_string($config[$field]) && preg_match('/\b(function|include|import|extends|range|max|min|dump|system|guest\.|admin\.|client\.)\b/i', $config[$field])) {
                    $needsSave = true;

                    break;
                }
            }

            if ($needsSave) {
                $this->di['logger']->setChannel('update')->warning('Custom payment adapter template may contain unsupported Twig syntax. Please review and update gateway ID {id}.', [
                    'id' => $gateway['id'],
                ]);
            }
        }
    }

    /**
     * If we end up needing a newly introduced package during the update process, composer's autoloader won't have it until the next load.
     * As a workaround, we can register AntLoader and point it at the Vendor folder which will then act as fallback to find the needed classes.
     * This isn't particularly fast though as it'll scan the entire vendor, so only use it if we know a needed class is missing.
     */
    private function registerFallbackAutoloader(): void
    {
        $loader = new \AntCMS\AntLoader([
            'mode' => 'filesystem',
            'path' => Path::join(PATH_CACHE, 'fallbackClassMap.php'),
        ]);
        $loader->addNamespace('', PATH_VENDOR);
        $loader->checkClassMap();
        $loader->register(true);
    }

    private function getDefaultEmailTemplateData(string $code): ?array
    {
        $path = $this->getDefaultEmailTemplatePath($code);
        if ($path === null) {
            return null;
        }

        $template = $this->filesystem->readFile($path);

        $subject = ucwords(str_replace('_', ' ', $code));
        preg_match('#{%.?block subject.?%}((.*?)+){%.?endblock.?%}#', $template, $subjectMatches);
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
}

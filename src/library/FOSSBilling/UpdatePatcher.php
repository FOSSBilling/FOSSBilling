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

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
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
            25 => function (): void {
                // Migrate email templates to be compatible with Twig 3.x.
                $this->di['dbal']->createQueryBuilder()
                    ->update('email_template')
                    ->set('content', 'REPLACE(content, \'{% filter markdown %}\', \'{% apply markdown %}\')')
                    ->executeStatement();

                $this->di['dbal']->createQueryBuilder()
                    ->update('email_template')
                    ->set('content', 'REPLACE(content, \'{% endfilter %}\', \'{% endapply %}\')')
                    ->executeStatement();
            },
            26 => function (): void {
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
            },
            27 => function (): void {
                // Migration steps to create table to allow admin users to do password reset.
                $q = 'CREATE TABLE `admin_password_reset` ( `id` bigint(20) NOT NULL AUTO_INCREMENT, `admin_id` bigint(20) DEFAULT NULL, `hash` varchar(100) DEFAULT NULL, `ip` varchar(45) DEFAULT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `admin_id_idx` (`admin_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
                $this->executeSql($q);
            },
            28 => function (): void {
                // Patch to remove .html from email templates action code.
                // @see https://github.com/FOSSBilling/FOSSBilling/issues/863
                $this->di['dbal']->createQueryBuilder()
                    ->update('email_template')
                    ->set('action_code', 'REPLACE(action_code, \'.html\', \'\')')
                    ->executeStatement();
            },
            29 => function (): void {
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
            },
            30 => function (): void {
                // Patch to remove the old guzzlehttp package, as we no longer
                // use it. Also serves as an example for how to perform file action.
                $fileActions = [
                    Path::join(PATH_VENDOR, 'guzzlehttp') => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            31 => function (): void {
                // Patch to remove the old htaccess.txt file, and any old config.php backup.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1075
                $fileActions = [
                    Path::join(PATH_ROOT, 'htaccess.txt') => 'unlink',
                    Path::join(PATH_ROOT, 'config.php.old') => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            32 => function (): void {
                // Patch to remove the old phpmailer package, some leftover
                // admin_default files, and old Box_ classes we've removed or replaced.
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
            },
            33 => function (): void {
                // Patch to remove the old FileCache class that was replaced with Symfony's Cache component.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1184
                $fileActions = [
                    Path::join(PATH_LIBRARY, 'FileCache.php') => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            34 => function (): void {
                // Adds the new "fingerprint" to the session table, to allow us to fingerprint devices and help prevent against attacks such as session hijacking.
                $q = 'ALTER TABLE session ADD fingerprint TEXT;';
                $this->executeSql($q);
            },
            35 => function (): void {
                // Adds the new "created_at" to the session table, to ensure sessions are destroyed after they reach their maximum age.
                $q = 'ALTER TABLE session ADD created_at int(11);';
                $this->executeSql($q);
            },
            36 => function (): void {
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
            },
            37 => function (): void {
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
            },
            38 => function (): void {
                // We need to remove the old ISPConfig3 and Virtualmin server managers from disk or else the leftover files could prevent the "hosting plans and servers" page from being loaded.
                $fileActions = [
                    Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Ispconfig3.php') => 'unlink',
                    Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Virtualmin.php') => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            39 => function (): void {
                // The Serbian language was incorrectly placed into a folder named `srp` by Crowdin which is now corrected for via the locale repo and as such we need to delete the old directory.
                // @see https://github.com/FOSSBilling/locale/issues/212
                $fileActions = [
                    Path::join(PATH_LANGS, 'srp') => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            40 => function (): void {
                // Added `passwordLength` field to server managers
                $dbal = $this->di['dbal'];
                $schemaManager = $dbal->createSchemaManager();

                // Support both old and new table names during transition
                if ($schemaManager->tablesExist(['ext_product_hosting_server'])) {
                    $q = 'ALTER TABLE ext_product_hosting_server ADD COLUMN `password_length` TINYINT DEFAULT NULL;';
                } elseif ($schemaManager->tablesExist(['service_hosting_server'])) {
                    $q = 'ALTER TABLE service_hosting_server ADD COLUMN `password_length` TINYINT DEFAULT NULL;';
                } else {
                    return; // Table doesn't exist yet
                }
                $this->executeSql($q);
            },
            41 => function (): void {
                // Remove the  `manifest` column from the extensions table since it's no longer used
                $q = 'ALTER TABLE extension DROP COLUMN manifest;';
                $this->executeSql($q);
            },
            42 => function (): void {
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
            },
            43 => function (): void {
                $fileActions = [
                    Path::join(PATH_LIBRARY, 'GeoLite2-Country.mmdb') => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            44 => function (): void {
                // Add ipn_hash column to transaction table and index it for fast duplicate detection.
                $q = 'ALTER TABLE `transaction`
                        ADD COLUMN `ipn_hash` VARCHAR(64) DEFAULT NULL,
                        ADD INDEX `transaction_ipn_hash_idx` (`gateway_id`, `ipn_hash`(64));';
                $this->executeSql($q);
            },
            45 => function (): void {
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
            },
            46 => function (): void {
                // Change gender column to ENUM type
                $q1 = 'ALTER TABLE `client`
                    MODIFY COLUMN `gender` ENUM("male", "female", "nonbinary", "other") DEFAULT NULL;';

                // Change document_type column to ENUM type
                $q2 = 'ALTER TABLE `client`
                    MODIFY COLUMN `document_type` ENUM("passport") DEFAULT NULL;';

                $this->executeSql($q1);
                $this->executeSql($q2);
            },
            47 => function (): void {
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
            },
            48 => function (): void {
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

                $products = $dbal->executeQuery("SELECT p.id, p.config FROM product p WHERE p.type IN ('download', 'downloadable')")->fetchAllAssociative();
                $schemaManager = $dbal->createSchemaManager();
                $downloadTable = null;
                if ($schemaManager->tablesExist(['ext_product_download'])) {
                    $downloadTable = 'ext_product_download';
                } elseif ($schemaManager->tablesExist(['service_download'])) {
                    $downloadTable = 'service_download';
                } elseif ($schemaManager->tablesExist(['service_downloadable'])) {
                    $downloadTable = 'service_downloadable';
                }

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

                    if ($foundFilename === null && $downloadTable !== null) {
                        $services = $dbal->executeQuery("SELECT sd.id, sd.filename FROM {$downloadTable} sd INNER JOIN client_order co ON sd.id = co.service_id WHERE co.product_id = :product_id AND sd.filename IS NOT NULL AND sd.filename != \"\"", ['product_id' => $product['id']])->fetchAllAssociative();

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

                        if ($downloadTable !== null) {
                            $dbal->executeStatement("UPDATE {$downloadTable} sd INNER JOIN client_order co ON sd.id = co.service_id SET sd.filename = :filename WHERE co.product_id = :product_id", ['filename' => $foundFilename, 'product_id' => $product['id']]);
                        }

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

                $orphans = [];
                if ($downloadTable !== null) {
                    $orphans = $dbal->executeQuery("SELECT sd.id, co.config as order_config FROM {$downloadTable} sd INNER JOIN client_order co ON sd.id = co.service_id WHERE sd.filename IS NULL OR sd.filename = \"\"")->fetchAllAssociative();
                }

                foreach ($orphans as $orphan) {
                    $orderConfig = json_decode($orphan['order_config'] ?? '', true);
                    if (isset($orderConfig['filename']) && !empty($orderConfig['filename'])) {
                        $filePath = Path::join(PATH_UPLOADS, md5((string) $orderConfig['filename']));
                        if ($filesystem->exists($filePath)) {
                            if ($downloadTable !== null) {
                                $dbal->executeStatement("UPDATE {$downloadTable} SET filename = :filename WHERE id = :id", ['filename' => $orderConfig['filename'], 'id' => $orphan['id']]);
                            }
                        }
                    }
                }
            },
            49 => function (): void {
                // Patch to update logo and favicon paths from assets/ to assets/build/ for huraga theme
                // This is needed because the esbuild migration moved assets to a build directory
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/XXXX
                $q = "UPDATE setting SET value = 'themes/huraga/assets/build/img/logo.svg' WHERE param = 'company_logo' AND value = 'themes/huraga/assets/img/logo.svg';";
                $this->executeSql($q);

                $q = "UPDATE setting SET value = 'themes/huraga/assets/build/img/logo_white.svg' WHERE param = 'company_logo_dark' AND value = 'themes/huraga/assets/img/logo_white.svg';";
                $this->executeSql($q);

                $q = "UPDATE setting SET value = 'themes/huraga/assets/build/favicon.ico' WHERE param = 'company_favicon' AND value = 'themes/huraga/assets/favicon.ico';";
                $this->executeSql($q);
            },
            50 => function (): void {
                // Product type registry migration.
                $q = 'ALTER TABLE `product` ADD COLUMN `product_type` varchar(255) DEFAULT NULL AFTER `type`;';
                $this->executeSql($q);

                $q = 'ALTER TABLE `product` ADD INDEX `product_product_type_idx` (`product_type`);';
                $this->executeSql($q);

                $q = 'ALTER TABLE `client_order` ADD COLUMN `product_type` varchar(100) DEFAULT NULL AFTER `service_type`;';
                $this->executeSql($q);

                $q = 'ALTER TABLE `client_order` ADD INDEX `client_order_product_type_idx` (`product_type`);';
                $this->executeSql($q);

                $q = 'UPDATE `product` SET `product_type` = `type` WHERE `product_type` IS NULL;';
                $this->executeSql($q);

                $q = 'UPDATE `client_order` SET `product_type` = `service_type` WHERE `product_type` IS NULL;';
                $this->executeSql($q);

                $dbal = $this->di['dbal'];
                $schemaManager = $dbal->createSchemaManager();

                if (
                    !$schemaManager->tablesExist(['ext_product_apikey'])
                    && !$schemaManager->tablesExist(['service_apikey'])
                ) {
                    $q = '
                    CREATE TABLE IF NOT EXISTS `ext_product_apikey` (
                        `id` bigint(20) NOT NULL AUTO_INCREMENT,
                        `client_id` bigint(20) NOT NULL,
                        `api_key` varchar(255) DEFAULT NULL,
                        `config` text NOT NULL,
                        `created_at` datetime DEFAULT NULL,
                        `updated_at` datetime DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `client_id_idx` (`client_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
                    $this->executeSql($q);
                }

                $q = "DELETE FROM extension WHERE type = 'mod' AND name IN ('mod_serviceapikey', 'mod_servicecustom', 'mod_servicelicense', 'mod_servicehosting', 'mod_servicedomain', 'mod_servicedownloadable');";
                $this->executeSql($q);

                $q = "DELETE FROM extension_meta WHERE extension IN ('mod_serviceapikey', 'mod_servicecustom', 'mod_servicelicense', 'mod_servicehosting', 'mod_servicedomain', 'mod_servicedownloadable');";
                $this->executeSql($q);

                $this->executeSql("UPDATE product SET type = 'download' WHERE type = 'downloadable'");
                $this->executeSql("UPDATE product SET product_type = 'download' WHERE product_type = 'downloadable'");
                $this->executeSql("UPDATE client_order SET service_type = 'download' WHERE service_type = 'downloadable'");
                $this->executeSql("UPDATE client_order SET product_type = 'download' WHERE product_type = 'downloadable'");
                $this->executeSql(
                    "UPDATE email_template SET action_code = CONCAT('ext_product_', SUBSTRING(action_code, LENGTH('mod_service') + 1))
                    WHERE action_code LIKE 'mod_service%_%'"
                );
                if (!$schemaManager->tablesExist(['ext_product_download'])) {
                    if ($schemaManager->tablesExist(['service_download'])) {
                        $dbal->executeStatement('RENAME TABLE service_download TO ext_product_download');
                    } elseif ($schemaManager->tablesExist(['service_downloadable'])) {
                        $dbal->executeStatement('RENAME TABLE service_downloadable TO ext_product_download');
                    }
                }

                $tableMappings = [
                    'service_apikey' => 'ext_product_apikey',
                    'service_custom' => 'ext_product_custom',
                    'service_domain' => 'ext_product_domain',
                    'service_download' => 'ext_product_download',
                    'service_hosting' => 'ext_product_hosting',
                    'service_hosting_hp' => 'ext_product_hosting_plan',
                    'service_hosting_server' => 'ext_product_hosting_server',
                    'service_license' => 'ext_product_license',
                ];

                foreach ($tableMappings as $oldName => $newName) {
                    if (
                        $schemaManager->tablesExist([$oldName])
                        && !$schemaManager->tablesExist([$newName])
                    ) {
                        $schemaManager->renameTable($oldName, $newName);
                    }
                }

                $fileActions = [
                    Path::join(PATH_ROOT, 'extensions', 'products', 'License', 'Plugin') => Path::join(PATH_ROOT, 'extensions', 'products', 'License', 'plugins'),
                ];
                $this->executeFileActions($fileActions);

                if ($schemaManager->tablesExist(['ext_product_hosting'])) {
                    $table = $schemaManager->listTableDetails('ext_product_hosting');

                    if ($table->hasColumn('service_hosting_server_id')) {
                        $dbal->executeStatement(
                            'ALTER TABLE ext_product_hosting CHANGE service_hosting_server_id ext_product_hosting_server_id BIGINT(20) DEFAULT NULL'
                        );
                    }

                    if ($table->hasColumn('service_hosting_hp_id')) {
                        $dbal->executeStatement(
                            'ALTER TABLE ext_product_hosting CHANGE service_hosting_hp_id ext_product_hosting_plan_id BIGINT(20) DEFAULT NULL'
                        );
                    }

                    if ($table->hasIndex('service_hosting_server_id_idx')) {
                        $dbal->executeStatement('ALTER TABLE ext_product_hosting DROP INDEX service_hosting_server_id_idx');
                    }

                    if ($table->hasIndex('service_hosting_hp_id_idx')) {
                        $dbal->executeStatement('ALTER TABLE ext_product_hosting DROP INDEX service_hosting_hp_id_idx');
                    }

                    if (!$table->hasIndex('ext_product_hosting_server_id_idx')) {
                        $dbal->executeStatement(
                            'ALTER TABLE ext_product_hosting ADD INDEX ext_product_hosting_server_id_idx (ext_product_hosting_server_id)'
                        );
                    }

                    if (!$table->hasIndex('ext_product_hosting_plan_id_idx')) {
                        $dbal->executeStatement(
                            'ALTER TABLE ext_product_hosting ADD INDEX ext_product_hosting_plan_id_idx (ext_product_hosting_plan_id)'
                        );
                    }
                }

                $root = Path::join(PATH_ROOT, 'extensions', 'products');
                if (!is_dir($root)) {
                    return;
                }

                $codes = [];
                $iterator = new \DirectoryIterator($root);
                foreach ($iterator as $entry) {
                    if (!$entry->isDir() || $entry->isDot()) {
                        continue;
                    }

                    $manifestPath = Path::join($entry->getPathname(), 'manifest.json');
                    if (!is_file($manifestPath)) {
                        continue;
                    }

                    $contents = file_get_contents($manifestPath);
                    if ($contents === false) {
                        continue;
                    }

                    try {
                        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        continue;
                    }

                    $code = $data['code'] ?? $entry->getFilename();
                    if (!is_string($code) || trim($code) === '') {
                        continue;
                    }

                    $codes[] = strtolower(trim($code));
                }

                $codes = array_values(array_unique($codes));
                if (empty($codes)) {
                    return;
                }

                $rows = $dbal->createQueryBuilder()
                    ->select('id', 'permissions')
                    ->from('admin')
                    ->executeQuery()
                    ->fetchAllAssociative();

                foreach ($rows as $row) {
                    $permissions = json_decode($row['permissions'] ?? '', true);
                    if (!is_array($permissions)) {
                        $permissions = [];
                    }

                    $changed = false;

                    foreach ($codes as $code) {
                        $legacyKey = 'service' . $code;
                        if (!array_key_exists($legacyKey, $permissions)) {
                            continue;
                        }

                        $legacyPerms = $permissions[$legacyKey];
                        $newKey = 'product_' . $code;
                        $newPerms = $permissions[$newKey] ?? [];
                        if (!is_array($newPerms)) {
                            $newPerms = [];
                        }

                        if (is_array($legacyPerms)) {
                            foreach ($legacyPerms as $permKey => $value) {
                                if ($permKey === 'access') {
                                    $newPerms[$permKey] = (bool) ($newPerms[$permKey] ?? false) || (bool) $value;
                                } elseif (!array_key_exists($permKey, $newPerms)) {
                                    $newPerms[$permKey] = $value;
                                }
                            }
                        } else {
                            $newPerms['access'] = (bool) ($newPerms['access'] ?? false) || (bool) $legacyPerms;
                        }

                        $permissions[$newKey] = $newPerms;
                        unset($permissions[$legacyKey]);
                        $changed = true;
                    }

                    if ($changed) {
                        $dbal->executeStatement(
                            'UPDATE admin SET permissions = :permissions WHERE id = :id',
                            [
                                'permissions' => json_encode($permissions),
                                'id' => $row['id'],
                            ]
                        );
                    }
                }
            },
        ];
        ksort($patches, SORT_NATURAL);

        return array_filter($patches, fn ($key): bool => $key > $patchLevel, ARRAY_FILTER_USE_KEY);
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
}

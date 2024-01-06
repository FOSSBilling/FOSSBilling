<?php

declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class UpdatePatcher implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Apply configuration file patches.
     */
    public function applyConfigPatches(): void
    {
        $filesystem = new Filesystem();

        $currentConfig = include PATH_CONFIG;

        if (!is_array($currentConfig)) {
            throw new Exception('Unable to load existing configuration');
        }

        // Create backup of current configuration.
        try {
            $filesystem->copy(PATH_CONFIG, substr(PATH_CONFIG, 0, -4) . '.old.php');
        } catch (FileNotFoundException|IOException) {
            throw new Exception('Unable to create backup of configuration file');
        }

        $newConfig = $currentConfig;
        $newConfig['security']['mode'] ??= 'strict';
        $newConfig['security']['force_https'] ??= true;
        $newConfig['security']['session_lifespan'] ??= $newConfig['security']['cookie_lifespan'] ?? 7200;
        $newConfig['update_branch'] ??= 'release';
        $newConfig['log_stacktrace'] ??= true;
        $newConfig['stacktrace_length'] ??= 25;
        $newConfig['maintenance_mode']['enabled'] ??= false;
        $newConfig['maintenance_mode']['allowed_urls'] ??= [];
        $newConfig['maintenance_mode']['allowed_ips'] ??= [];
        $newConfig['disable_auto_cron'] ??= false;
        $newConfig['i18n']['locale'] ??= $currentConfig['locale'] ?? 'en_US';
        $newConfig['i18n']['timezone'] ??= $currentConfig['timezone'] ?? 'UTC';
        $newConfig['i18n']['date_format'] ??= 'medium';
        $newConfig['i18n']['time_format'] ??= 'short';
        $newConfig['db']['port'] ??= '3306';
        $newConfig['api']['throttle_delay'] ??= 2;
        $newConfig['api']['rate_span_login'] ??= 60;
        $newConfig['api']['rate_limit_login'] ??= 20;
        $newConfig['api']['CSRFPrevention'] ??= true;
        $newConfig['api']['rate_limit_whitelist'] ??= [];
        $newConfig['debug_and_monitoring']['debug'] ??= $newConfig['debug'] ?? false;
        $newConfig['debug_and_monitoring']['log_stacktrace'] ??= $newConfig['log_stacktrace'] ?? true;
        $newConfig['debug_and_monitoring']['stacktrace_length'] ??= $newConfig['stacktrace_length'] ?? 25;
        $newConfig['debug_and_monitoring']['report_errors'] ??= false;
        if (!class_exists('Uuid')) {
            $this->registerFallbackAutoloader();
        }
        $newConfig['info']['instance_id'] ??= Uuid::uuid4()->toString();
        $newConfig['info']['salt'] ??= $newConfig['salt'];

        // Remove depreciated config keys/subkeys.
        $depreciatedConfigKeys = ['guzzle', 'locale', 'locale_date_format', 'locale_time_format', 'timezone', 'sef_urls', 'salt', 'path_logs', 'log_to_db'];
        $depreciatedConfigSubkeys = [
            'security' => 'cookie_lifespan',
        ];
        $newConfig = array_diff_key($newConfig, array_flip($depreciatedConfigKeys));
        foreach ($depreciatedConfigSubkeys as $key => $subkey) {
            unset($newConfig[$key][$subkey]);
        }

        if ($currentConfig === $newConfig) {
            return;
        }

        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($newConfig, true) . ';';

        // Write updated configuration file.
        try {
            $filesystem->dumpFile(PATH_CONFIG, $output);
        } catch (IOException) {
            throw new Exception('Error when writing updated configuration file.');
        }
    }

    /**
     * Apply all relevant patches to current FOSSBilling instance.
     */
    public function applyCorePatches(): void
    {
        $patchLevel = $this->getPatchLevel();
        $patches = $this->getPatches($patchLevel);
        if ($patches) {
            foreach ($patches as $patchLevel => $patch) {
                call_user_func($patch);
                $this->setPatchLevel($patchLevel);
            }
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
        $filesystem = new Filesystem();

        foreach ($files as $file => $action) {
            try {
                if ($action == 'unlink' && $filesystem->exists($file)) {
                    $filesystem->remove($file);
                } elseif ($filesystem->exists($file)) {
                    $filesystem->rename($file, $action);
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
        $statement = $this->di['pdo']->prepare($sql);

        try {
            $statement->execute();
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
    private function getPatchLevel(): int|null
    {
        $sql = 'SELECT value FROM setting WHERE param = :param';
        $sqlStatement = $this->di['pdo']->prepare($sql);
        $sqlStatement->execute(['param' => 'last_patch']);
        $result = $sqlStatement->fetchColumn();

        return intval($result) ?: null;
    }

    /**
     * Set the current patch level of FOSSBilling.
     *
     * @param int $patchLevel The last executed patch level
     */
    private function setPatchLevel(int $patchLevel): void
    {
        if (is_null($this->getPatchLevel())) {
            $sql = 'INSERT INTO setting (param, value, public, updated_at, created_at) VALUES ("last_patch", :value, 1, :u, :c)';
            $sqlStatement = $this->di['pdo']->prepare($sql);
            $sqlStatement->execute(['value' => $patchLevel, 'c' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s')]);
        } else {
            $sql = 'UPDATE setting SET value = :value, updated_at = :u WHERE param = :param';
            $sqlStatement = $this->di['pdo']->prepare($sql);
            $sqlStatement->execute(['param' => 'last_patch', 'value' => $patchLevel, 'u' => date('Y-m-d H:i:s')]);
        }
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
            25 => function () {
                // Migrate email templates to be compatible with Twig 3.x.
                $q = "UPDATE email_template SET content = REPLACE(content, '{% filter markdown %}', '{% apply markdown %}')";
                $this->executeSql($q);

                $q = "UPDATE email_template SET content = REPLACE(content, '{% endfilter %}', '{% endapply %}')";
                $this->executeSql($q);
            },
            26 => function () {
                // Migration steps from BoxBilling to FOSSBilling - added favicon settings.
                $q = "INSERT INTO setting (param, value, public, category, hash, created_at, updated_at) VALUES ('company_favicon','themes/huraga/assets/favicon.ico',0,NULL,NULL,'2023-01-08 12:00:00','2023-01-08 12:00:00');";
                $this->executeSql($q);
            },
            27 => function () {
                // Migration steps to create table to allow admin users to do password reset.
                $q = 'CREATE TABLE `admin_password_reset` ( `id` bigint(20) NOT NULL AUTO_INCREMENT, `admin_id` bigint(20) DEFAULT NULL, `hash` varchar(100) DEFAULT NULL, `ip` varchar(45) DEFAULT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `admin_id_idx` (`admin_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
                $this->executeSql($q);
            },
            28 => function () {
                // Patch to remove .html from email templates action code.
                // @see https://github.com/FOSSBilling/FOSSBilling/issues/863
                $q = "UPDATE email_template SET action_code = REPLACE(action_code, '.html', '')";
                $this->executeSql($q);
            },
            29 => function () {
                // Patch to update email templates to use format_date/format_datetime filters
                // instead of removed bb_date/bb_datetime filters.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/948
                $q = "UPDATE email_template SET content = REPLACE(content, 'bb_date', 'format_date')";
                $this->executeSql($q);
                $q = "UPDATE email_template SET content = REPLACE(content, 'bb_datetime', 'format_datetime')";
                $this->executeSql($q);
            },
            30 => function () {
                // Patch to remove the old guzzlehttp package, as we no longer
                // use it. Also serves as an example for how to perform file action.
                $fileActions = [
                    PATH_VENDOR . DIRECTORY_SEPARATOR . 'guzzlehttp' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            31 => function () {
                // Patch to remove the old htaccess.txt file, and any old config.php backup.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1075
                $fileActions = [
                    PATH_ROOT . DIRECTORY_SEPARATOR . 'htaccess.txt' => 'unlink',
                    PATH_ROOT . DIRECTORY_SEPARATOR . 'config.php.old' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            32 => function () {
                // Patch to remove the old phpmailer package, some leftover
                // admin_default files, and old Box_ classes we've removed or replaced.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1091
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1063
                $fileActions = [
                    PATH_VENDOR . DIRECTORY_SEPARATOR . 'phpmailer' => 'unlink',
                    PATH_THEMES . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'images' => 'unlink',
                    PATH_THEMES . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'bb-deprecated.scss' => 'unlink',
                    PATH_THEMES . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'dataTable-deprecated.scss' => 'unlink',
                    PATH_THEMES . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'main-deprecated.scss' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Mail.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Ftp.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'FileCacheExcption.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Zip.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Requirements.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Version.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Extension.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Cookie.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'ExceptionAuth.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Response.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Config.php' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            33 => function () {
                // Patch to remove the old FileCache class that was replaced with Symfony's Cache component.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1184
                $fileActions = [
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FileCache.php' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            34 => function () {
                // Adds the new "fingerprint" to the session table, to allow us to fingerprint devices and help prevent against attacks such as session hijacking.
                $q = 'ALTER TABLE session ADD fingerprint TEXT;';
                $this->executeSql($q);
            },
            35 => function () {
                // Adds the new "created_at" to the session table, to ensure sessions are destroyed after they reach their maximum age.
                $q = 'ALTER TABLE session ADD created_at int(11);';
                $this->executeSql($q);
            },
            36 => function () {
                // Patch to complete merging the Kb and Support modules.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1180

                // Renames the "kb_article" and "kb_article_category" tables to "support_kb_article" and "support_kb_article_category", respectively.
                $q = 'RENAME TABLE kb_article TO support_kb_article, kb_article_category TO support_kb_article_category;';
                $this->executeSql($q);

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

                // Finally, remove old Kb extension files/folders.
                $fileActions = [
                    PATH_MODS . DIRECTORY_SEPARATOR . 'Kb' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            37 => function () {
                // Patch to complete remove the outdated queue module.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1777

                $ext_service = $this->di['mod_service']('extension');
                // If the queue extension exists, uninstall it.
                $queue_ext = $ext_service->findExtension('mod', 'queue');
                if ($queue_ext instanceof \Model_Extension) {
                    $ext_service->uninstall($queue_ext);
                }

                // Finally, remove old queue module from the disk.
                $fileActions = [
                    PATH_MODS . DIRECTORY_SEPARATOR . 'Queue' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            38 => function () {
                // We need to remove the old ISPConfig3 and Virtualmin server managers from disk or else the leftover files could prevent the "hosting plans and servers" page from being loaded.
                $fileActions = [
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Server' . DIRECTORY_SEPARATOR . 'Manager' . DIRECTORY_SEPARATOR . 'Ispconfig3.php' => 'unlink',
                    PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Server' . DIRECTORY_SEPARATOR . 'Manager' . DIRECTORY_SEPARATOR . 'Virtualmin.php' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            39 => function () {
                // The Serbian language was incorrectly placed into a folder named `srp` by Crowdin which is now corrected for via the locale repo and as such we need to delete the old directory.
                // @see https://github.com/FOSSBilling/locale/issues/212
                $fileActions = [
                    PATH_LANGS . DIRECTORY_SEPARATOR . 'srp' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
        ];
        ksort($patches, SORT_NATURAL);

        return array_filter($patches, fn ($key) => $key > $patchLevel, ARRAY_FILTER_USE_KEY);
    }

    private function registerFallbackAutoloader()
    {
        $loader = new \AntCMS\AntLoader([
            'mode' => 'filesystem',
            'path' => PATH_CACHE . DIRECTORY_SEPARATOR . 'fallbackClassMap.php',
        ]);
        $loader->addNamespace('', PATH_VENDOR);
        $loader->checkClassMap();
        $loader->register(true);
    }
}

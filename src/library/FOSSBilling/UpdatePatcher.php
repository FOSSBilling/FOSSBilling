<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

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

    public function isOutdated(): bool
    {
        $patchLevel = $this->getPatchLevel();
        $patches = $this->getPatches($patchLevel);
        return count($patches) !== 0;
    }

    /**
     * Apply configuration file patches.
     *
     * @return void
     */
    public function applyConfigPatches(): void
    {
        $filesystem = new Filesystem();

        $configPath = PATH_ROOT . '/config.php';
        $currentConfig = include $configPath;

        if (!is_array($currentConfig)) {
            throw new \Box_Exception('Unable to load existing configuration');
        }

        // Create backup of current configuration.
        try {
            $filesystem->copy($configPath, substr($configPath, 0, -4) . '.old.php');
        } catch (FileNotFoundException | IOException) {
            throw new \Box_Exception('Unable to create backup of configuration file');
        }

        $newConfig = $currentConfig;
        $newConfig['security']['mode'] ??= 'strict';
        $newConfig['security']['force_https'] ??= true;
        $newConfig['security']['cookie_lifespan'] ??= 7200;
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

        // Remove depreciated config keys/subkeys.
        $depreciatedConfigKeys = ['guzzle', 'locale', 'locale_date_format', 'locale_time_format', 'timezone', 'sef_urls'];
        $depreciatedConfigSubkeys = [];
        $newConfig = array_diff_key($newConfig, array_flip($depreciatedConfigKeys));
        foreach ($depreciatedConfigSubkeys as $key => $subkey) {
            unset($newConfig[$key][$subkey]);
        }

        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($newConfig, true) . ';';

        // Write updated configuration file.
        try {
            $filesystem->dumpFile($configPath, $output);
        } catch (IOException) {
            throw new \Box_Exception('Error when writing updated configuration file.');
        }
    }

    /**
     * Apply all relevant patches to current FOSSBilling instance.
     *
     * @return void
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
     *
     * @return void
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
     * @param $sql The SQL statement to execute.
     *
     * @return void
     */
    private function executeSql($sql): void
    {
        $statement = $this->di['pdo']->prepare($sql);
        try {
            $statement->execute();
        } catch (\Box_Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Get the current patch level of FOSSBilling.
     *
     * @return int|null The current patch level.
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
     * @param int
     *
     * @return void
     */
    private function setPatchLevel(int $patchLevel): void
    {
        if (is_null($this->getPatchLevel())) {
            $sql = 'INSERT INTO setting (last_patch, value, public, updated_at, created_at) VALUES (:value, 1, :u, :c)';
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
     * @param int|null $patchLevel The current patch level of FOSSBilling.
     *
     * @return array Array containing the patches to be executed, in order.
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
                $q = "INSERT INTO setting ('id', 'param', 'value', 'public', 'category', 'hash', 'created_at', 'updated_at') VALUES (29,'company_favicon','themes/huraga/assets/favicon.ico',0,NULL,NULL,'2023-01-08 12:00:00','2023-01-08 12:00:00');";
                $this->executeSql($q);
            },
            27 => function () {
                // Migration steps to create table to allow admin users to do password reset.
                $q = "CREATE TABLE `admin_password_reset` ( `id` bigint(20) NOT NULL AUTO_INCREMENT, `admin_id` bigint(20) DEFAULT NULL, `hash` varchar(100) DEFAULT NULL, `ip` varchar(45) DEFAULT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `admin_id_idx` (`admin_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
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
                    __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'guzzlehttp' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            31 => function () {
                // Patch to remove the old htaccess.txt file, and any old config.php backup.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1075
                $fileActions = [
                    __DIR__ . DIRECTORY_SEPARATOR . 'htaccess.txt' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'config.php.old' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            32 => function () {
                // Patch to remove the old phpmailer package, some leftover
                // admin_default files, and old Box_ classes we've removed or replaced.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1091
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1063
                $fileActions = [
                    __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpmailer' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'images' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'bb-deprecated.scss' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'dataTable-deprecated.scss' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'admin_default' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'scss' . DIRECTORY_SEPARATOR . 'main-deprecated.scss' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Mail.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Ftp.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'FileCacheExcption.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Zip.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Requirements.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Version.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Extension.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Cookie.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'ExceptionAuth.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Response.php' => 'unlink',
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Box' . DIRECTORY_SEPARATOR . 'Config.php' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            33 => function () {
                // Patch to remove the old FileCache class that was replaced with Symfony's Cache component.
                // @see https://github.com/FOSSBilling/FOSSBilling/pull/1184
                $fileActions = [
                    __DIR__ . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'FileCache.php' => 'unlink',
                ];
                $this->executeFileActions($fileActions);
            },
            34 => function () {
                // Adds the new "fingerprint" to the session table, to allow us to fingerprint devices and help prevent against attacks such as session hijacking.
                $q = "ALTER TABLE session ADD fingerprint TEXT;";
                $this->executeSql($q);
            },
            35 => function () {
                // Patch to add update_orders field to servicedownloadable table.
                $q = "ALTER TABLE service_downloadable ADD update_orders TINYINT(1) NOT NULL DEFAULT '0';";
                $this->executeSql($q);
            }
        ];
        ksort($patches, SORT_NATURAL);

        return array_filter($patches, fn($key) => $key > $patchLevel, ARRAY_FILTER_USE_KEY);
    }
}

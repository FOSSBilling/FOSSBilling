<?php
/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;

class Box_Update
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    private string $_url = 'https://api.github.com/repos/FOSSBilling/FOSSBilling/releases/latest';
    private string $_preview_url = 'https://fossbilling.org/downloads/preview/';

    /**
     * Determines which branch FOSSBilling is configured to update from.
     */
    public function getUpdateBranch(): string
    {
        return (isset($this->di['config']['update_branch'])) ? $this->di['config']['update_branch'] : "release";
    }

    /**
     * Checks if FOSSBilling is running a preview version or not
     */
    public function isPreviewVersion(): bool
    {
        $reg = '^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$^';
        if (preg_match($reg, $this->getLatestVersion())) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns latest information
     *
     * @return array
     */
    private function _getLatestVersionInfo(): array
    {
        return $this->di['cache']->get('Update._getLatestVersionInfo', function (ItemInterface $item) {
            $item->expiresAfter(24 * 60 * 60);

            return $this->getJson();
        });
    }

    /**
     * Returns latest release notes
     *
     * @return string
     */
    public function getLatestReleaseNotes(): string
    {
        if ($this->getUpdateBranch() === "preview") {
            $compareLink = 'https://github.com/FOSSBilling/FOSSBilling/compare/' . $this->getLatestVersion() . '...main';
            return "Release notes are not available for preview builds. You can check the latest changes on our [GitHub]($compareLink) repository.";
        }

        $response = $this->_getLatestVersionInfo();
        if (!isset($response['body'])) {
            return "**Error: Release info unavailable**";
        }
        return $response['body'];
    }

    /**
     * Returns latest version number
     * @return string
     */
    public function getLatestVersion(): string
    {
        if ($this->getUpdateBranch() === "preview") {
            return \FOSSBilling\Version::VERSION;
        }

        $response = $this->_getLatestVersionInfo();
        if (!isset($response['tag_name'])) {
            return \FOSSBilling\Version::VERSION;
        }
        return $response['tag_name'];
    }

    /**
     * Latest version link
     * @return string
     */
    public function getLatestVersionDownloadLink(): string
    {
        if ($this->getUpdateBranch() === "preview") {
            return $this->_preview_url;
        }

        $response = $this->_getLatestVersionInfo();
        return $response['assets'][0]['browser_download_url'];
    }

    /**
     * Check if we need to update current FOSSBilling version
     * @return bool
     */
    public function getCanUpdate(): bool
    {
        $version = $this->getLatestVersion();
        $result = \FOSSBilling\Version::compareVersion($version);
        $result = ($this->isPreviewVersion() && $this->getUpdateBranch() === "release") ? 1 : $result;
        return ($result > 0);
    }

    /**
     * Check if given file is same as original
     * @param string $file - filepath
     * @return bool
     */
    private function isHashValid($file): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        $response = $this->_getLatestVersionInfo();
        $hash = md5($response->version . filesize($file));
        return ($hash == $response->hash);
    }

    public function getJson(): array
    {
        $url = $this->_url;
        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        return $response->toArray();
    }

    /**
     * Perform update
     *
     * @throws \Box_Exception
     */
    public function performUpdate()
    {
        if ($this->getUpdateBranch() !== 'preview' && !$this->getCanUpdate()) {
            throw new \Box_Exception('You have latest version of FOSSBilling. You do not need to update.');
        }

        error_log('Started FOSSBilling auto-update script');
        $latestVersionNum = $this->getLatestVersion();
        $archiveFile = PATH_CACHE . DIRECTORY_SEPARATOR . $latestVersionNum . '.zip';

        // Download latest archive.
        try {
            $httpClient = HttpClient::create([
                'timeout' => 5,
                'max_duration' => 120
            ]);
            $response = $httpClient->request('GET', $this->getLatestVersionDownloadLink());

            $fileHandler = fopen($archiveFile, 'w');
            foreach ($httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface|
        \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            error_log($e->getMessage());
            throw new \Box_Exception('Failed to download the update archive. Further details are available in the error log.');
        }

        //@todo validate downloaded file hash

        // Extract latest archive on top of current version.
        try {
            $zip = new \PhpZip\ZipFile();
            $zip->openFile($archiveFile);
            $zip->extractTo(PATH_ROOT);
            $zip->close();
        } catch (\PhpZip\Exception\ZipException $e) {
            error_log($e->getMessage());
            throw new \Box_Exception('Failed to extract file, please check file and folder permissions. Further details are available in the error log.');
        }

        if (file_exists(PATH_ROOT . '/foss-update.php')) {
            error_log('Calling foss-update.php script from auto-updater');
            file_get_contents(BB_URL . 'foss-update.php');
        }

        // Migrate the configuration file
        $this->performConfigUpdate();

        // clean up things
        $this->di['tools']->emptyFolder(PATH_CACHE);
        $this->di['tools']->emptyFolder(PATH_ROOT . '/install');
        rmdir(PATH_ROOT . '/install');

        // Log off the current user and destroy the session.
        unset($_COOKIE['BOXADMR']);
        $this->di['session']->delete('admin');
        session_destroy();
        return true;
    }

    /**
     * Perform config file update.
     *
     * @throws \Box_Exception
     */
    public function performConfigUpdate()
    {
        $configPath = PATH_ROOT . '/config.php';
        $currentConfig = include $configPath;

        if (!is_array($currentConfig)) {
            throw new \Box_Exception('Unable to load existing configuration. performConfigUpdate() is unable to progress.');
        }
        if (!copy($configPath, substr($configPath, 0, -4) . '.old.php')) {
            throw new \Box_Exception('Unable to create backup of configuration file. Cancelling config migration.');
        }

        $newConfig = $currentConfig;

        $newConfig['security']['mode'] ??= 'strict';
        $newConfig['security']['force_https'] ??= true;
        $newConfig['security']['cookie_lifespan'] ??= '7200';

        $newConfig['update_branch'] ??= 'release';
        $newConfig['log_stacktrace'] ??= true;
        $newConfig['stacktrace_length'] ??= 25;

        $newConfig['maintenance_mode']['enabled'] ??= false;
        $newConfig['maintenance_mode']['allowed_urls'] ??= [];
        $newConfig['maintenance_mode']['allowed_ips'] ??= [];

        $newConfig['disable_auto_cron'] ??= false;

        $newConfig['i18n']['locale'] = $currentConfig['locale'] ?? 'en_US';
        $newConfig['i18n']['timezone'] = $currentConfig['timezone'] ?? 'UTC';
        $newConfig['i18n']['date_format'] ??= 'medium';
        $newConfig['i18n']['time_format'] ??= 'short';

        $newConfig['db']['port'] ??= '3306';

        $newConfig['api']['throttle_delay'] ??= 2;
        $newConfig['api']['rate_span_login'] ??= 60;
        $newConfig['api']['rate_limit_login'] ??= 20;
        $newConfig['api']['CSRFPrevention'] ??= true;

        // Remove depreciated config keys/subkeys.
        $depreciatedConfigKeys = ['guzzle', 'locale', 'locale_date_format', 'locale_time_format', 'timezone', 'sef_urls'];
        $depreciatedConfigSubkeys = [];
        $newConfig = array_diff_key($newConfig, array_flip($depreciatedConfigKeys));
        foreach ($depreciatedConfigSubkeys as $key => $subkey) {
            unset($newConfig[$key][$subkey]);
        }

        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($newConfig, true) . ';';
        if (file_put_contents($configPath, $output)) {
            return true;
        } else {
            throw new \Box_Exception('Error when writing updated configuration file.');
        }
    }
}

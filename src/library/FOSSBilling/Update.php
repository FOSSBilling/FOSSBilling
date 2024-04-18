<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Update implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private array $allowedDownloadPrefixes = [
        'https://github.com/FOSSBilling/FOSSBilling/releases/',
        'https://api.github.com/repos/FOSSBilling/FOSSBilling/releases/assets/',
        'https://s4-fossb-2.fi-hel2.upcloudobjects.com/releases/',
    ];

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Get the branch configured to update from.
     *
     * @return string branch to update from
     */
    public function getUpdateBranch(): string
    {
        return Config::getProperty('update_branch', 'release');
    }

    /**
     * Get latest release notes for the configured update branch.
     *
     * @return string release notes for the latest version
     */
    public function getLatestReleaseNotes(): string
    {
        $updateBranch = $this->getUpdateBranch();

        return $this->getLatestVersionInfo($updateBranch)['release_notes'];
    }

    /**
     * Get latest version number for the configured update branch.
     *
     * @return string version number of the latest version
     */
    public function getLatestVersion(): string
    {
        $updateBranch = $this->getUpdateBranch();

        return $this->getLatestVersionInfo($updateBranch)['version'];
    }

    /**
     * Builds a complete changelog for all updates between the the newest FOSSBilling version and an ending version number.
     *
     * @param string $end (optional) What version number to end on. Defaults to the current version.
     */
    private function buildCompleteChangelog(string $end = Version::VERSION): string
    {
        if (Version::isPreviewVersion($end)) {
            return 'Changelogs are not available when updating from a preview release';
        }

        return $this->di['cache']->get("changelog_from_$end", function (ItemInterface $item) use ($end) {
            $item->expiresAfter(3600);

            $httpClient = HttpClient::create(['bindto' => BIND_TO]);
            $response = $httpClient->request('GET', "https://api.fossbilling.org/versions/build_changelog/$end");
            $result = $response->toArray();

            return $result['result'];
        });
    }

    /**
     * Returns information about the latest version of the specified branch.
     *
     * @param string $branch  the branch to return the latest information for;
     *                        valid values are: 'preview' or 'release'
     * @param bool   $refetch Set to `true` to have FOSSBilling invalidate the update cache and fetch the latest info
     *
     * @throws Exception if there is an error downloading the latest
     *                   version information
     */
    public function getLatestVersionInfo(string $branch = null, bool $refetch = false): array
    {
        $branch ??= $this->getUpdateBranch();
        $branch = (in_array($branch, ['release', 'preview'])) ? $branch : 'release';

        if ($branch === 'preview') {
            $currentVersion = Version::VERSION;
            $compareLink = "https://github.com/FOSSBilling/FOSSBilling/compare/{$currentVersion}...main";
            $downloadUrl = 'https://fossbilling.org/downloads/preview/';

            return [
                'version' => Version::VERSION,
                'download_url' => $downloadUrl,
                'release_notes' => "Release notes are not available for the preview branch. You can check the latest changes on our [GitHub]($compareLink) repository.",
                'update_type' => 0,
                'last_check' => time(),
                'next_check' => time() + 3600,
                'branch' => 'preview',
                'minimum_php_version' => 'unknown',
            ];
        } else {
            $key = "Update.latest_{$branch}_version_info";

            // Delete the cached result to force a refetch
            if ($refetch) {
                $this->di['cache']->delete($key);
            }

            return $this->di['cache']->get($key, function (ItemInterface $item) use ($branch) {
                $item->expiresAfter(3600);

                try {
                    $releaseInfoUrl = 'https://api.fossbilling.org/versions/latest';
                    $httpClient = HttpClient::create(['bindto' => BIND_TO]);
                    $response = $httpClient->request('GET', $releaseInfoUrl);
                    $releaseInfo = $response->toArray()['result'];
                } catch (TransportExceptionInterface|HttpExceptionInterface $e) {
                    error_log($e->getMessage());

                    throw new Exception('Failed to download the latest version information. Further details are available in the error log.');
                }

                return [
                    'version' => $releaseInfo['version'] ?: Version::VERSION,
                    'download_url' => $releaseInfo['download_url'],
                    'release_date' => $releaseInfo['released_on'],
                    'release_notes' => $this->buildCompleteChangelog() ?: '**Error: Release notes unavailable.**',
                    'update_type' => Version::getUpdateType($releaseInfo['version'] ?: Version::VERSION),
                    'last_check' => date('Y-m-d H:i:s'),
                    'next_check' => date('Y-m-d H:i:s', time() + 3600),
                    'branch' => $branch,
                    'minimum_php_version' => $releaseInfo['minimum_php_version'],
                ];
            });
        }
    }

    /**
     * Check if an update is available for the current FOSSBilling version.
     *
     * @return bool true if update is available, false if not
     */
    public function isUpdateAvailable(): bool
    {
        $version = $this->getLatestVersion();
        $result = Version::compareVersion($version);
        $result = (Version::isPreviewVersion() && $this->getUpdateBranch() === 'release') ? 1 : $result;

        return $result > 0;
    }

    public function isBehindOnDBPatches(): bool
    {
        $patcher = new UpdatePatcher();
        $patcher->setDi($this->di);

        return $patcher->availablePatches() > 0;
    }

    /**
     * Perform manual update - apply patches and update config.
     *
     * @throws Exception
     */
    public function performManualUpdate(): void
    {
        // Apply system patches and migrate configuration file.
        $patcher = new UpdatePatcher();
        $patcher->setDi($this->di);
        $patcher->applyCorePatches();
        $patcher->applyConfigPatches();
    }

    /**
     * Perform system update.
     *
     * @throws InformationException if latest version already installed
     * @throws Exception            if unable to download the update archive
     * @throws Exception            if unable to extract the update archive
     */
    public function performUpdate(): void
    {
        $updateBranch = $this->getUpdateBranch();
        if ($updateBranch !== 'preview' && !$this->isUpdateAvailable()) {
            throw new InformationException('You have the latest version of FOSSBilling. You do not need to update.');
        }

        error_log('Started FOSSBilling auto-update script');
        $latestVersionNum = $this->getLatestVersion();
        $archiveFile = PATH_CACHE . DIRECTORY_SEPARATOR . $latestVersionNum . '.zip';

        $releaseInfo = $this->getLatestVersionInfo($updateBranch);

        // Validate the required PHP version is met
        $requiredPHPVersion = $releaseInfo['minimum_php_version'];
        if ($requiredPHPVersion !== 'unknown' && version_compare(PHP_VERSION, $requiredPHPVersion, '<')) {
            throw new InformationException('FOSSBilling :version: requires at least PHP :min_php:, but you are running :current_php:.', [':version:' => $latestVersionNum, ':min_php:' => $requiredPHPVersion, ':current_php:' => PHP_VERSION]);
        }

        // Perform a sanity check that the download URL is a trusted one
        if ($updateBranch !== 'preview') {
            $allowed = false;
            foreach ($this->allowedDownloadPrefixes as $prefix) {
                $allowed = $allowed ? true : str_starts_with($releaseInfo['download_url'], $prefix);
            }

            if (!$allowed) {
                throw new InformationException('The download URL for this release was not specified as a trusted one. Update canceled for security reasons.');
            }
        }

        // Download latest version archive for configured update branch.
        try {
            $httpClient = HttpClient::create([
                'timeout' => 30,
                'max_duration' => 120,
                'bindto' => BIND_TO,
            ]);
            $response = $httpClient->request('GET', $releaseInfo['download_url']);

            $fileHandler = fopen($archiveFile, 'w');
            foreach ($httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);
        } catch (TransportExceptionInterface|HttpExceptionInterface $e) {
            error_log($e->getMessage());

            throw new Exception('Failed to download the update archive. Further details are available in the error log.');
        }

        // @TODO - Validate downloaded file hash.

        // Extract latest version archive on top of the current version.
        try {
            $zip = new ZipFile();
            $zip->openFile($archiveFile);
            $zip->extractTo(PATH_ROOT);
            $zip->close();
        } catch (ZipException $e) {
            error_log($e->getMessage());

            throw new Exception('Failed to extract file, please check file and folder permissions. Further details are available in the error log.');
        }

        // Create the update patcher
        $patcher = new UpdatePatcher();
        $patcher->setDi($this->di);

        // Clear the cache folder to reduce chances of errors
        try {
            $filesystem = new Filesystem();
            $filesystem->remove(PATH_CACHE);
            $filesystem->mkdir(PATH_CACHE, 0755);
        } catch (\Exception) {
            // This step is rarely important, we can safely ignore an error here
        }

        // Now run the patches
        $patcher->applyCorePatches();
        $patcher->applyConfigPatches();

        // Clear cache and remove the install folder.
        try {
            $filesystem = new Filesystem();
            $filesystem->remove([PATH_CACHE, PATH_ROOT . '/install']);
            $filesystem->mkdir(PATH_CACHE, 0755);
        } catch (IOException $e) {
            error_log($e->getMessage());

            throw new Exception('Unable to clear cache and/or remove install folder. Further details are available in the error log.');
        }

        // Log off the current user and destroy the session.
        unset($_COOKIE['BOXADMR']);
        $this->di['session']->destroy('admin');
    }
}

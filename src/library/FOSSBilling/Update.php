<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
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
     * @return string Branch to update from.
     */
    public function getUpdateBranch(): string
    {
        return $this->di['config']['update_branch'] ?? 'release';
    }

    /**
     * Get latest release notes for the configured update branch.
     *
     * @return string Release notes for the latest version.
     */
    public function getLatestReleaseNotes(): string
    {
        $updateBranch = $this->getUpdateBranch();
        return $this->getLatestVersionInfo($updateBranch)['release_notes'];
    }

    /**
     * Get latest version number for the configured update branch.
     *
     * @return string Version number of the latest version.
     */
    public function getLatestVersion(): string
    {
        $updateBranch = $this->getUpdateBranch();
        return $this->getLatestVersionInfo($updateBranch)['version'];
    }

    /**
     * Returns information about the latest version of the specified branch.
     *
     * @param string $branch The branch to return the latest information for;
     *                       valid values are: 'preview' or 'release'.
     *
     * @throws \Box_Exception if there is an error downloading the latest
     *                        version information.
     *
     * @return array
     */
    private function getLatestVersionInfo(string $branch = 'release'): array
    {
        $branch = (in_array($branch, ['release', 'preview'])) ? $branch : 'release';

        if ($branch === 'preview') {
            $currentVersion = Version::VERSION;
            $compareLink = "https://github.com/FOSSBilling/FOSSBilling/compare/{$currentVersion}...main";
            $downloadUrl = 'https://fossbilling.org/downloads/preview/';

            return [
                'version' => Version::VERSION,
                'download_url' => $downloadUrl,
                'release_notes' => "Release notes are not available for the preview branch. You can check the latest changes on our [GitHub]($compareLink) repository.",
            ];
        } else {
            return $this->di['cache']->get("Update.latest_{$branch}_version_info", function (ItemInterface $item) {
                $item->expiresAfter(24 * 60 * 60);

                try {
                    $releaseInfoUrl = 'https://api.github.com/repos/FOSSBilling/FOSSBilling/releases/latest';
                    $httpClient = HttpClient::create();
                    $response = $httpClient->request('GET', $releaseInfoUrl);
                    $releaseInfo = $response->toArray();
                } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
                    error_log($e->getMessage());
                    throw new \Box_Exception('Failed to download the latest version information. Further details are available in the error log.');
                }

                return [
                    'version' => $releaseInfo['tag_name'] ?: Version::VERSION,
                    'download_url' => $releaseInfo['assets'][0]['browser_download_url'],
                    'release_date' => $releaseInfo['published_at'],
                    'release_notes' => $releaseInfo['body'] ?: '**Error: Release notes unavailable.**',
                ];
            });
        }
    }

    /**
     * Checks if current FOSSBilling version is a preview.
     *
     * @return bool True if preview, false if not.
     */
    private function isPreviewVersion(): bool
    {
        $reg = '^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$^';
        if (preg_match($reg, $this->getLatestVersion())) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check if an update is available for the current FOSSBilling version.
     *
     * @return bool True if update is available, false if not.
     */
    public function isUpdateAvailable(): bool
    {
        $version = $this->getLatestVersion();
        $result = Version::compareVersion($version);
        $result = ($this->isPreviewVersion() && $this->getUpdateBranch() === "release") ? 1 : $result;
        return ($result > 0);
    }

    /**
     * Perform manual update - apply patches and update config.
     *
     * @return void
     */
    public function performManualUpdate(): void
    {
        // Apply system patches and migrate configuration file.
        $patcher = new UpdatePatcher;
        $patcher->setDi($this->di);
        $patcher->applyCorePatches();
        $patcher->applyConfigPatches();
    }

    /**
     * Perform system update.
     *
     * @throws \Box_Exception if latest version already installed.
     * @throws \Box_Exception if unable to download the update archive.
     * @throws \Box_Exception if unable to extract the update archive.
     *
     * @return void
     */
    public function performUpdate(): void
    {
        $updateBranch = $this->getUpdateBranch();
        if ($updateBranch !== 'preview' && !$this->isUpdateAvailable()) {
            throw new \Box_Exception('You have latest version of FOSSBilling. You do not need to update.');
        }

        error_log('Started FOSSBilling auto-update script');
        $latestVersionNum = $this->getLatestVersion();
        $archiveFile = PATH_CACHE . DIRECTORY_SEPARATOR . $latestVersionNum . '.zip';

        // Download latest version archive for configured update branch.
        try {
            $httpClient = HttpClient::create([
                'timeout' => 30,
                'max_duration' => 120
            ]);
            $response = $httpClient->request('GET', $this->getLatestVersionInfo($updateBranch)['download_url']);

            $fileHandler = fopen($archiveFile, 'w');
            foreach ($httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);
        } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
            error_log($e->getMessage());
            throw new \Box_Exception('Failed to download the update archive. Further details are available in the error log.');
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
            throw new \Box_Exception('Failed to extract file, please check file and folder permissions. Further details are available in the error log.');
        }

        // Apply system patches and migrate configuration file.
        $patcher = new UpdatePatcher;
        $patcher->setDi($this->di);
        $patcher->applyCorePatches();

        // Apply configuration file patches.
        $patcher->applyConfigPatches();

        // Clear cache and remove install folder.
        try {
            $filesystem = new Filesystem();
            $filesystem->remove([PATH_CACHE, PATH_ROOT . '/install']);
            $filesystem->mkdir(PATH_CACHE, 0777);
        } catch (IOException $e) {
            error_log($e->getMessage());
            throw new \Box_Exception("Unable to clear cache and/or remove install folder. Further details are available in the error log.");
        }

        // Log off the current user and destroy the session.
        unset($_COOKIE['BOXADMR']);
        $this->di['session']->destroy('admin');
    }
}

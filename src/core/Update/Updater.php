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
use FOSSBilling\Core\Exception\BaseException;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\System\Config;
use FOSSBilling\Core\System\Version;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Updater implements InjectionAwareInterface
{
    /**
     * Name of the marker file that tells load.php to refuse all requests while
     * performUpdate() is writing files to PATH_ROOT.
     *
     * This filename is duplicated as a literal string at the top of load.php,
     * which has to check for the lock before the Composer autoloader (and
     * therefore this class) is available. Keep both in sync if you change it.
     */
    public const string LOCK_FILENAME = '.update-lock';

    /**
     * How long a lock file is honored before it's treated as abandoned - see the
     * comment above isCoreUpdateLockActive() in load.php, which applies the same
     * window to decide whether to keep refusing requests.
     */
    private const int LOCK_STALE_SECONDS = 600;

    protected ?\Pimple\Container $di = null;
    private array $allowedDownloadPrefixes = [
        'https://github.com/FOSSBilling/FOSSBilling/releases/',
        'https://api.github.com/repos/FOSSBilling/FOSSBilling/releases/assets/',
        // Mirrored on our own IPv6-reachable storage - github.com and api.github.com
        // have no AAAA record, so IPv6-only hosts can't reach either prefix above.
        // See https://github.com/FOSSBilling/FOSSBilling/issues/2479.
        'https://download.fossbilling.org/releases/',
    ];
    private Filesystem $filesystem;

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

            $httpClient = $this->di['http_client'];
            $response = $httpClient->request('GET', "https://api.fossbilling.net/versions/v1/build_changelog/{$end}");
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
     * @throws BaseException if there is an error downloading the latest
     *                       version information
     */
    public function getLatestVersionInfo(?string $branch = null, bool $refetch = false): array
    {
        $branch ??= $this->getUpdateBranch();
        $branch = (in_array($branch, ['release', 'preview'])) ? $branch : 'release';

        if ($branch === 'preview') {
            $key = 'Update.latest_preview_version_info_v1';

            if ($refetch) {
                $this->di['cache']->delete($key);
            }

            return $this->di['cache']->get($key, function (ItemInterface $item) {
                $item->expiresAfter(300);

                try {
                    $response = $this->di['http_client']->request('GET', 'https://api.fossbilling.net/previews/v1/main');
                    $previewInfo = $response->toArray()['result'] ?? null;
                } catch (TransportExceptionInterface|HttpExceptionInterface $e) {
                    $this->di['logger']->withChannel('update')->error($e->getMessage());

                    throw new BaseException('Failed to download the latest preview information. Further details are available in the error log.');
                }

                if (!is_array($previewInfo)) {
                    throw new BaseException('The previews API returned invalid preview metadata.');
                }

                $downloadUrl = $previewInfo['download_url'] ?? null;
                if (!is_string($downloadUrl) || !str_starts_with($downloadUrl, 'https://download.fossbilling.org/')) {
                    throw new BaseException('The previews API returned invalid preview metadata.');
                }

                $shortSha = $previewInfo['short_sha'] ?? null;
                if (!is_string($shortSha) || $shortSha === '') {
                    $commitSha = $previewInfo['commit_sha'] ?? null;
                    $shortSha = is_string($commitSha) && $commitSha !== '' ? substr($commitSha, 0, 7) : Version::VERSION;
                }
                $shortSha = strtolower(trim($shortSha));
                if ($shortSha !== Version::VERSION && preg_match('/\A[0-9a-f]{7,40}\z/', $shortSha) !== 1) {
                    throw new BaseException('The previews API returned an invalid preview commit identifier.');
                }

                $currentVersion = Version::VERSION;
                $compareLink = "https://github.com/FOSSBilling/FOSSBilling/compare/{$currentVersion}...main";

                return [
                    'version' => $shortSha,
                    'download_url' => $downloadUrl,
                    'release_notes' => "Release notes are not available for the preview branch. You can check the latest changes on our [GitHub]({$compareLink}) repository.",
                    'update_type' => 0,
                    'last_check' => date('Y-m-d H:i:s'),
                    'next_check' => date('Y-m-d H:i:s', time() + 300),
                    'branch' => 'preview',
                    'minimum_php_version' => 'unknown',
                    'digest' => $previewInfo['digest'] ?? null,
                    'commit_sha' => $previewInfo['commit_sha'] ?? null,
                    'short_sha' => $shortSha,
                    'release_date' => $previewInfo['last_modified'] ?? $previewInfo['created_at'] ?? null,
                ];
            });
        }
        // The response shape changed when the API digest became mandatory; do not reuse
        // cached metadata created before that contract existed.
        $key = "Update.latest_{$branch}_version_info_v2";

        // Delete the cached result to force a refetch
        if ($refetch) {
            $this->di['cache']->delete($key);
        }

        return $this->di['cache']->get($key, function (ItemInterface $item) use ($branch) {
            $item->expiresAfter(3600);

            try {
                $releaseInfoUrl = 'https://api.fossbilling.net/versions/v1/latest';
                $httpClient = $this->di['http_client'];
                $response = $httpClient->request('GET', $releaseInfoUrl);
                $releaseInfo = $response->toArray()['result'];
            } catch (TransportExceptionInterface|HttpExceptionInterface $e) {
                $this->di['logger']->withChannel('update')->error($e->getMessage());

                throw new BaseException('Failed to download the latest version information. Further details are available in the error log.');
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
                'digest' => $releaseInfo['digest'] ?? null,
            ];
        });
    }

    /**
     * Resolve the SHA-256 digest for the exact update archive being downloaded.
     */
    private function getArchiveDigest(array $releaseInfo): string
    {
        if (!isset($releaseInfo['digest'])) {
            throw new InformationException('The FOSSBilling update API did not provide a SHA-256 digest. Update canceled for security reasons.');
        }

        return $this->normalizeSha256Digest($releaseInfo['digest']);
    }

    private function normalizeSha256Digest(mixed $digest): string
    {
        if (!is_string($digest)) {
            throw new InformationException('The FOSSBilling update API provided an invalid SHA-256 digest. Update canceled for security reasons.');
        }

        $digest = trim($digest);
        if (str_starts_with(strtolower($digest), 'sha256:')) {
            $digest = substr($digest, 7);
        }

        if (preg_match('/\A[0-9a-fA-F]{64}\z/', $digest) !== 1) {
            throw new InformationException('The FOSSBilling update API provided an invalid SHA-256 digest. Update canceled for security reasons.');
        }

        return strtolower($digest);
    }

    private function validateDownloadedArchive(string $archiveFile, array $releaseInfo): void
    {
        try {
            $expectedDigest = $this->getArchiveDigest($releaseInfo);
            $actualDigest = hash_file('sha256', $archiveFile);

            if ($actualDigest === false || !hash_equals($expectedDigest, $actualDigest)) {
                throw new InformationException('The downloaded update archive failed integrity verification. Update canceled for security reasons.');
            }
        } catch (BaseException $e) {
            $this->removeDownloadedArchive($archiveFile);

            throw $e;
        }
    }

    private function removeDownloadedArchive(string $archiveFile): void
    {
        try {
            $this->filesystem->remove($archiveFile);
        } catch (IOException $e) {
            $this->di['logger']->withChannel('update')->error($e->getMessage());
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

        if ($this->getUpdateBranch() === 'preview') {
            return $version !== Version::VERSION;
        }

        $result = Version::compareVersion($version);
        $result = (Version::isPreviewVersion() && $this->getUpdateBranch() === 'release') ? 1 : $result;

        return $result > 0;
    }

    public function isBehindOnDBPatches(): bool
    {
        $patcher = new Patcher();
        $patcher->setDi($this->di);

        return $patcher->availablePatches() > 0;
    }

    /**
     * Perform manual update - apply patches and update config.
     *
     * @throws BaseException
     */
    public function performManualUpdate(): void
    {
        try {
            $this->filesystem->remove(PATH_CACHE);
            $this->filesystem->mkdir(PATH_CACHE, 0o755);
        } catch (IOException) {
            // Best effort: continue with patching even if the pre-clear fails.
        }

        // Apply system patches and migrate configuration file.
        $patcher = new Patcher();
        $patcher->setDi($this->di);
        $patcher->applyConfigPatches(force: true);
        $patcher->applyCorePatches(force: true);

        try {
            $this->filesystem->remove(PATH_CACHE);
            $this->filesystem->mkdir(PATH_CACHE, 0o755);
        } catch (IOException $e) {
            $this->di['logger']->withChannel('update')->error($e->getMessage());

            throw new BaseException('Unable to clear the cache after applying manual update patches. Further details are available in the error log.');
        }
    }

    /**
     * Perform system update.
     *
     * @throws InformationException if latest version already installed
     * @throws BaseException        if unable to download the update archive
     * @throws BaseException        if unable to extract the update archive
     */
    public function performUpdate(): void
    {
        $finalization = $this->di['update_finalization'];
        if ($finalization->isRequired()) {
            throw new InformationException('An update finalization is already pending. Complete finalization before starting another update.');
        }

        $readiness = $this->di['update_readiness']->check();
        if (!$readiness['can_update']) {
            throw new BaseException('FOSSBilling does not have sufficient filesystem permissions to perform the update. Resolve the reported issues before trying again.', null, 820);
        }

        $updateBranch = $this->getUpdateBranch();
        if ($updateBranch !== 'preview' && !$this->isUpdateAvailable()) {
            throw new InformationException('You have the latest version of FOSSBilling. You do not need to update.');
        }

        $this->di['logger']->withChannel('update')->info('Started FOSSBilling auto-update script');
        $latestVersionNum = $this->getLatestVersion();
        $archiveFile = Path::join(PATH_CACHE, "{$latestVersionNum}.zip");

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
                $allowed = $allowed ? true : str_starts_with((string) $releaseInfo['download_url'], (string) $prefix);
            }

            if (!$allowed) {
                throw new InformationException('The download URL for this release was not specified as a trusted one. Update canceled for security reasons.');
            }
        }

        // Download latest version archive for configured update branch.
        try {
            $httpClient = $this->di['http_client']->withOptions([
                'timeout' => 30,
                'max_duration' => 120,
            ]);
            $downloadOptions = [];
            if (str_starts_with((string) $releaseInfo['download_url'], 'https://api.github.com/repos/FOSSBilling/FOSSBilling/releases/assets/')) {
                $downloadOptions['headers'] = ['Accept' => 'application/octet-stream'];
            }

            $response = $httpClient->request('GET', $releaseInfo['download_url'], $downloadOptions);

            $fileHandler = fopen($archiveFile, 'w');
            if ($fileHandler === false) {
                throw new \RuntimeException('Unable to create the update archive.');
            }

            try {
                foreach ($httpClient->stream($response) as $chunk) {
                    $content = (string) $chunk->getContent();
                    $written = fwrite($fileHandler, $content);
                    if ($written === false || $written !== strlen($content)) {
                        throw new \RuntimeException('Unable to write the update archive.');
                    }
                }
            } finally {
                fclose($fileHandler);
            }
        } catch (\Throwable $e) {
            $this->removeDownloadedArchive($archiveFile);
            $this->di['logger']->withChannel('update')->error($e->getMessage());

            throw new BaseException('Failed to download the update archive. Further details are available in the error log.');
        }

        $this->validateDownloadedArchive($archiveFile, $releaseInfo);

        /*
         * From here until the lock is released below, files under PATH_ROOT are
         * being overwritten in place while the site may still be serving other
         * requests (this HTTP request is the only one that knows an update is
         * running). A request that lands mid-extraction can autoload a mix of
         * old and new class files and fatal out with an "incompatible
         * declaration" error - see https://github.com/FOSSBilling/FOSSBilling/issues/4159.
         *
         * load.php refuses to serve any request while this lock file exists (and
         * self-expires it in case this process dies before the `finally` below
         * runs), so create it before touching any files and make sure PHP keeps
         * running even if the client triggering the update disconnects mid-request.
         *
         * The create is exclusive (fails if the file already exists) so two
         * updates triggered at the same time can't both extract into PATH_ROOT
         * concurrently - the loser is turned away instead of corrupting the
         * extraction. A leftover lock from a run that never reached the
         * `finally` below (the process was killed outright) is treated as
         * abandoned once it's older than LOCK_STALE_SECONDS.
         */
        $lockFile = Path::join(PATH_ROOT, self::LOCK_FILENAME);
        if ($this->filesystem->exists($lockFile) && (time() - (int) @filemtime($lockFile)) > self::LOCK_STALE_SECONDS) {
            $this->filesystem->remove($lockFile);
        }

        // fopen(..., 'x') rather than Filesystem here: it's the only way to make the
        // create atomic (it fails if the file already exists), which is what makes
        // this a real mutex against two updates racing each other below.
        $lockHandle = @fopen($lockFile, 'x');
        if ($lockHandle === false) {
            throw new InformationException('Another update appears to already be in progress. Please wait for it to finish before trying again.');
        }
        fclose($lockHandle);
        ignore_user_abort(true);

        /*
         * Do not finalize in this process. Once extraction completes, this
         * request is still running the old loaded code; the next request must
         * run the new code before constructing the session handler so database
         * patches and removed-file cleanup are applied safely.
         */
        try {
            // Extract latest version archive on top of the current version.
            try {
                $zip = new ZipFile();
                $zip->openFile($archiveFile);
                foreach ($zip->getListFiles() as $entryName) {
                    if (!self::isSafeArchiveEntry($entryName)) {
                        throw new BaseException('The update archive contains an unsafe file path and cannot be extracted.');
                    }
                }
                $zip->extractTo(PATH_ROOT);
                $zip->close();
            } catch (ZipException $e) {
                $this->di['logger']->withChannel('update')->error($e->getMessage());

                throw new BaseException('Failed to extract file, please check file and folder permissions. Further details are available in the error log.');
            }

            // Mark extraction complete so the short finalization handoff below
            // is not mistaken for an abandoned update.
            $this->filesystem->touch($lockFile);

            $finalization->createPendingState(Version::VERSION, $latestVersionNum, [
                'branch' => $updateBranch,
                'update_type' => $releaseInfo['update_type'] ?? Version::getUpdateType($latestVersionNum),
                'source' => 'auto-update',
            ]);
        } finally {
            $this->filesystem->remove($lockFile);
        }

        // Log off the current user and destroy the session.
        $this->di['session']->destroy('admin');
    }

    /**
     * Whether a release archive entry name is safe to extract under PATH_ROOT
     * (Zip Slip / CWE-22 guard).
     *
     * nelexa/zip normalizes entry names by splitting on '/' only, so on a
     * Windows host an entry such as `..\..\poc.php` isn't recognized as
     * traversal and extractTo() can write outside PATH_ROOT (CVE-2026-16767).
     * Normalizing backslashes to forward slashes before checking for '..'
     * segments and absolute paths closes that gap on every platform.
     */
    public static function isSafeArchiveEntry(string $entryName): bool
    {
        $normalized = str_replace('\\', '/', $entryName);

        if (str_starts_with($normalized, '/') || preg_match('#^[a-zA-Z]:#', $normalized)) {
            return false;
        }

        foreach (explode('/', $normalized) as $segment) {
            // Windows strips trailing spaces and periods from path components, so
            // '.. .' or '...' resolve to '..' at extraction time even though they
            // don't match it literally here. Reject any segment that is nothing
            // but dots and/or spaces rather than just the exact '..' segment.
            if ($segment !== '' && trim($segment, ' .') === '') {
                return false;
            }
        }

        return true;
    }
}

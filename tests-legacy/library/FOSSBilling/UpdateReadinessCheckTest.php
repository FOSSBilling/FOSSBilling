<?php

declare(strict_types=1);

namespace FOSSBilling;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class UpdateReadinessCheckTest extends TestCase
{
    private string $root;
    private string $dataDir;
    private string $configPath;
    private Filesystem $fs;
    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->fs = new Filesystem();

        // Build an isolated filesystem tree under sys_get_temp_dir() that
        // mirrors the real FOSSBilling layout, then point the checker at it.
        $this->root = Path::join(sys_get_temp_dir(), 'fossbilling_urc_' . bin2hex(random_bytes(8)));
        $this->dataDir = Path::join($this->root, 'data');
        $this->configPath = Path::join($this->root, 'config.php');

        $this->fs->mkdir([
            $this->root,
            $this->dataDir,
            Path::join($this->dataDir, 'cache'),
            Path::join($this->dataDir, 'log'),
            Path::join($this->dataDir, 'uploads'),
            Path::join($this->root, 'install'),
            Path::join($this->root, 'vendor'),
            Path::join($this->root, 'library'),
            Path::join($this->root, 'modules'),
            Path::join($this->root, 'themes'),
            Path::join($this->root, 'public'),
            Path::join($this->root, 'locale'),
        ]);

        $this->fs->dumpFile($this->configPath, "<?php\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        $this->restorePermissions();
        if ($this->fs->exists($this->root)) {
            $this->fs->remove($this->root);
        }
        $this->created = [];
    }

    public function testPassesOnHealthyTree(): void
    {
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertTrue($result['can_update'], 'Healthy tree should pass readiness check.');
        $this->assertSame([], $result['issues']);
    }

    public function testFailsWhenConfigFileIsNotWritable(): void
    {
        $this->makeUnwritable($this->configPath);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $this->assertIssueContainsPath($result['issues'], $this->configPath, 'file');
    }

    public function testFailsWhenCacheDirectoryIsNotWritable(): void
    {
        $cache = Path::join($this->dataDir, 'cache');
        $this->makeUnwritable($cache);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $this->assertIssueContainsPath($result['issues'], $cache, 'folder');
    }

    public function testFailsWhenVendorDirectoryIsNotWritable(): void
    {
        $vendor = Path::join($this->root, 'vendor');
        $this->makeUnwritable($vendor);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $this->assertIssueContainsPath($result['issues'], $vendor, 'folder');
    }

    public function testFailsWhenInstallDirectoryIsNotRemovable(): void
    {
        $install = Path::join($this->root, 'install');
        $this->makeUnwritable($install);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $this->assertIssueContainsPath($result['issues'], $install, 'folder');
    }

    public function testFailsWhenDataDirectoryIsMissing(): void
    {
        $this->restorePermissions();
        $this->fs->remove($this->dataDir);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $this->assertIssueContainsPath($result['issues'], $this->dataDir, 'folder');
    }

    public function testFailsWhenUpdateFinalizationFileIsMissingAndDataIsNotWritable(): void
    {
        $this->restorePermissions();
        $this->fs->remove(Path::join($this->dataDir, 'update-finalization.json'));
        $this->makeUnwritable($this->dataDir);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $this->assertIssueContainsPath($result['issues'], $this->dataDir, 'folder');
    }

    public function testReportsAllFailuresAtOnce(): void
    {
        $cache = Path::join($this->dataDir, 'cache');
        $vendor = Path::join($this->root, 'vendor');
        $this->makeUnwritable($this->configPath);
        $this->makeUnwritable($cache);
        $this->makeUnwritable($vendor);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $paths = array_column($result['issues'], 'path');
        $this->assertContains($this->configPath, $paths);
        $this->assertContains($cache, $paths);
        $this->assertContains($vendor, $paths);
    }

    public function testIssuePayloadShapeIsStable(): void
    {
        $cache = Path::join($this->dataDir, 'cache');
        $this->makeUnwritable($cache);
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertFalse($result['can_update']);
        $issue = $result['issues'][0];
        $this->assertSame(['path', 'type', 'reason', 'message'], array_keys($issue));
        $this->assertSame($cache, $issue['path']);
        $this->assertSame('folder', $issue['type']);
        $this->assertNotSame('', $issue['message']);
    }

    public function testIgnoresMissingInstallDirectoryWhenReporting(): void
    {
        $this->fs->remove(Path::join($this->root, 'install'));
        $check = new UpdateReadinessCheck($this->root, $this->dataDir, $this->configPath);

        $result = $check->check();

        $this->assertTrue($result['can_update'], 'A missing install/ directory must not be reported as an issue.');
    }

    private function makeUnwritable(string $path): void
    {
        // chmod mode bits are bypassed by the kernel for root, so the
        // "make it unwritable" simulation cannot reproduce the
        // non-root web server case when the test runner is root.
        if (getmyuid() === 0) {
            $this->markTestSkipped('Cannot simulate unwritable paths when running as root; chmod mode bits are ignored by the kernel.');
        }
        chmod($path, 0o500);
        $this->created[] = $path;
    }

    private function restorePermissions(): void
    {
        foreach ($this->created as $path) {
            if (file_exists($path)) {
                chmod($path, is_dir($path) ? 0o755 : 0o644);
            }
        }
        $this->created = [];
    }

    /**
     * @param list<array{path: string, type: string, reason: string, message: string}> $issues
     */
    private function assertIssueContainsPath(array $issues, string $path, string $type): void
    {
        foreach ($issues as $issue) {
            if ($issue['path'] === $path && $issue['type'] === $type) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail(sprintf('Expected an issue for path "%s" of type "%s", got: %s', $path, $type, json_encode(array_column($issues, 'path'))));
    }
}

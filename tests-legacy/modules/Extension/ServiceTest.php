<?php

declare(strict_types=1);

namespace Box\Mod\Extension;

use Box\Mod\Extension\Entity\Extension;
use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Box\Mod\Extension\Repository\ExtensionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

class PdoMock extends \PDO
{
    public function __construct()
    {
    }
}
class PdoStatmentsMock extends \PDOStatement
{
    public function __construct()
    {
    }
}

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;
    protected $filesystemMock;

    public function setUp(): void
    {
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->service = new Service($this->filesystemMock);
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testIsCoreModule(): void
    {
        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isCoreModule('extension');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testIsExtensionActiveModNotFound(): void
    {
        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('hasInstalledExtension')
            ->willReturn(false);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isExtensionActive('mod', 'ModDoesNotExists');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testRemoveNotExistingModules(): void
    {
        $extension = new Extension('mod', 'extensionName');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')->willThrowException(new \Exception());

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('findByType')
            ->willReturn([$extension]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->removeNotExistingModules();
        $this->assertIsInt($result);
        $this->assertTrue($result > 0);
    }

    public static function searchQueryData(): array
    {
        return [
            [[], 'SELECT e'],
            [['type' => 'mod'], 'SELECT e'],
            [['search' => 'FindUp'], 'SELECT e'],
        ];
    }

    #[DataProvider('searchQueryData')]
    public function testGetSearchQuery(array $data, string $expectedStr): void
    {
        $qbMock = $this->createMock(QueryBuilder::class);
        $qbMock->method('getDQL')->willReturn('SELECT e FROM Extension e WHERE e.status = :status');
        $qbMock->method('getParameters')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $di = $this->getDi();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        [$sql, $params] = $this->service->getSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
    }

    public function testGetExtensionsList(): void
    {
        $data = [
            'has_settings' => true,
            'active' => true,
        ];

        $extension = new Extension('mod', 'extensionName');
        $extension->setStatus(Extension::STATUS_INSTALLED);
        $extension->setVersion('1');

        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn([$extension]);

        $qbMock = $this->createMock(QueryBuilder::class);
        $qbMock->method('getQuery')->willReturn($queryMock);

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);
        $repoMock->expects($this->atLeastOnce())
            ->method('findByType')
            ->willReturn([]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testGetExtensionsListOnlyInstalled(): void
    {
        $data = [
            'installed' => true,
        ];

        $extension = new Extension('mod', 'extensionName');
        $extension->setStatus(Extension::STATUS_INSTALLED);
        $extension->setVersion('1');

        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn([$extension]);

        $qbMock = $this->createMock(QueryBuilder::class);
        $qbMock->method('getQuery')->willReturn($queryMock);

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);
        $repoMock->expects($this->atLeastOnce())
            ->method('findByType')
            ->willReturn([]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testGetAdminNavigation(): void
    {
        $extensionServiceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getConfig'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->willReturn(true);

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->method('findInstalledNamesByType')->willReturn([]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->method('getRepository')->willReturn($repoMock);

        $link = 'extension';

        $urlMock = $this->createMock('Box_Url');
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://fossbilling.org/index.php?_url=/' . $link);

        $di = $this->getDi();
        $di['mod'] = $di->protect(function ($name) use ($di) {
            $mod = new \FOSSBilling\Module($name);
            $mod->setDi($di);

            return $mod;
        });
        $di['tools'] = new \FOSSBilling\Tools();
        $di['mod_service'] = $di->protect(function ($mod) use ($extensionServiceMock, $staffServiceMock) {
            if ($mod == 'staff') {
                return $staffServiceMock;
            }

            return $extensionServiceMock;
        });
        $di['url'] = $urlMock;
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->getAdminNavigation(new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testFindExtension(): void
    {
        $extension = new Extension('mod', 'testExtension');

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('findOneByTypeAndName')
            ->willReturn($extension);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $di = $this->getDi();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->findExtension('mod', 'id');
        $this->assertInstanceOf(Extension::class, $result);
    }

    public function testUpdate(): void
    {
        $extension = new Extension('mod', 'testExtension');
        $extension->setVersion('2');

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(252);
        $this->expectExceptionMessage('Visit the extension directory for more information on updating this extension.');
        $this->service->update($extension);
    }

    public function testActivate(): void
    {
        $ext = new Extension('mod', 'testExtension');

        $expectedResult = [
            'id' => $ext->getName(),
            'type' => $ext->getType(),
            'redirect' => true,
            'has_settings' => true,
        ];

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn(['version' => '1']);
        $modMock->expects($this->atLeastOnce())
            ->method('hasAdminController')
            ->willReturn(true);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->activate($ext);
        $this->assertIsArray($result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testDeactivate(): void
    {
        $ext = new Extension('mod', 'extensionTest');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testDeactivateCoreModuleException(): void
    {
        $ext = new Extension('mod', 'extensionTest');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([$ext->getName()]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
        $this->service->deactivate($ext);
    }

    public function testDeactivateHookExtension(): void
    {
        $ext = new Extension('hook', 'extensionTest');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testDeactivateModule(): void
    {
        $ext = new Extension('mod', 'extensionTest');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testUninstall(): void
    {
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willReturn(true);

        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->method('hasInstalledExtension')->willReturn(false);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->method('getRepository')->willReturn($repoMock);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getExtensionPath'])
            ->setConstructorArgs([$this->filesystemMock])
            ->getMock();

        $tmpDir = sys_get_temp_dir() . '/fb_test_ext_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getExtensionPath')
            ->willReturn($tmpDir);

        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(function ($name) use ($staffService) {
            if ($name === 'Staff') {
                return $staffService;
            }

            return null;
        });

        $serviceMock->setDi($di);

        $this->filesystemMock->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        $result = $serviceMock->uninstall('mod', 'TestExtension');
        $this->assertTrue($result);

        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }

        $result = $serviceMock->uninstall('mod', 'Branding');
        $this->assertTrue($result);
    }

    public function testDownloadAndExtractDownloadUrlMissing(): void
    {
        $extensionMock = $this->createMock(\FOSSBilling\ExtensionManager::class);
        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionRelease')
            ->willReturn([]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Couldn\'t find a valid download URL for the extension.');
        $this->service->downloadAndExtract('mod', 'extensionId');
    }

    public function testGetInstalledMods(): void
    {
        $repoMock = $this->createMock(ExtensionRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('findInstalledNamesByType')
            ->willReturn([]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->getInstalledMods();
        $this->assertSame([], $result);
    }

    public function testActivateExistingExtension(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $extension = new Extension('extensionType', 'extensionId');
        $extension->setStatus(Extension::STATUS_DEACTIVATED);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['findExtension', 'activate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturnOnConsecutiveCalls(null, $extension);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->willReturn([]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $eventMock = $this->createMock(\Box_EventManager::class);
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->activateExistingExtension($data);
        $this->assertIsArray($result);
    }

    public function testActivateException(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $extension = new Extension('extensionType', 'extensionId');

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['findExtension', 'activate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn($extension);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->will($this->throwException(new \Exception()));

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $eventMock = $this->createMock(\Box_EventManager::class);
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['events_manager'] = $eventMock;

        $serviceMock->setDi($di);

        $this->expectException(\Exception::class);
        $serviceMock->activateExistingExtension($data);
    }

    public function testGetConfig(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $meta = new ExtensionMeta();
        $meta->setExtension('extensionName');
        $meta->setMetaKey('config');
        $meta->setMetaValue(null);

        $repoMock = $this->createMock(ExtensionMetaRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn($meta);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);

        $cryptMock = $this->createMock(\Box_Crypt::class);
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['crypt'] = $cryptMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);

        $result = $this->service->getConfig($data['ext']);
        $this->assertIsArray($result);
    }

    public function testGetConfigExtensionMetaNotFound(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $repoMock = $this->createMock(ExtensionMetaRepository::class);
        $repoMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn(null);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repoMock);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);
        $result = $this->service->getConfig($data['ext']);

        $this->assertIsArray($result);
        $this->assertEquals(['ext' => 'extensionName'], $result);
    }

    public function testSetConfig(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $meta = new ExtensionMeta();
        $meta->setExtension('extensionName');
        $meta->setMetaKey('config');

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getConfig', 'hasManagePermission'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('hasManagePermission');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);

        $cryptMock = $this->createMock(\Box_Crypt::class);
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt')
            ->willReturn('encryptedConfig');

        $metaRepoMock = $this->createMock(ExtensionMetaRepository::class);
        $metaRepoMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn($meta);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(ExtensionMeta::class)
            ->willReturn($metaRepoMock);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $eventMock = $this->createMock(\Box_EventManager::class);
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['tools'] = $toolsMock;
        $di['crypt'] = $cryptMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $serviceMock->setDi($di);
        $result = $serviceMock->setConfig($data);

        $this->assertTrue($result);
    }
}

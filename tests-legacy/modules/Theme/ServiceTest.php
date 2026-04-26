<?php

declare(strict_types=1);

namespace Box\Mod\Theme;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetTheme(): void
    {
        $result = $this->service->getTheme('huraga');
        $this->assertInstanceOf('\\' . Model\Theme::class, $result);
        $this->assertSame('huraga', $result->getName());
    }

    public function testGetCurrentThemePreset(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['setCurrentThemePreset'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');

        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn(null);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getCurrentPreset')
            ->willReturn('CurrentPresetString');

        $di = $this->getDi();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['em'] = $emMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->getCurrentThemePreset($themeMock);
        $this->assertIsString($result);
    }

    public function testSetCurrentThemePreset(): void
    {
        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn(null);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = $this->getDi();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->setCurrentThemePreset($themeMock, 'dark_blue');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDeletePreset(): void
    {
        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->exactly(2))
            ->method('deleteByExtensionAndScope');

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = $this->getDi();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->deletePreset($themeMock, 'dark_blue');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetThemePresets(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['updateSettings'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateSettings');

        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findByExtensionAndScope')
            ->willReturn([]);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $corePresets = [
            'default' => [],
            'red_black' => [],
        ];
        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetsFromSettingsDataFile')
            ->willReturn($corePresets);

        $di = $this->getDi();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['em'] = $emMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->getThemePresets($themeMock, 'dark_blue');
        $this->assertIsArray($result);

        $expected = [
            'default' => 'default',
            'red_black' => 'red_black',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetThemePresetsThemeDoNotHaveSettingsDataFile(): void
    {
        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findByExtensionAndScope')
            ->willReturn([]);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetsFromSettingsDataFile')
            ->willReturn([]);

        $di = $this->getDi();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->getThemePresets($themeMock);
        $this->assertIsArray($result);

        $expected = [
            'Default' => 'Default',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetThemeSettings(): void
    {
        $extensionMeta = (new \Box\Mod\Extension\Entity\ExtensionMeta())
            ->setMetaValue('{}');

        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn($extensionMeta);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = $this->getDi();

        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->getThemeSettings($themeMock, 'default');
        $this->assertIsArray($result);
    }

    public function testGetThemeSettingsWithEmptyPresets(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getCurrentThemePreset'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');

        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn(null);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');
        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetFromSettingsDataFile')
            ->willReturn([]);

        $di = $this->getDi();

        $di['em'] = $emMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->getThemeSettings($themeMock);
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testUpdateSettings(): void
    {
        $repositoryMock = $this->getMockBuilder(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByExtensionAndScope')
            ->willReturn(null);

        $emMock = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = $this->getDi();

        $di['em'] = $emMock;

        $this->service->setDi($di);
        $params = [];
        $result = $this->service->updateSettings($themeMock, 'default', $params);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testRegenerateThemeSettingsDataFile(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getThemePresets', 'getThemeSettings', 'getCurrentThemePreset'])
            ->getMock();

        $presets = [
            'default' => 'Defaults',
            'red_black' => 'Red Black',
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemePresets')
            ->willReturn($presets);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemeSettings')
            ->willReturn([]);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');

        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
        $tmpDir = sys_get_temp_dir() . '/fb_test_' . uniqid('', true);
        mkdir($tmpDir, 0o755, true);
        $testFile = $tmpDir . '/test_settings.json';

        $themeMock->expects($this->atLeastOnce())
            ->method('getPathSettingsDataFile')
            ->willReturn($testFile);

        $di = $this->getDi();

        $serviceMock->setDi($di);
        $result = $serviceMock->regenerateThemeSettingsDataFile($themeMock);

        // Clean up temp file
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testRegenerateThemeCssAndJsFilesEmptyFiles(): void
    {
        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('fb_test_assets_', true);
        mkdir($tmpDir, 0o755, true);

        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets')
            ->willReturn($tmpDir . '/');

        $di = $this->getDi();
        $this->service->setDi($di);

        $result = $this->service->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());

        // Clean up temp directory
        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetCurrentAdminAreaTheme(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn('');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getCurrentAdminAreaTheme();
        $this->assertIsArray($result);
    }

    public function testGetCurrentClientAreaTheme(): void
    {
        $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getCurrentClientAreaThemeCode', 'getTheme'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode');

        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);

        $result = $serviceMock->getCurrentClientAreaTheme();
        $this->assertInstanceOf('\\' . Model\Theme::class, $result);
    }

    public function testGetCurrentClientAreaThemeCode(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn('');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getCurrentClientAreaThemeCode();
        $this->assertIsString($result);
        $this->assertEquals('huraga', $result);
    }
}

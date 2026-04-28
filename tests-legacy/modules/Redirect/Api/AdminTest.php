<?php

declare(strict_types=1);

namespace Box\Mod\Redirect\Api;

use Box\Mod\Extension\Entity\ExtensionMeta;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testGetReturnsMappedRedirect(): void
    {
        $redirect = (new ExtensionMeta())
            ->setExtension('mod_redirect')
            ->setMetaKey('old-page')
            ->setMetaValue('new-page');

        $service = $this->createMock(\Box\Mod\Redirect\Service::class);
        $service->expects($this->once())
            ->method('get')
            ->with(3)
            ->willReturn($redirect);
        $service->expects($this->once())
            ->method('toApiArray')
            ->with($redirect)
            ->willReturn(['id' => 3, 'path' => 'old-page', 'target' => 'new-page']);

        $api = new Admin();
        $api->setService($service);

        $result = $api->get(['id' => 3]);
        $this->assertSame(['id' => 3, 'path' => 'old-page', 'target' => 'new-page'], $result);
    }

    public function testCreateDelegatesToService(): void
    {
        $service = $this->createMock(\Box\Mod\Redirect\Service::class);
        $service->expects($this->once())
            ->method('create')
            ->with('/old-page/', '/new-page/')
            ->willReturn(7);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('redirect', 'create_and_edit');

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffService,
            default => throw new \RuntimeException(sprintf('Unexpected mod service request: %s', $name)),
        });

        $api = new Admin();
        $api->setDi($di);
        $api->setService($service);

        $result = $api->create(['path' => '/old-page/', 'target' => '/new-page/']);
        $this->assertSame(7, $result);
    }

    public function testDeleteDelegatesToServiceEntity(): void
    {
        $redirect = (new ExtensionMeta())
            ->setExtension('mod_redirect')
            ->setMetaKey('old-page')
            ->setMetaValue('new-page');

        $service = $this->createMock(\Box\Mod\Redirect\Service::class);
        $service->expects($this->once())
            ->method('get')
            ->with(4)
            ->willReturn($redirect);
        $service->expects($this->once())
            ->method('delete')
            ->with($redirect)
            ->willReturn(true);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('redirect', 'delete');

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffService,
            default => throw new \RuntimeException(sprintf('Unexpected mod service request: %s', $name)),
        });

        $api = new Admin();
        $api->setDi($di);
        $api->setService($service);

        $this->assertTrue($api->delete(['id' => 4]));
    }
}

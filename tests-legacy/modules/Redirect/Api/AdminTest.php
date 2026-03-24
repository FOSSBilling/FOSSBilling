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

    public function testCreateSanitizesBeforeDelegating(): void
    {
        $service = $this->createMock(\Box\Mod\Redirect\Service::class);
        $service->expects($this->once())
            ->method('create')
            ->with('old-page', 'new-page')
            ->willReturn(7);

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();

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

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();

        $api = new Admin();
        $api->setDi($di);
        $api->setService($service);

        $this->assertTrue($api->delete(['id' => 4]));
    }
}

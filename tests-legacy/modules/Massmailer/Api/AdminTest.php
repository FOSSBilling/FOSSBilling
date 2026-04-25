<?php

declare(strict_types=1);

namespace Box\Mod\Massmailer\Api;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use FOSSBilling\InformationException;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testUpdateStoresNormalizedFilter(): void
    {
        $model = (new MassmailerMessage())
            ->setContent('content')
            ->setSubject('subject')
            ->setStatus(MassmailerMessage::STATUS_DRAFT);

        $service = new \Box\Mod\Massmailer\Service();
        $di = $this->createDi($model);
        $di['logger'] = new \Box_Log();
        $service->setDi($di);

        $this->api->setDi($di);
        $this->api->setService($service);

        $result = $this->api->update([
            'id' => 1,
            'filter' => [
                'client_status' => ['canceled', 'active', 'active'],
                'has_order_with_status' => ['suspended', 'active', 'active'],
            ],
        ]);

        $this->assertTrue($result);
        $this->assertSame('{"client_status":["active","canceled"],"has_order_with_status":["active","suspended"]}', $model->getFilter());
    }

    public function testUpdateRejectsInvalidFilter(): void
    {
        $model = (new MassmailerMessage())
            ->setContent('content')
            ->setSubject('subject')
            ->setStatus(MassmailerMessage::STATUS_DRAFT);

        $service = new \Box\Mod\Massmailer\Service();
        $di = $this->createDi($model, false);
        $di['logger'] = new \Box_Log();
        $service->setDi($di);

        $this->api->setDi($di);
        $this->api->setService($service);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "client_status"');

        $this->api->update([
            'id' => 1,
            'filter' => [
                'client_status' => ['active', 'not-valid'],
            ],
        ]);
    }

    private function createDi(MassmailerMessage $message, bool $expectFlush = true): \Pimple\Container
    {
        $di = $this->getDi();

        $repo = $this->getMockBuilder(MassmailerMessageRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($message);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(MassmailerMessage::class)
            ->willReturn($repo);

        if ($expectFlush) {
            $em->expects($this->once())
                ->method('flush');
        } else {
            $em->expects($this->never())
                ->method('flush');
        }

        $di['em'] = $em;

        return $di;
    }
}

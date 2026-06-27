<?php

declare(strict_types=1);

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use FOSSBilling\InformationException;

use function Tests\Helpers\moduleService;

function createMassmailerAdminDi(MassmailerMessage $message, bool $expectFlush = true): Pimple\Container
{
    $di = new Pimple\Container();

    $repo = Mockery::mock(MassmailerMessageRepository::class);
    $repo->shouldReceive('find')->with(1)->once()->andReturn($message);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(MassmailerMessage::class)->andReturn($repo);

    if ($expectFlush) {
        $em->shouldReceive('flush')->once();
    } else {
        $em->shouldNotReceive('flush');
    }

    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService());

    return $di;
}

test('update stores normalized filter', function (): void {
    $model = (new MassmailerMessage())
        ->setContent('content')
        ->setSubject('subject')
        ->setStatus(MassmailerMessage::STATUS_DRAFT);

    $service = new Box\Mod\Massmailer\Service();
    $di = createMassmailerAdminDi($model);
    $di['logger'] = new Box_Log();
    $service->setDi($di);

    $api = new Box\Mod\Massmailer\Api\Admin();
    $api->setDi($di);
    $api->setService($service);

    $result = $api->update([
        'id' => 1,
        'filter' => [
            'client_status' => ['canceled', 'active', 'active'],
            'has_order_with_status' => ['suspended', 'active', 'active'],
        ],
    ]);

    expect($result)->toBeTrue();
    expect($model->getFilter())->toBe('{"client_status":["active","canceled"],"has_order_with_status":["active","suspended"]}');
});

test('update rejects invalid filter', function (): void {
    $model = (new MassmailerMessage())
        ->setContent('content')
        ->setSubject('subject')
        ->setStatus(MassmailerMessage::STATUS_DRAFT);

    $service = new Box\Mod\Massmailer\Service();
    $di = createMassmailerAdminDi($model, false);
    $di['logger'] = new Box_Log();
    $service->setDi($di);

    $api = new Box\Mod\Massmailer\Api\Admin();
    $api->setDi($di);
    $api->setService($service);

    expect(fn (): bool => $api->update([
        'id' => 1,
        'filter' => ['client_status' => ['active', 'not-valid']],
    ]))->toThrow(InformationException::class, 'Mass mail filter contains invalid values for "client_status"');
});

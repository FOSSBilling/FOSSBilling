<?php

declare(strict_types=1);

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use FOSSBilling\InformationException;

use function Tests\Helpers\container;
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

    $api = apiEndpoint(new Box\Mod\Massmailer\Api\Admin());
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

test('get_test_client returns the configured test client email', function (): void {
    // Regression test: get_test_client() previously read $client->email directly off the
    // Client entity returned by ClientService::get(), which fatals since that property is
    // private (Client::getEmail() is the accessor) - see the sibling bug in
    // Massmailer\Service::sendMessage(), covered in ServiceTest.php.
    $client = (new Box\Mod\Client\Entity\Client())->setEmail('test-client@example.com');

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('get')->with(['id' => 5])->once()->andReturn($client);

    $modMock = Mockery::mock();
    $modMock->shouldReceive('getConfig')->andReturn(['test_client_id' => 5]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $clientService]));
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);

    $api = apiEndpoint(new Box\Mod\Massmailer\Api\Admin());
    $api->setDi($di);

    expect($api->get_test_client())->toBe('test-client@example.com');
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

    $api = apiEndpoint(new Box\Mod\Massmailer\Api\Admin());
    $api->setDi($di);
    $api->setService($service);

    expect(fn (): bool => $api->update([
        'id' => 1,
        'filter' => ['client_status' => ['active', 'not-valid']],
    ]))->toThrow(InformationException::class, 'Mass mail filter contains invalid values for "client_status"');
});

<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicedownloadable\Controller\Admin;
use Box\Mod\Servicedownloadable\Controller\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Tests\Helpers\container;

final class ServiceDownloadableControllerRouteTestApp extends Box_App
{
    public function __construct(private readonly string $area)
    {
        parent::__construct();
    }

    protected function init(): void
    {
        $controller = $this->area === 'client' ? new Client() : new Admin();
        $app = $this;
        $controller->register($app);
    }
}

test('download routes pass captured parameters to their controllers', function (
    string $area,
    string $path,
    string $apiKey,
    string $apiMethod,
    array $expectedData,
): void {
    $api = Mockery::mock();
    $api->shouldReceive($apiMethod)
        ->once()
        ->with($expectedData)
        ->andReturn(new Response('download'));

    $di = container();
    $di['request'] = Request::create('http://localhost' . $path);
    $di[$apiKey] = $api;
    $di['is_admin_logged'] = true;

    $app = new ServiceDownloadableControllerRouteTestApp($area);
    $app->setDi($di);
    $app->setUrl($path);

    $response = $app->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('download');
})->with([
    'client order file' => [
        'client',
        '/servicedownloadable/get-file/12/34',
        'api_client',
        'servicedownloadable_send_file',
        ['order_id' => '12', 'file_id' => '34'],
    ],
    'admin product file' => [
        'admin',
        '/servicedownloadable/get-file/12/0123456789abcdef0123456789abcdef',
        'api_admin',
        'servicedownloadable_send_file',
        ['id' => '12', 'file_id' => '0123456789abcdef0123456789abcdef'],
    ],
    'admin order file' => [
        'admin',
        '/servicedownloadable/order-file/12/34',
        'api_admin',
        'servicedownloadable_send_order_file',
        ['order_id' => '12', 'file_id' => '34'],
    ],
]);

<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\System\Service;
use Twig\Environment;

beforeEach(function (): void {
    $this->service = new Service();
});

test('getParamValue throws exception when key parameter is missing', function (): void {
    $param = [];
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Parameter key is missing');

    $this->service->getParamValue($param);
});

test('getCompany returns company information', function (): void {
    $expected = [
        'www' => SYSTEM_URL,
        'name' => 'Inc. Test',
        'email' => 'work@example.eu',
        'tel' => null,
        'signature' => null,
        'logo_url' => null,
        'logo_url_dark' => null,
        'favicon_url' => null,
        'address_1' => null,
        'address_2' => null,
        'address_3' => null,
        'account_number' => null,
        'bank_name' => null,
        'bic' => null,
        'display_bank_info' => null,
        'bank_info_pagebottom' => null,
        'number' => null,
        'note' => null,
        'privacy_policy' => null,
        'tos' => null,
        'vat_number' => null,
    ];

    $multParamsResults = [
        [
            'param' => 'company_name',
            'value' => 'Inc. Test',
        ],
        [
            'param' => 'company_email',
            'value' => 'work@example.eu',
        ],
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("getAll")->atLeast()->once()
        ->andReturn($multParamsResults);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getCompany();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('getLanguages returns available languages', function (): void {
    $result = $this->service->getLanguages(true);
    expect($result)->toBeArray();
});

test('getParams returns system parameters', function (): void {
    $expected = [
        'company_name' => 'Inc. Test',
        'company_email' => 'work@example.eu',
    ];
    $multParamsResults = [
        [
            'param' => 'company_name',
            'value' => 'Inc. Test',
        ],
        [
            'param' => 'company_email',
            'value' => 'work@example.eu',
        ],
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("getAll")->atLeast()->once()
        ->andReturn($multParamsResults);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getParams([]);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('updateParams updates system parameters', function (): void {
    $data = [
        'company_name' => 'newValue',
    ];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive("fire")->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $systemServiceMock = Mockery::mock(Service::class)->makePartial();
    $systemServiceMock->shouldReceive("setParamValue")->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = $logStub;

    $systemServiceMock->setDi($di);
    $result = $systemServiceMock->updateParams($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getMessages returns system messages', function (): void {
    $latestVersion = '1.0.0';
    $type = 'info';

    $filesystemMock = Mockery::mock(\Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->allows()->exists(Mockery::any())->andReturn(false);
    $systemServiceMock = Mockery::mock(new Service($filesystemMock))->makePartial();
    $systemServiceMock->allows()->getParamValue(Mockery::any())->andReturn(false);

    $updaterMock = Mockery::mock(\FOSSBilling\Update::class);
    $updaterMock->allows()->isUpdateAvailable()->andReturn(true);
    $updaterMock->allows()->getLatestVersion()->andReturn($latestVersion);

    $urlMock = Mockery::mock(\Box\Url::class);
    $urlMock->allows()->adminLink(Mockery::any())->andReturn('http://example.com');

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $queryBuilderMock = Mockery::mock(\Doctrine\DBAL\Query\QueryBuilder::class);
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $dbalMock->allows()->createQueryBuilder()->andReturn($queryBuilderMock);
    $queryBuilderMock->allows()->select(Mockery::any())->andReturnSelf();
    $queryBuilderMock->allows()->from(Mockery::any())->andReturnSelf();
    $queryBuilderMock->allows()->where(Mockery::any())->andReturnSelf();
    $queryBuilderMock->allows()->setParameter(Mockery::any(), Mockery::any())->andReturnSelf();
    $queryBuilderMock->allows()->executeQuery()->andReturn($resultMock);
    $resultMock->allows()->fetchOne()->andReturn(false);

    $di = container();
    $di['updater'] = $updaterMock;
    $di['url'] = $urlMock;
    $di['dbal'] = $dbalMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);

    $systemServiceMock->setDi($di);

    $result = $systemServiceMock->getMessages($type);
    expect($result)->toBeArray();
});

test('templateExists returns false when paths are empty', function (): void {
    $getThemeResults = ['paths' => []];
    $themeServiceMock = Mockery::mock(\Box\Mod\Theme\Service::class)->makePartial();
    $themeServiceMock->shouldReceive("getThemeConfig")->atLeast()->once()
        ->andReturn($getThemeResults);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $themeServiceMock);
    $this->service->setDi($di);

    $result = $this->service->templateExists('defaultFile.cp');
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('renderString throws exception on template error', function (): void {
    $vars = [
        '_client_id' => 1,
    ];

    $twigMock = Mockery::mock(Environment::class);
    $twigMock->shouldReceive("addGlobal")->atLeast()->once();
    $twigMock->shouldReceive('createTemplate')
        ->andThrow(new \Error('Error'));

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn(new \Model_Client());

    $di = container();
    $di['db'] = $dbMock;
    $di['twig'] = $twigMock;
    $di['api_client'] = new \Model_Client();
    $this->service->setDi($di);

    $this->expectException(\Error::class);
    $this->service->renderString('test', false, $vars);
});

test('renderString renders template string', function (): void {
    $vars = [
        '_client_id' => 1,
    ];

    $twigMock = Mockery::mock(Environment::class);

    $twigMock->shouldReceive("addGlobal")->atLeast()->once();
    $twigMock->shouldReceive('render')
        ->andReturn('');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn(new \Model_Client());

    $di = container();
    $di['db'] = $dbMock;
    $di['twig'] = $twigMock;
    $di['api_client'] = new \Model_Client();
    $this->service->setDi($di);

    $string = $this->service->renderString('test', true, $vars);
    expect($string)->toBe('test');
});

test('clearCache clears cache directory', function (): void {
    // Use a temporary directory for testing instead of PATH_CACHE
    $cacheDir = sys_get_temp_dir() . '/fossbilling_test_cache_' . uniqid();

    // Create cache directory with .gitkeep
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $gitkeepFile = $cacheDir . '/.gitkeep';
    file_put_contents($gitkeepFile, '');

    // Call clearCache with the temp directory
    $result = $this->service->clearCache($cacheDir);

    // Restore .gitkeep file after clearCache removes it
    file_put_contents($gitkeepFile, '');

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    // Cleanup temp directory
    if (is_dir($cacheDir)) {
        // Remove .gitkeep file first, then the directory
        if (file_exists($gitkeepFile)) {
            unlink($gitkeepFile);
        }
        rmdir($cacheDir);
    }
});

test('getPeriod returns period description', function (): void {
    $code = '1W';
    $expected = 'Every week';
    $result = $this->service->getPeriod($code);

    expect($result)->toBeString();
    expect($result)->toBe($expected);
});

test('getCountries returns list of countries', function (): void {
    $modMock = Mockery::mock('\\' . \FOSSBilling\Module::class);
    $modMock->shouldReceive("getConfig")->atLeast()->once()
        ->andReturn(['countries' => 'US']);

    $di = container();
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);

    $this->service->setDi($di);
    $result = $this->service->getCountries();
    expect($result)->toBeArray();
});

test('getEuCountries returns EU countries list', function (): void {
    $modMock = Mockery::mock('\\' . \FOSSBilling\Module::class);
    $modMock->shouldReceive("getConfig")->atLeast()->once()
        ->andReturn(['countries' => 'US']);

    $di = container();
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);

    $this->service->setDi($di);
    $result = $this->service->getEuCountries();
    expect($result)->toBeArray();
});

test('getStates returns list of states', function (): void {
    $result = $this->service->getStates();
    expect($result)->toBeArray();
});

test('getPhoneCodes returns phone codes', function (): void {
    $data = [];
    $result = $this->service->getPhoneCodes($data);
    expect($result)->toBeArray();
});

test('getVersion returns FOSSBilling version', function (): void {
    $result = $this->service->getVersion();
    expect($result)->toBeString();
    expect($result)->toBe(\FOSSBilling\Version::VERSION);
});

test('getPendingMessages returns pending messages from session', function (): void {
    $di = container();

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive("get")->atLeast()->once()
        ->with('pending_messages')
        ->andReturn([]);

    $di['session'] = $sessionMock;

    $this->service->setDi($di);
    $result = $this->service->getPendingMessages();
    expect($result)->toBeArray();
});

test('getPendingMessages returns empty array when session returns non-array', function (): void {
    $di = container();

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive("get")->atLeast()->once()
        ->with('pending_messages')
        ->andReturn(null);

    $di['session'] = $sessionMock;

    $this->service->setDi($di);
    $result = $this->service->getPendingMessages();
    expect($result)->toBeArray();
});

test('setPendingMessage adds message to pending messages', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive("getPendingMessages")->atLeast()->once()
        ->andReturn([]);

    $di = container();

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive("set")->atLeast()->once()
        ->with('pending_messages', Mockery::any());

    $di['session'] = $sessionMock;

    $serviceMock->setDi($di);

    $message = 'Important Message';
    $result = $serviceMock->setPendingMessage($message);
    expect($result)->toBeTrue();
});

test('clearPendingMessages clears pending messages', function (): void {
    $di = container();

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive("delete")->atLeast()->once()
        ->with('pending_messages');
    $di['session'] = $sessionMock;
    $this->service->setDi($di);
    $result = $this->service->clearPendingMessages();
    expect($result)->toBeTrue();
});

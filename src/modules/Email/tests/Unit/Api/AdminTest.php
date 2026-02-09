<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('email get list', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = new \Box\Mod\Email\Service();

    $willReturn = [
        'list' => [
            'id' => 1,
        ],
    ];

    $pager = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pager
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $di = container();
    $di['pager'] = $pager;

    $adminApi->setDi($di);
    $emailService->setDi($di);

    $service = $emailService;
    $adminApi->setService($service);

    $result = $adminApi->email_get_list([]);
    expect($result)->toBeArray();

    expect($result)->toHaveKey('list');
    expect($result['list'])->toBeArray();
});

test('email get', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];
    $id = 10;
    $client_id = 5;
    $sender = 'sender@example.com';
    $recipients = 'recipient@example.com';
    $subject = 'Subject';
    $content_html = 'HTML';
    $content_text = 'TEXT';
    $created = date('Y-m-d H:i:s', time() - 86400);
    $updated = date('Y-m-d H:i:s');

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;
    $model->client_id = $client_id;
    $model->sender = $sender;
    $model->recipients = $recipients;
    $model->subject = $subject;
    $model->content_html = $content_html;
    $model->content_text = $content_text;
    $model->created_at = $created;
    $model->updated_at = $updated;

    $expected = [
        'id' => $id,
        'client_id' => $client_id,
        'sender' => $sender,
        'recipients' => $recipients,
        'subject' => $subject,
        'content_html' => $content_html,
        'content_text' => $content_text,
        'created_at' => $created,
        'updated_at' => $updated,
    ];

    $service = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('getEmailById')
    ->atLeast()->once()
    ->andReturn($model);
    $service
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn($expected);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($service);

    $result = $adminApi->email_get($data);

    expect($result)->toBeArray();
    expect($expected)->toEqual($result);
});

test('send', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'to' => 'to@example.com',
        'to_name' => 'Recipient Name',
        'from' => 'from@example.com',
        'from_name' => 'Sender Name',
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('sendMail')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();

    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->send($data);

    expect($result)->toBeTrue();
});

test('resend', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $db = Mockery::mock('Box_Database');
    $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['db'] = $db;
    $adminApi->setDi($di);

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('resend')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->email_resend($data);

    expect($result)->toBeTrue();
});

test('resend exception email not found', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $db = Mockery::mock('Box_Database');
    $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(null);

    $di = container();
    $di['db'] = $db;
    $adminApi->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Email not found');
    $adminApi->email_resend($data);
});

test('delete exception email not found', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $db = Mockery::mock('Box_Database');
    $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(null);

    $di = container();
    $di['db'] = $db;
    $adminApi->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Email not found');
    $adminApi->email_delete($data);
});

test('email delete', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = new \Box\Mod\Email\Service();

    $data = [
        'id' => 1,
    ];

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $db = Mockery::mock('Box_Database');
    $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($model);
    $db
    ->shouldReceive('trash')
    ->atLeast()->once()
    ->andReturn(true);

    $loggerStub = $this->createStub('\Box_Log');

    $di = container();
    $di['db'] = $db;
    $di['logger'] = $loggerStub;
    $adminApi->setDi($di);

    $adminApi->setService($emailService);

    $result = $adminApi->email_delete($data);

    expect($result)->toBeTrue();
});

test('template get list', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = new \Box\Mod\Email\Service();

    $willReturn = [
        'list' => [
            [
                'id' => 1,
            ],
        ],
    ];

    $pager = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pager
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $di = container();
    $di['pager'] = $pager;

    $adminApi->setDi($di);
    $emailService->setDi($di);

    $service = $emailService;
    $adminApi->setService($service);

    $result = $adminApi->template_get_list([]);
    expect($result)->toBeArray();

    expect($result)->toHaveKey('list');
    expect($result['list'])->toBeArray();
});

test('template get', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = new \Model_EmailTemplate();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $db = Mockery::mock('\Box_Database');
    $db
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['db'] = $db;
    $adminApi->setDi($di);

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
    $adminApi->setService($emailService);

    $result = $adminApi->template_get($data);
    expect($result)->toBeArray();
});

test('template delete', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = new \Model_EmailTemplate();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $db = Mockery::mock('Box_Database');
    $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($model);
    $db
    ->shouldReceive('trash')
    ->atLeast()->once()
    ->andReturn(true);

    $loggerStub = $this->createStub('\Box_Log');

    $di = container();
    $di['db'] = $db;
    $di['logger'] = $loggerStub;
    $adminApi->setDi($di);

    $result = $adminApi->template_delete($data);
    expect($result)->toBeTrue();
});

test('template delete template not found', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $db = Mockery::mock('Box_Database');
    $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(null);

    $di = container();
    $di['db'] = $db;
    $adminApi->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Email template not found');
    $adminApi->template_delete($data);
});

test('template create', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $modelId = 1;

    $templateModel = new \Model_EmailTemplate();
    $templateModel->loadBean(new \Tests\Helpers\DummyBean());
    $templateModel->id = $modelId;

    $data = [
        'action_code' => 'Action_code',
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateCreate')
    ->atLeast()->once()
    ->andReturn($templateModel);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_create($data);
    expect($modelId)->toEqual($result);
});

test('template send to not set exception', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $di = container();
    $adminApi->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $adminApi->template_send(['code' => 'code']);
});

test('template update', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $id = 1;
    $data = [
        'id' => $id,
        'enabled' => '1',
        'category' => 'Category',
        'action_code' => 'Action_code',
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $emailTemplateModel = new \Model_EmailTemplate();
    $emailTemplateModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($emailTemplateModel);

    $di = container();
    $di['db'] = $dbMock;
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('updateTemplate')
    ->atLeast()->once()
        ->with($emailTemplateModel, $data['enabled'], $data['category'], $data['subject'], $data['content'])
    ->andReturn(true);
    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->template_update($data);
    expect($result)->toBeTrue();
});

test('template reset', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();

    $id = 1;
    $data = [
        'code' => 'CODE',
    ];

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('resetTemplateByCode')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_reset($data);
    expect($id)->toEqual($result);
});

test('batch template generate', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateBatchGenerate')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_generate();
    expect($result)->toBeTrue();
});

test('batch template disable', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateBatchDisable')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_disable([]);
    expect($result)->toBeTrue();
});

test('batch template enable', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateBatchEnable')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_enable([]);
    expect($result)->toBeTrue();
});

test('send test', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('sendTemplate')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->send_test([]);
    expect($result)->toBeTrue();
});

test('batch sendmail', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('batchSend')->atLeast()->once();

    $isExtensionActiveReturn = false;
    $extension = Mockery::mock(\Box\Mod\Extension\Service::class);
    $extension
    ->shouldReceive('isExtensionActive')
    ->atLeast()->once()
    ->andReturn($isExtensionActiveReturn);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extension);

    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->batch_sendmail();
    expect($result)->toBeNull();
});

test('template send', function () {
    $adminApi = new \Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('sendTemplate')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);

    $adminApi->setService($emailService);

    $data = [
        'code' => 'mod_email_test',
        'to' => 'example@example.com',
        'default_subject' => 'SUBJECT',
        'default_template' => 'TEMPLATE',
        'default_description' => 'DESCRIPTION',
    ];

    $result = $adminApi->template_send($data);
    expect($result)->toBeTrue();
});

test('template render', function () {
    $adminApi = Mockery::mock(\Box\Mod\Email\Api\Admin::class)->makePartial();
    $adminApi
    ->shouldReceive('template_get')
    ->atLeast()->once()
    ->andReturn(['vars' => [], 'content' => 'content']);

    $loader = new \Twig\Loader\ArrayLoader();
    $twigStub = $this->createStub(\Twig\Environment::class);

    $di = container();
    $di['twig'] = $twigStub;

    $systemService = Mockery::mock(\Box\Mod\System\Service::class)->makePartial();
    $systemService
    ->shouldReceive('renderString')
    ->atLeast()->once()
    ->andReturn('rendered');

    $di['mod_service'] = $di->protect(fn () => $systemService);

    $adminApi->setDi($di);

    $result = $adminApi->template_render(['id' => 5]);
    expect('rendered')->toEqual($result);
});

test('batch delete', function () {
    $activityMock = Mockery::mock(\Box\Mod\Email\Api\Admin::class)->makePartial();
    $activityMock->shouldReceive('email_delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

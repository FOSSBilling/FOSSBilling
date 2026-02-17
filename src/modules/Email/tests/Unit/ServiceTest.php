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

test('di returns dependency injection container', function (): void {
    $service = new \Box\Mod\Email\Service();

    $di = container();

    $service->setDi($di);

    $result = $service->getDi();
    expect($result)->toBe($di);
});

dataset('getSearchQueryProvider', function (): array {
    return [
        [
            [],
            'SELECT * FROM activity_client_email ORDER BY id DESC',
            [],
        ],
        [
            [
                'search' => 'search_query',
            ],
            'SELECT * FROM activity_client_email WHERE (sender LIKE :sender OR recipients LIKE :recipient OR subject LIKE :subject OR content_text LIKE :content_text OR content_html LIKE :content_html) ORDER BY id DESC',
            [
                ':sender' => '%search_query%',
                ':recipient' => '%search_query%',
                ':subject' => '%search_query%',
                ':content_text' => '%search_query%',
                ':content_html' => '%search_query%',
            ],
        ],
        [
            [
                'client_id' => 5,
            ],
            'SELECT * FROM activity_client_email WHERE client_id = :client_id ORDER BY id DESC',
            [
                ':client_id' => 5,
            ],
        ],
        [
            [
                'search' => 'search_query',
                'client_id' => 5,
            ],
            'SELECT * FROM activity_client_email WHERE (sender LIKE :sender OR recipients LIKE :recipient OR subject LIKE :subject OR content_text LIKE :content_text OR content_html LIKE :content_html) AND client_id = :client_id ORDER BY id DESC',
            [
                ':sender' => '%search_query%',
                ':recipient' => '%search_query%',
                ':subject' => '%search_query%',
                ':content_text' => '%search_query%',
                ':content_html' => '%search_query%',
                ':client_id' => 5,
            ],
        ],
    ];
});

test('getSearchQuery returns query and bindings', function (array $data, string $query, array $bindings): void {
    $service = new \Box\Mod\Email\Service();
    $di = container();

    $service->setDi($di);
    $result = $service->getSearchQuery($data);

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect($result[0])->toBe($query);
    expect($result[1])->toBe($bindings);
})->with('getSearchQueryProvider');

test('findOneForClientById returns email for client', function (): void {
    $service = new \Box\Mod\Email\Service();
    $di = container();
    $id = 5;
    $client_id = 1;

    $activityEmail = new \Model_ActivityClientEmail();
    $activityEmail->loadBean(new \Tests\Helpers\DummyBean());
    $activityEmail->client_id = $client_id;
    $activityEmail->id = $id;

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($activityEmail);

    $di['db'] = $db;
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = $client_id;

    $result = $service->findOneForClientById($client, $id);

    expect($result)->toBeInstanceOf('Model_ActivityClientEmail');
    expect($result->id)->not->toBeNull();
    expect($result->id)->toBe($activityEmail->id);
    expect($result->client_id)->toBe($activityEmail->client_id);
});

test('rmByClient removes emails for client', function (): void {
    $service = new \Box\Mod\Email\Service();
    $di = container();

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$model]);

    $db->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di['db'] = $db;
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $result = $service->rmByClient($client);
    expect($result)->toBeTrue();
});

test('rm removes email', function (): void {
    $service = new \Box\Mod\Email\Service();
    $di = container();

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di['db'] = $db;
    $service->setDi($di);

    $email = new \Model_ActivityClientEmail();
    $email->loadBean(new \Tests\Helpers\DummyBean());
    $email->id = 1;

    $result = $service->rm($email);
    expect($result)->toBeTrue();
});

test('toApiArray returns API array for email', function (): void {
    $service = new \Box\Mod\Email\Service();

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

    $result = $service->toApiArray($model);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('setVars encrypts and sets variables', function (): void {
    $service = new \Box\Mod\Email\Service();

    $di = container();
    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('store')->atLeast()->once();

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $di['db'] = $db;
    $di['crypt'] = $cryptMock;
    $service->setDi($di);

    $t = new \stdClass();
    $vars = [
        'param1' => 'value1',
    ];

    $result = $service->setVars($t, $vars);
    expect($result)->toBeTrue();
});

test('getVars decrypts and returns variables', function (): void {
    $service = new \Box\Mod\Email\Service();

    $di = container();
    $db = Mockery::mock('Box_Database');

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('decrypt')
        ->atLeast()->once()
        ->andReturn('{"param1":"value1"}');

    $expected = ['param1' => 'value1'];

    $di['db'] = $db;
    $di['crypt'] = $cryptMock;
    $service->setDi($di);

    $t = new \stdClass();
    $t->vars = 'haNUZYeNjo1oXhH6OkoKuHGPxakyKY10qR3O/DSy9Og=';

    $result = $service->getVars($t);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('sendTemplate returns false when template does not exist', function (): void {
    $service = new \Box\Mod\Email\Service();
    $di = container();

    $data = [
        'code' => 'mod_email_test_not_existing',
        'to' => 'example@example.com',
        'default_subject' => 'SUBJECT',
        'default_description' => 'DESCRIPTION',
    ];

    $emailTemplate = new \Model_EmailTemplate();
    $emailTemplate->loadBean(new \Tests\Helpers\DummyBean());

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);
    $db->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($emailTemplate);
    $db->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $di['db'] = $db;
    $di['crypt'] = $cryptMock;
    $di['api_admin'] = function () use ($di) {
        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);

        return $api;
    };

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();
    $di['validator'] = $validatorMock;
    $service->setDi($di);

    $result = $service->sendTemplate($data);

    expect($result)->toBeFalse();
});

test('sendTemplate sends email when template exists', function (): void {

    $data = [
        'code' => 'mod_email_test',
        'to' => 'example@example.com',
        'default_subject' => 'SUBJECT',
        'default_template' => 'TEMPLATE',
        'default_description' => 'DESCRIPTION',
    ];
    $service = new \Box\Mod\Email\Service();

    $di = container();

    $emailTemplate = new \Model_EmailTemplate();
    $emailTemplate->loadBean(new \Tests\Helpers\DummyBean());
    $emailTemplate->enabled = true;

    $queueModel = new \Model_ModEmailQueue();
    $queueModel->loadBean(new \Tests\Helpers\DummyBean());

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($emailTemplate);
    $db->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);
    $db->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($queueModel);

    $systemService = Mockery::mock(\Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('value');
    $systemService->shouldReceive('renderString')
        ->atLeast()->once()
        ->andReturn('rendered content');

    $twigStub = Mockery::mock(\Twig\Environment::class);

    $di['api_admin'] = function () use ($di) {
        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);

        return $api;
    };
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();
    $di['validator'] = $validatorMock;

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $modMock = Mockery::mock(\FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'from_name' => 'Test',
            'from_email' => 'test@test.com',
        ]);

    $di['db'] = $db;
    $di['crypt'] = $cryptMock;
    $di['twig'] = $twigStub;
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemService);
    $di['tools'] = new \FOSSBilling\Tools();

    $service->setDi($di);

    $result = $service->sendTemplate($data);

    expect($result)->toBeTrue();
});

dataset('sendTemplateExistsStaffProvider', function (): array {
    return [
        [
            [
                'code' => 'mod_email_test',
                'to' => 'example@example.com',
                'default_subject' => 'SUBJECT',
                'default_template' => 'TEMPLATE',
                'default_description' => 'DESCRIPTION',
                'to_staff' => 1,
            ],
            'never',
            'atLeastOnce',
        ],
        [
            [
                'code' => 'mod_email_test',
                'to' => 'example@example.com',
                'default_subject' => 'SUBJECT',
                'default_template' => 'TEMPLATE',
                'default_description' => 'DESCRIPTION',
                'to_client' => 1,
            ],
            'atLeastOnce',
            'never',
        ],
    ];
});

test('sendTemplate handles to_staff and to_client options', function (array $data, string $clientGetExpects, string $staffgetListExpects): void {

    $service = new \Box\Mod\Email\Service();

    $di = container();

    $emailTemplate = new \Model_EmailTemplate();
    $emailTemplate->loadBean(new \Tests\Helpers\DummyBean());
    $emailTemplate->enabled = true;

    $queueModel = new \Model_ModEmailQueue();
    $queueModel->loadBean(new \Tests\Helpers\DummyBean());

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);
    $db->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturnUsing(function ($type) use ($emailTemplate, $queueModel) {
            return $type === 'EmailTemplate' ? $emailTemplate : $queueModel;
        });
    $db->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $system = Mockery::mock(\Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('value');

    $system->shouldReceive('renderString')
        ->atLeast()->once()
        ->andReturn('value');

    $staffServiceMock = Mockery::mock(\Box\Mod\Staff\Service::class);
    if ($staffgetListExpects === 'atLeastOnce') {
        $staffServiceMock->shouldReceive('getList')
            ->atLeast()->once()
            ->andReturn([
                'list' => [
                    0 => [
                        'id' => 1,
                        'email' => 'staff@fossbilling.org',
                        'name' => 'George',
                    ],
                ],
            ]);
    } else {
        $staffServiceMock->shouldReceive('getList')->never();
    }

    $clientServiceMock = Mockery::mock(\Box\Mod\Client\Service::class);

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    if ($clientGetExpects === 'atLeastOnce') {
        $clientServiceMock->shouldReceive('get')
            ->atLeast()->once()
            ->andReturn($clientModel);
        $clientApiArray = [
            'id' => 1,
            'email' => 'staff@fossbilling.org',
            'first_name' => 'John',
            'last_name' => 'Smith',
        ];
        $clientServiceMock->shouldReceive('toApiArray')
            ->atLeast()->once()
            ->andReturn($clientApiArray);
    } else {
        $clientServiceMock->shouldReceive('get')->never();
        $clientServiceMock->shouldReceive('toApiArray')->never();
    }

    $twigStub = Mockery::mock(\Twig\Environment::class);

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $di['api_admin'] = function () use ($di) {
        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);

        return $api;
    };

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();
    $di['validator'] = $validatorMock;

    $modMock = Mockery::mock(\FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'from_name' => 'Test',
            'from_email' => 'test@test.com',
        ]);

    $di['mod'] = $di->protect(fn () => $modMock);
    $di['db'] = $db;
    $di['twig'] = $twigStub;
    $di['crypt'] = $cryptMock;
    $di['mod_service'] = $di->protect(function ($name) use ($system, $staffServiceMock, $clientServiceMock) {
        if ($name == 'staff') {
            return $staffServiceMock;
        } elseif ($name == 'System' || $name == 'system') {
            return $system;
        } elseif ($name == 'client') {
            return $clientServiceMock;
        }
    });
    $di['tools'] = new \FOSSBilling\Tools();

    $service->setDi($di);

    $result = $service->sendTemplate($data);

    expect($result)->toBeTrue();
})->with('sendTemplateExistsStaffProvider');

test('resend resends email', function (): void {

    $service = new \Box\Mod\Email\Service();

    $di = container();

    $emailSettings = [
        'mailer' => 'sendmail',
        'smtp_authentication' => 'login',
        'smtp_host' => null,
        'smtp_security' => null,
        'smtp_port' => null,
        'smtp_username' => null,
        'smtp_password' => null,
    ];

    $isExtensionActiveReturn = false;
    $extension = Mockery::mock(\Box\Mod\Extension\Service::class);
    $extension->shouldReceive('isExtensionActive')
        ->atLeast()->once()
        ->andReturn($isExtensionActiveReturn);

    $config = [];
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extension);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->client_id = 1;
    $model->sender = 'sender@exemple.com';
    $model->recipients = 'recipient@example.com';
    $model->subject = 'Email Title';
    $model->content_html = '<b>Content</b>';
    $model->content_text = 'Content';

    $result = $service->resend($model);

    expect($result)->toBeTrue();
});

dataset('templateGetSearchQueryProvider', function (): array {
    return [
        [
            [],
            'SELECT * FROM email_template ORDER BY category ASC',
            [],
        ],
        [
            [
                'search' => 'keyword',
            ],
            'SELECT * FROM email_template WHERE (action_code LIKE :action_code OR subject LIKE :subject OR content LIKE :content) ORDER BY category ASC',
            [
                ':action_code' => '%keyword%',
                ':subject' => '%keyword%',
                ':content' => '%keyword%',
            ],
        ],
        [
            [
                'search' => 'keyword',
                'code' => 'code',
            ],
            'SELECT * FROM email_template WHERE action_code LIKE :code AND (action_code LIKE :action_code OR subject LIKE :subject OR content LIKE :content) ORDER BY category ASC',
            [
                ':code' => '%code%',
                ':action_code' => '%keyword%',
                ':subject' => '%keyword%',
                ':content' => '%keyword%',
            ],
        ],
        [
            [
                'code' => 'code',
            ],
            'SELECT * FROM email_template WHERE action_code LIKE :code ORDER BY category ASC',
            [
                ':code' => '%code%',
            ],
        ],
    ];
});

test('templateGetSearchQuery returns query and bindings', function (array $data, string $query, array $bindings): void {
    $service = new \Box\Mod\Email\Service();
    $di = container();

    $service->setDi($di);
    $result = $service->templateGetSearchQuery($data);

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect($result[0])->toBe($query);
    expect($result[1])->toBe($bindings);
})->with('templateGetSearchQueryProvider');

test('templateToApiArray returns API array for template', function (): void {
    $id = 1;
    $action_code = 'code';
    $category = 'category';
    $enabled = 1;
    $subject = 'Subject';
    $description = 'Description';
    $content = 'content';

    $model = new \Model_EmailTemplate();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;
    $model->action_code = $action_code;
    $model->category = $category;
    $model->enabled = $enabled;
    $model->subject = $subject;
    $model->description = $description;
    $model->content = $content;

    $expected = [
        'id' => $id,
        'action_code' => $action_code,
        'category' => $category,
        'enabled' => $enabled,
        'subject' => $subject,
        'description' => $description,
    ];

    $service = new \Box\Mod\Email\Service();
    $result = $service->templateToApiArray($model);

    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('templateToApiArray returns deep array with vars', function (): void {
    $id = 1;
    $action_code = 'code';
    $category = 'category';
    $enabled = 1;
    $subject = 'Subject';
    $description = 'Description';
    $content = 'content';

    $model = new \Model_EmailTemplate();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;
    $model->action_code = $action_code;
    $model->category = $category;
    $model->enabled = $enabled;
    $model->subject = $subject;
    $model->description = $description;
    $model->content = $content;

    $expected = [
        'id' => $id,
        'action_code' => $action_code,
        'category' => $category,
        'enabled' => $enabled,
        'subject' => $subject,
        'description' => $description,
        'content' => $content,
        'vars' => [
            'param1' => 'value1',
        ],
    ];

    $serviceMock = Mockery::mock(\Box\Mod\Email\Service::class)->makePartial();
    $serviceMock->shouldReceive('getVars')
        ->atLeast()->once()
        ->andReturn(['param1' => 'value1']);

    $result = $serviceMock->templateToApiArray($model, true);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('vars');
    expect($result['vars'])->toBeArray();

    expect($result)->toBe($expected);
});

dataset('template_updateProvider', function (): array {
    return [
        [
            [
                'id' => 5,
                'enabled' => 1,
                'category' => 'Category',
                'subject' => null,
                'content' => null,
            ],
            'never',
        ],
        [
            [
                'id' => 5,
                'enabled' => 1,
                'category' => 'Category',
                'subject' => 'Subject',
                'content' => 'Content',
            ],
            'atLeastOnce',
        ],
    ];
});

test('updateTemplate updates template', function (array $data, string $templateRenderExpects): void {

    $id = 1;
    $model = new \Model_EmailTemplate();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;

    $emailService = new \Box\Mod\Email\Service();

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('store')
        ->atLeast()->once();

    $loggerStub = new \Tests\Helpers\TestLogger();

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('decrypt')
        ->atLeast()->once();
    $configMock = ['salt' => md5(random_bytes(13))];

    $twigStub = Mockery::mock(\Twig\Environment::class);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = $loggerStub;
    $di['crypt'] = $cryptMock;
    $di['config'] = $configMock;
    $di['twig'] = $twigStub;

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    if ($templateRenderExpects === 'atLeastOnce') {
        $systemServiceMock->shouldReceive('renderString')
            ->atLeast()->once();
    } else {
        $systemServiceMock->shouldReceive('renderString')->never();
    }

    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);

    $emailService->setDi($di);

    $templateModel = new \Model_EmailTemplate();
    $templateModel->loadBean(new \Tests\Helpers\DummyBean());

    $result = $emailService->updateTemplate($templateModel, $data['enabled'], $data['category'], $data['subject'], @$data['content']);
    expect($result)->toBeTrue();
})->with('template_updateProvider');

test('getEmailById returns email by ID', function (): void {
    $service = new \Box\Mod\Email\Service();

    $id = 1;
    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $db;
    $service->setDi($di);

    $result = $service->getEmailById($id);

    expect($result->id)->toBe($id);
});

test('getEmailById throws exception when email not found', function (): void {
    $service = new \Box\Mod\Email\Service();

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['db'] = $db;
    $service->setDi($di);

    expect(fn () => $service->getEmailById(5))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('templateCreate creates new template', function (): void {

    $service = new \Box\Mod\Email\Service();

    $id = 1;
    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($id);
    $emailTemplateModel = new \Model_EmailTemplate();
    $emailTemplateModel->loadBean(new \Tests\Helpers\DummyBean());
    $db->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($emailTemplateModel);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $data = [
        'action_code' => 'Action_code',
        'subject' => 'Subject',
        'content' => 'Content',
        'category' => 'category',
    ];

    $result = $service->templateCreate($data['action_code'], $data['subject'], $data['content'], 1, $data['category']);

    expect($result)->toBe($emailTemplateModel);
});

dataset('batchTemplateGenerateProvider', function (): array {
    return [
        [true, false, 'never', 'never'],
        [false, true, 'atLeastOnce', 'atLeastOnce'],
        [true, true, 'atLeastOnce', 'never'],
    ];
});

test('templateBatchGenerate generates templates in batch', function (bool $findOneReturn, bool $isExtensionActiveReturn, string $findOneExpects, string $dispenseExpects): void {

    $service = new \Box\Mod\Email\Service();

    $db = Mockery::mock('Box_Database');
    if ($findOneExpects === 'atLeastOnce') {
        $db->shouldReceive('findOne')
            ->atLeast()->once()
            ->andReturn($findOneReturn);
    } else {
        $db->shouldReceive('findOne')->never();
    }

    $emailTemplateModel = new \Model_EmailTemplate();
    $emailTemplateModel->loadBean(new \Tests\Helpers\DummyBean());
    if ($dispenseExpects === 'atLeastOnce') {
        $db->shouldReceive('dispense')
            ->atLeast()->once()
            ->andReturn($emailTemplateModel);
        $db->shouldReceive('store')
            ->atLeast()->once()
            ->andReturn(1);
    } else {
        $db->shouldReceive('dispense')->never();
    }

    $extension = Mockery::mock(\Box\Mod\Extension\Service::class);
    $extension->shouldReceive('isExtensionActive')
        ->atLeast()->once()
        ->andReturn($isExtensionActiveReturn);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extension);

    $service->setDi($di);

    $result = $service->templateBatchGenerate();

    expect($result)->toBeTrue();
})->with('batchTemplateGenerateProvider');

test('templateBatchDisable disables all templates', function (): void {

    $service = new \Box\Mod\Email\Service();

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('exec')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->templateBatchDisable();

    expect($result)->toBeTrue();
});

test('templateBatchEnable enables all templates', function (): void {

    $service = new \Box\Mod\Email\Service();

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('exec')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->templateBatchEnable();

    expect($result)->toBeTrue();
});

test('batchSend processes email queue', function (): void {

    $service = new \Box\Mod\Email\Service();

    $queueModel = new \Tests\Helpers\DummyBean();
    $queueModel->priority = 10;
    $queueModel->tries = 10;
    $queueModel->subject = 'subject';
    $queueModel->client_id = 1;
    $queueModel->sender = 'sender@example.com';
    $queueModel->recipient = 'receiver@example.com';
    $queueModel->content = 'content';
    $queueModel->from_name = 'From Name';
    $queueModel->to_name = 'To Name';

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findAll')
        ->once()
        ->andReturn([$queueModel]);
    $db->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $modMock = Mockery::mock(\FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'log_enabled' => 1,
            'cancel_after' => 1,
        ]);

    $extension = Mockery::mock(\Box\Mod\Extension\Service::class);
    $isExtensionActiveReturn = false;
    $extension->shouldReceive('isExtensionActive')
        ->atLeast()->once()
        ->andReturn($isExtensionActiveReturn);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(function ($name) use ($extension) {
        if ($name == 'extension') {
            return $extension;
        }
    });
    $di['mod'] = $di->protect(fn () => $modMock);

    $service->setDi($di);

    $result = $service->batchSend();

    expect($result)->toBeNull();
});

test('resetTemplateByCode resets template by code', function (): void {

    $service = new \Box\Mod\Email\Service();

    $templateModel = new \Model_EmailTemplate();
    $templateModel->loadBean(new \Tests\Helpers\DummyBean());
    $templateModel->id = 1;
    $templateModel->action_code = 'mod_email_test';

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($templateModel);
    $db->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('decrypt')
        ->atLeast()->once();
    $configMock = ['salt' => md5(random_bytes(13))];

    $twigStub = Mockery::mock(\Twig\Environment::class);

    $di = container();
    $di['db'] = $db;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['crypt'] = $cryptMock;
    $di['config'] = $configMock;
    $di['twig'] = $twigStub;

    $systemService = Mockery::mock(\Box\Mod\System\Service::class);
    $systemService->shouldReceive('renderString')
        ->atLeast()->once()
        ->andReturn('rendered content');

    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemService);

    $service->setDi($di);

    $result = $service->resetTemplateByCode('mod_email_test');

    expect($result)->toBeTrue();
});

test('resetTemplateByCode throws exception when template not found', function (): void {
    $service = new \Box\Mod\Email\Service();

    $db = Mockery::mock('Box_Database');
    $db->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['db'] = $db;
    $service->setDi($di);

    expect(fn () => $service->resetTemplateByCode('mod_email_test'))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('sendMail queues email for sending', function (): void {

    $dbMock = Mockery::mock('\Box_Database');

    $queueEmail = new \Model_ModEmailQueue();
    $queueEmail->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->with('ModEmailQueue')
        ->andReturn($queueEmail);

    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $di['logger'] = new \Tests\Helpers\TestLogger();
    $modMock = Mockery::mock('\stdClass');
    $extension = Mockery::mock(\Box\Mod\Extension\Service::class);
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(function ($name) use ($extension) {
        if ($name == 'system') {
        } elseif ($name == 'extension') {
            return $extension;
        }
    });

    $service = new \Box\Mod\Email\Service();
    $service->setDi($di);

    $to = 'receiver@example.com';
    $from = 'sender@example.com';
    $subject = 'Important message';
    $content = 'content';
    $result = $service->sendMail($to, $from, $subject, $content);
    expect($result)->toBeTrue();
});

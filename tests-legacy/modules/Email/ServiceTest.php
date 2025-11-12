<?php

namespace Box\Tests\Mod\Email;

class ServiceEmailTestDouble extends \Box\Mod\Email\Service
{
    public function template_render(...$args): string
    {
        return '';
    }
}

class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public static function getSearchQueryProvider(): array
    {
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
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getSearchQueryProvider')]
    public function testGetSearchQuery(array $data, string $query, array $bindings): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Pimple\Container();

        $service->setDi($di);
        $result = $service->getSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($result[0], $query);
        $this->assertEquals($result[1], $bindings);
    }

    public function testEmailFindOneForClientById(): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Pimple\Container();
        $id = 5;
        $client_id = 1;

        $activityEmail = new \Model_ActivityClientEmail();
        $activityEmail->loadBean(new \DummyBean());
        $activityEmail->client_id = $client_id;
        $activityEmail->id = $id;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($activityEmail);

        $di['db'] = $db;
        $service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = $client_id;

        $result = $service->findOneForClientById($client, $id);

        $this->assertInstanceOf('Model_ActivityClientEmail', $result);
        $this->assertNotNull($result->id);
        $this->assertEquals($result->id, $activityEmail->id);
        $this->assertEquals($result->client_id, $activityEmail->client_id);
    }

    public function testEmailRmByClient(): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Pimple\Container();

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$model]);

        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di['db'] = $db;
        $service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 1;

        $result = $service->rmByClient($client);
        $this->assertTrue($result);
    }

    public function testEmailRm(): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Pimple\Container();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di['db'] = $db;
        $service->setDi($di);

        $email = new \Model_ActivityClientEmail();
        $email->loadBean(new \DummyBean());
        $email->id = 1;

        $result = $service->rm($email);
        $this->assertTrue($result);
    }

    public function testEmailToApiArray(): void
    {
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
        $model->loadBean(new \DummyBean());
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
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testSetVars(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $service->setDi($di);

        $t = new \stdClass();
        $vars = [
            'param1' => 'value1',
        ];

        $result = $service->setVars($t, $vars);
        $this->assertTrue($result);
    }

    public function testGetVars(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt')
            ->willReturn('{"param1":"value1"}');

        $expected = ['param1' => 'value1'];

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $service->setDi($di);

        $t = new \stdClass();
        $t->vars = 'haNUZYeNjo1oXhH6OkoKuHGPxakyKY10qR3O/DSy9Og=';

        $result = $service->getVars($t);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testSendTemplateNotExists(): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Pimple\Container();

        $data = [
            'code' => 'mod_email_test_not_existing',
            'to' => 'example@example.com',
            'default_subject' => 'SUBJECT',
            'default_description' => 'DESCRIPTION',
        ];

        $emailTemplate = new \Model_EmailTemplate();
        $emailTemplate->loadBean(new \DummyBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($emailTemplate);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $service->setDi($di);

        $result = $service->sendTemplate($data);

        $this->assertFalse($result);
    }

    public function testSendTemplateExists(): void
    {
        $data = [
            'code' => 'mod_email_test',
            'to' => 'example@example.com',
            'default_subject' => 'SUBJECT',
            'default_template' => 'TEMPLATE',
            'default_description' => 'DESCRIPTION',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendMail'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di = new \Pimple\Container();

        $emailTemplate = new \Model_EmailTemplate();
        $emailTemplate->loadBean(new \DummyBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($emailTemplate);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $systemService = $this->getMockBuilder(\Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('value');

        $twig = $this->getMockBuilder('\\' . \Twig\Environment::class)->disableOriginalConstructor()->getMock();

        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $cryptMock = $this->getMockBuilder('\Box_Crypt')
            ->disableOriginalConstructor()
            ->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'from_name' => '',
                'from_email' => '',
            ]);

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $di['twig'] = $twig;
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['tools'] = new \FOSSBilling\Tools();

        $serviceMock->setDi($di);

        $result = $serviceMock->sendTemplate($data);

        $this->assertTrue($result);
    }

    public static function sendTemplateExistsStaffProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

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
                $self->never(),
                $self->atLeastOnce(),
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
                $self->atLeastOnce(),
                $self->never(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sendTemplateExistsStaffProvider')]
    public function testSendTemplateExistsStaff(array $data, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $clientGetExpects, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $staffgetListExpects): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendMail'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di = new \Pimple\Container();

        $emailTemplate = new \Model_EmailTemplate();
        $emailTemplate->loadBean(new \DummyBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($emailTemplate);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $system = $this->getMockBuilder(\Box\Mod\System\Service::class)->getMock();
        $system->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('value');

        $system->expects($this->atLeastOnce())
            ->method('renderString')
            ->willReturn('value');

        $staffServiceMock = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffServiceMock->expects($staffgetListExpects)
            ->method('getList')
            ->willReturn(
                [
                    'list' => [
                        0 => [
                            'id' => 1,
                            'email' => 'staff@fossbilling.org',
                            'name' => 'George',
                        ],
                    ],
                ]
            );

        $clientServiceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)->getMock();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientServiceMock->expects($clientGetExpects)
            ->method('get')
            ->willReturn($clientModel);
        $clientApiArray = [
            'id' => 1,
            'email' => 'staff@fossbilling.org',
            'first_name' => 'John',
            'last_name' => 'Smith',
        ];
        $clientServiceMock->expects($clientGetExpects)
            ->method('toApiArray')
            ->willReturn($clientApiArray);

        $loader = new \Twig\Loader\ArrayLoader();
        $twig = $this->getMockBuilder(\Twig\Environment::class)->setConstructorArgs([$loader, ['debug' => false]])->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')
            ->disableOriginalConstructor()
            ->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'from_name' => '',
                'from_email' => '',
            ]);

        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['db'] = $db;
        $di['twig'] = $twig;
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

        $serviceMock->setDi($di);

        $result = $serviceMock->sendTemplate($data);

        $this->assertTrue($result);
    }

    public function testResend(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $emailSettings = [
            'mailer' => 'sendmail',
            'smtp_authentication' => 'login',
            'smtp_host' => null,
            'smtp_security' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
        ];

        $di['db'] = $db;
        $isExtensionActiveReturn = false;
        $extension = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->getMock();
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn($isExtensionActiveReturn);

        $config = [];
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extension);
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
        $model->client_id = 1;
        $model->sender = 'sender@exemple.com';
        $model->recipients = 'recipient@example.com';
        $model->subject = 'Email Title';
        $model->content_html = '<b>Content</b>';
        $model->content_text = 'Content';

        $result = $service->resend($model);

        $this->assertTrue($result);
    }

    public static function templateGetSearchQueryProvider(): array
    {
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
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templateGetSearchQueryProvider')]
    public function testTemplateGetSearchQuery(array $data, string $query, array $bindings): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Pimple\Container();

        $service->setDi($di);
        $result = $service->templateGetSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($result[0], $query);
        $this->assertEquals($result[1], $bindings);
    }

    public function testTemplateToApiArray(): void
    {
        $id = 1;
        $action_code = 'code';
        $category = 'category';
        $enabled = 1;
        $subject = 'Subject';
        $description = 'Description';
        $content = 'content';

        $model = new \Model_EmailTemplate();
        $model->loadBean(new \DummyBean());
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

        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testTemplateToApiArrayDeep(): void
    {
        $id = 1;
        $action_code = 'code';
        $category = 'category';
        $enabled = 1;
        $subject = 'Subject';
        $description = 'Description';
        $content = 'content';

        $model = new \Model_EmailTemplate();
        $model->loadBean(new \DummyBean());
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

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->onlyMethods(['getVars'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getVars')
            ->willReturn(['param1' => 'value1']);

        $result = $serviceMock->templateToApiArray($model, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('vars', $result);
        $this->assertIsArray($result['vars']);

        $this->assertEquals($result, $expected);
    }

    public static function template_updateProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        return [
            [
                [
                    'id' => 5,
                    'enabled' => 1,
                    'category' => 'Category',
                    'subject' => null,
                    'content' => null,
                ],
                $self->never(),
            ],
            [
                [
                    'id' => 5,
                    'enabled' => 1,
                    'category' => 'Category',
                    'subject' => 'Subject',
                    'content' => 'Content',
                ],
                $self->atLeastOnce(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('template_updateProvider')]
    public function testTemplateUpdate(array $data, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $templateRenderExpects): void
    {
        $id = random_int(1, 100);
        $model = new \Model_EmailTemplate();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $emailServiceMock = $this->getMockBuilder(ServiceEmailTestDouble::class)->onlyMethods(['template_render'])->getMock();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store');

        $loggerMock = $this->getMockBuilder('Box_Log')->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');
        $configMock = ['salt' => md5(random_bytes(13))];

        $twigMock = $this->getMockBuilder(\Twig\Environment::class)->disableOriginalConstructor()->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $loggerMock;
        $di['crypt'] = $cryptMock;
        $di['config'] = $configMock;
        $di['twig'] = $twigMock;

        $systemServiceMock = $this->getMockBuilder(\Box\Mod\System\Service::class)->getMock();

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);

        $emailServiceMock->setDi($di);

        $templateModel = new \Model_EmailTemplate();
        $templateModel->loadBean(new \DummyBean());

        $result = $emailServiceMock->updateTemplate($templateModel, $data['enabled'], $data['category'], $data['subject'], @$data['content']);
        $this->assertEquals($result, true);
    }

    public function testGetEmailById(): void
    {
        $service = new \Box\Mod\Email\Service();

        $id = random_int(1, 100);
        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->getEmailById($id);

        $this->assertEquals($id, $result->id);
    }

    public function testGetEmailByIdException(): void
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->getEmailById(5);
    }

    public function testTemplateCreate(): void
    {
        $service = new \Box\Mod\Email\Service();

        $id = random_int(1, 100);
        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($id);
        $emailTemplateModel = new \Model_EmailTemplate();
        $emailTemplateModel->loadBean(new \DummyBean());
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($emailTemplateModel);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $data = [
            'action_code' => 'Action_code',
            'subject' => 'Subject',
            'content' => 'Content',
            'category' => 'category',
        ];

        $result = $service->templateCreate($data['action_code'], $data['subject'], $data['content'], 1, $data['category']);

        $this->assertEquals($emailTemplateModel, $result);
    }

    public static function batchTemplateGenerateProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        return [
            [true, false, $self->never(), $self->never()],
            [false, true, $self->atLeastOnce(), $self->atLeastOnce()],
            [true, true, $self->atLeastOnce(), $self->never()],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('batchTemplateGenerateProvider')]
    public function testBatchTemplateGenerate(bool $findOneReturn, bool $isExtensionActiveReturn, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $findOneExpects, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $dispenseExpects): void
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($findOneExpects)
            ->method('findOne')
            ->willReturn($findOneReturn);

        $emailTemplateModel = new \Model_EmailTemplate();
        $emailTemplateModel->loadBean(new \DummyBean());
        $db->expects($dispenseExpects)
            ->method('dispense')
            ->willReturn($emailTemplateModel);

        $extension = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->getMock();
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn($isExtensionActiveReturn);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extension);

        $service->setDi($di);

        $result = $service->templateBatchGenerate();

        $this->assertTrue($result);
    }

    public function testTemplateBatchDisable(): void
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('exec')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $result = $service->templateBatchDisable();

        $this->assertTrue($result, true);
    }

    public function testTemplateBatchEnable(): void
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('exec')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $result = $service->templateBatchEnable();

        $this->assertTrue($result, true);
    }

    public function testbatchSend(): void
    {
        $service = new \Box\Mod\Email\Service();

        $queueModel = new \DummyBean();
        $queueModel->priority = 10;
        $queueModel->tries = 10;
        $queueModel->subject = 'subject';
        $queueModel->client_id = 1;
        $queueModel->sender = 'sender@example.com';
        $queueModel->recipient = 'receiver@example.com';
        $queueModel->content = 'content';
        $queueModel->from_name = 'From Name';
        $queueModel->to_name = 'To Name';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->exactly(1))
            ->method('findAll')->willReturn([$queueModel]);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'log_enabled' => 1,
                'cancel_after' => 1,
            ]);

        $extension = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->getMock();
        $isExtensionActiveReturn = false;
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn($isExtensionActiveReturn);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(function ($name) use ($extension) {
            if ($name == 'extension') {
                return $extension;
            }
        });
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $service->setDi($di);

        $result = $service->batchSend();

        $this->assertNull($result);
    }

    public function testResetTemplateByCode(): void
    {
        $service = new \Box\Mod\Email\Service();

        $templateModel = new \Model_EmailTemplate();
        $templateModel->loadBean(new \DummyBean());
        $templateModel->id = random_int(1, 100);
        $templateModel->action_code = 'mod_email_test';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($templateModel);

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');
        $configMock = ['salt' => md5(random_bytes(13))];

        $twigMock = $this->getMockBuilder(\Twig\Environment::class)->disableOriginalConstructor()->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['crypt'] = $cryptMock;
        $di['config'] = $configMock;
        $di['twig'] = $twigMock;

        $systemService = $this->getMockBuilder(\Box\Mod\System\Service::class)->getMock();

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $service->setDi($di);

        $result = $service->resetTemplateByCode('mod_email_test');

        $this->assertTrue($result);
    }

    public function testResetTemplateByCodeException(): void
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->resetTemplateByCode('mod_email_test');
    }

    public function testsendMail(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $queueEmail = new \Model_ModEmailQueue();
        $queueEmail->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ModEmailQueue')
            ->willReturn($queueEmail);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $modMock = $this->getMockBuilder('\stdClass')->getMock();
        $extension = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->getMock();
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(function ($name) use ($extension) {
            if ($name == 'system') {
            } elseif ($name == 'extension') {
                return $extension;
            }
        });

        $service = new \Box\Mod\Email\Service();
        $service->setDi($di);

        // Queue the email
        $to = 'receiver@example.com';
        $from = 'sender@example.com';
        $subject = 'Important message';
        $content = 'content';
        $result = $service->sendMail($to, $from, $subject, $content);
        $this->assertTrue($result);

        // Send the email from queue
        // todo: solve this
    }
}

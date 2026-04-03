<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Email;

use Box\Mod\Email\Entity\EmailTemplate;
use Box\Mod\Email\Repository\EmailTemplateRepository;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

class ServiceEmailTestDouble extends \Box\Mod\Email\Service
{
    public function template_render(...$args): string
    {
        return '';
    }
}

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    private function createEmMock(?object $repositoryMock = null): object
    {
        if ($repositoryMock === null) {
            $repositoryMock = $this->getMockBuilder(EmailTemplateRepository::class)
                ->disableOriginalConstructor()
                ->getMock();
        }
        $emMock = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->any())
            ->method('getRepository')
            ->with(EmailTemplate::class)
            ->willReturn($repositoryMock);

        return $emMock;
    }

    private function setTemplateRepository(object $service, object $repository): void
    {
        $reflection = new \ReflectionClass($service);
        if ($reflection->hasProperty('templateRepository')) {
            $property = $reflection->getProperty('templateRepository');
            $property->setValue($service, $repository);
        }
    }

    private function createTemplateEntity(string $actionCode = 'mod_email_test'): EmailTemplate
    {
        return new EmailTemplate($actionCode);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testDi(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
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

    #[DataProvider('getSearchQueryProvider')]
    public function testGetSearchQuery(array $data, string $query, array $bindings): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = $this->getDi();

        $di['em'] = $this->createEmMock();
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
        $di = $this->getDi();
        $id = 5;
        $client_id = 1;

        $activityEmail = new \Model_ActivityClientEmail();
        $activityEmail->loadBean(new \DummyBean());
        $activityEmail->client_id = $client_id;
        $activityEmail->id = $id;

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($activityEmail);

        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
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
        $di = $this->getDi();

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$model]);

        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
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
        $di = $this->getDi();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
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

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $cryptMock = $this->createMock('\Box_Crypt');
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $di['em'] = $this->createEmMock();
        $service->setDi($di);

        $t = $this->createTemplateEntity();
        $vars = [
            'param1' => 'value1',
        ];

        $result = $service->setVars($t, $vars);
        $this->assertTrue($result);
    }

    public function testGetVars(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $cryptMock = $this->createMock('\Box_Crypt');
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt')
            ->willReturn('{"param1":"value1"}');

        $expected = ['param1' => 'value1'];

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $di['em'] = $this->createEmMock();
        $service->setDi($di);

        $t = $this->createTemplateEntity();
        $t->setVars('haNUZYeNjo1oXhH6OkoKuHGPxakyKY10qR3O/DSy9Og=');

        $result = $service->getVars($t);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testSendTemplateNotExists(): void
    {
        $service = new \Box\Mod\Email\Service();
        $di = $this->getDi();

        $data = [
            'code' => 'mod_email_test_not_existing',
            'to' => 'example@example.com',
            'default_subject' => 'SUBJECT',
            'default_description' => 'DESCRIPTION',
        ];

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->method('findOneByActionCode')->willReturn(null);

        $emMock = $this->createEmMock($repoMock);
        $emMock->expects($this->atLeastOnce())->method('persist');
        $emMock->expects($this->atLeastOnce())->method('flush');

        $cryptMock = $this->createMock('\Box_Crypt');
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db'] = $this->createMock('Box_Database');
        $di['crypt'] = $cryptMock;
        $di['em'] = $emMock;
        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
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
        $serviceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendMail'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di = $this->getDi();

        $emailTemplate = $this->createTemplateEntity('mod_email_test');
        $emailTemplate->setEnabled(true);
        $emailTemplate->setSubject('SUBJECT');
        $emailTemplate->setContent('TEMPLATE');

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->method('findOneByActionCode')->willReturn($emailTemplate);

        $emMock = $this->createEmMock($repoMock);

        $this->setTemplateRepository($serviceMock, $repoMock);

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('value');

        $twig = $this->getMockBuilder(\Twig\Environment::class)->disableOriginalConstructor()->getMock();

        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $cryptMock = $this->getMockBuilder('\Box_Crypt')
            ->disableOriginalConstructor()
            ->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'from_name' => '',
                'from_email' => '',
            ]);

        $di['db'] = $this->createMock('Box_Database');
        $di['crypt'] = $cryptMock;
        $di['em'] = $emMock;
        $di['twig'] = $twig;
        $di['mod'] = $di->protect(fn () => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['tools'] = new \FOSSBilling\Tools();

        $serviceMock->setDi($di);

        $result = $serviceMock->sendTemplate($data);

        $this->assertTrue($result);
    }

    public static function sendTemplateExistsStaffProvider(): array
    {
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
    }

    #[DataProvider('sendTemplateExistsStaffProvider')]
    public function testSendTemplateExistsStaff(array $data, string $clientGetExpects, string $staffgetListExpects): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendMail'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di = $this->getDi();

        $emailTemplate = $this->createTemplateEntity('mod_email_test');
        $emailTemplate->setEnabled(true);
        $emailTemplate->setSubject('SUBJECT');
        $emailTemplate->setContent('TEMPLATE');

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->method('findOneByActionCode')->willReturn($emailTemplate);

        $emMock = $this->createEmMock($repoMock);

        $this->setTemplateRepository($serviceMock, $repoMock);

        $system = $this->createMock(\Box\Mod\System\Service::class);
        $system->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('value');

        $system->expects($this->atLeastOnce())
            ->method('renderEmailTplString')
            ->willReturn('value');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->$staffgetListExpects())
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

        $clientServiceMock = $this->createMock(\Box\Mod\Client\Service::class);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientServiceMock->expects($this->$clientGetExpects())
            ->method('get')
            ->willReturn($clientModel);
        $clientApiArray = [
            'id' => 1,
            'email' => 'staff@fossbilling.org',
            'first_name' => 'John',
            'last_name' => 'Smith',
        ];
        $clientServiceMock->expects($this->$clientGetExpects())
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

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'from_name' => '',
                'from_email' => '',
            ]);

        $di['mod'] = $di->protect(fn () => $modMock);
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $emMock;
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

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
        $isExtensionActiveReturn = false;
        $extension = $this->createMock(\Box\Mod\Extension\Service::class);
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn($isExtensionActiveReturn);

        $config = [];
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extension);
        $di['logger'] = $this->createMock('Box_Log');

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

    public function testTemplateToApiArray(): void
    {
        $id = 1;
        $action_code = 'code';
        $category = 'category';
        $enabled = true;
        $subject = 'Subject';
        $description = 'Description';
        $content = 'content';

        $model = $this->createTemplateEntity($action_code);
        $this->setEntityId($model, $id);
        $model->setCategory($category);
        $model->setEnabled($enabled);
        $model->setSubject($subject);
        $model->setDescription($description);
        $model->setContent($content);
        $model->setIsOverridden(true);

        $expected = [
            'id' => $id,
            'action_code' => $action_code,
            'category' => $category,
            'enabled' => $enabled,
            'subject' => $subject,
            'description' => $description,
            'is_custom' => false,
            'has_default' => false,
            'is_overridden' => true,
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
        $enabled = true;
        $subject = 'Subject';
        $description = 'Description';
        $content = 'content';

        $model = $this->createTemplateEntity($action_code);
        $this->setEntityId($model, $id);
        $model->setCategory($category);
        $model->setEnabled($enabled);
        $model->setSubject($subject);
        $model->setDescription($description);
        $model->setContent($content);
        $model->setIsOverridden(true);

        $expected = [
            'id' => $id,
            'action_code' => $action_code,
            'category' => $category,
            'enabled' => $enabled,
            'subject' => $subject,
            'description' => $description,
            'is_custom' => false,
            'has_default' => false,
            'is_overridden' => true,
            'content' => $content,
            'vars' => [
                'param1' => 'value1',
            ],
            'subject_override' => $subject,
            'content_override' => $content,
        ];

        $serviceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['getVars'])->getMock();
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
    }

    #[DataProvider('template_updateProvider')]
    public function testTemplateUpdate(array $data, string $templateRenderExpects): void
    {
        $model = $this->createTemplateEntity('code');

        $emailService = new \Box\Mod\Email\Service();

        $loggerMock = $this->createMock('Box_Log');

        $cryptMock = $this->createMock('\Box_Crypt');
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');
        $configMock = ['salt' => md5(random_bytes(13))];

        $twigMock = $this->getMockBuilder(\Twig\Environment::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['logger'] = $loggerMock;
        $di['crypt'] = $cryptMock;
        $di['config'] = $configMock;
        $di['twig'] = $twigMock;
        $di['em'] = $this->createEmMock();

        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->$templateRenderExpects())
            ->method('renderEmailTplString');

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);

        $emailService->setDi($di);

        $templateModel = $this->createTemplateEntity('code');

        $result = $emailService->updateTemplate($templateModel, $data['enabled'], $data['category'], $data['subject'], @$data['content']);
        $this->assertTrue($result);
    }

    public function testGetEmailById(): void
    {
        $service = new \Box\Mod\Email\Service();

        $id = 1;
        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
        $service->setDi($di);

        $result = $service->getEmailById($id);

        $this->assertEquals($id, $result->id);
    }

    public function testGetEmailByIdException(): void
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->getEmailById(5);
    }

    public function testTemplateCreate(): void
    {
        $service = new \Box\Mod\Email\Service();

        $emMock = $this->createEmMock();
        $emMock->expects($this->atLeastOnce())->method('persist');
        $emMock->expects($this->atLeastOnce())->method('flush');

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $emMock;
        $di['logger'] = $this->createMock('Box_Log');
        $service->setDi($di);

        $data = [
            'action_code' => 'Action_code',
            'subject' => 'Subject',
            'content' => 'Content',
            'category' => 'category',
        ];

        $result = $service->templateCreate($data['action_code'], $data['subject'], $data['content'], 1, $data['category']);

        $this->assertInstanceOf(EmailTemplate::class, $result);
        $this->assertEquals($data['action_code'], $result->getActionCode());
    }

    public static function batchTemplateGenerateProvider(): array
    {
        return [
            [true, false, 'never', 'never'],
            [false, true, 'atLeastOnce', 'atLeastOnce'],
            [true, true, 'atLeastOnce', 'never'],
        ];
    }

    #[DataProvider('batchTemplateGenerateProvider')]
    public function testBatchTemplateGenerate(bool $findOneReturn, bool $isExtensionActiveReturn, string $findOneExpects, string $persistExpects): void
    {
        $service = new \Box\Mod\Email\Service();

        $existingTemplateModel = $this->createTemplateEntity('mod_email_test');
        $existingTemplateModel->setIsCustom(false);

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->$findOneExpects())
            ->method('findOneByActionCode')
            ->willReturn($findOneReturn ? $existingTemplateModel : null);

        $emMock = $this->createEmMock($repoMock);
        $emMock->expects($this->$persistExpects())->method('persist');
        $emMock->expects($this->any())->method('flush');

        $extension = $this->createMock(\Box\Mod\Extension\Service::class);
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn($isExtensionActiveReturn);

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $emMock;
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extension);

        $service->setDi($di);

        $result = $service->templateBatchGenerate();

        $this->assertTrue($result);
    }

    public function testTemplateBatchDisable(): void
    {
        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateBatchGenerate'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('templateBatchGenerate')
            ->willReturn(true);

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->atLeastOnce())
            ->method('setAllEnabled')
            ->with(false);

        $this->setTemplateRepository($service, $repoMock);

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $this->createEmMock($repoMock);
        $di['logger'] = $this->createMock('Box_Log');
        $service->setDi($di);

        $result = $service->templateBatchDisable();

        $this->assertTrue($result);
    }

    public function testTemplateBatchEnable(): void
    {
        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateBatchGenerate'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('templateBatchGenerate')
            ->willReturn(true);

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->atLeastOnce())
            ->method('setAllEnabled')
            ->with(true);

        $this->setTemplateRepository($service, $repoMock);

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $this->createEmMock($repoMock);
        $di['logger'] = $this->createMock('Box_Log');
        $service->setDi($di);

        $result = $service->templateBatchEnable();

        $this->assertTrue($result);
    }

    public function testGetTemplateDoesNotReEnableDisabledBuiltinTemplate(): void
    {
        $template = $this->createTemplateEntity('mod_support_ticket_open');
        $template->setEnabled(false);
        $template->setIsCustom(false);
        $template->setIsOverridden(false);
        $template->setCategory(null);
        $template->setDescription(null);
        $template->setSubject('Outdated subject');
        $template->setContent('Outdated content');

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($template);

        $di = $this->getDi();
        $di['em'] = $this->createEmMock($repoMock);

        $service = new \Box\Mod\Email\Service();
        $service->setDi($di);

        $result = $service->getTemplate(1);

        $this->assertSame($template, $result);
        $this->assertFalse($result->isEnabled());
        $this->assertNotNull($result->getCategory());
        $this->assertNotSame('Outdated subject', $result->getSubject());
        $this->assertNotSame('Outdated content', $result->getContent());
    }

    public function testGetTemplateListUsesDoctrinePagination(): void
    {
        $template = $this->createTemplateEntity('mod_email_test');
        $template->setCategory('email');
        $template->setEnabled(true);
        $template->setSubject('Subject');
        $template->setContent('Content');
        $template->setDescription('Description');

        $willReturn = [
            'total' => 1,
            'list' => [$template],
        ];

        $qbMock = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);

        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['paginateDoctrineQuery'])
            ->disableOriginalConstructor()
            ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('paginateDoctrineQuery')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['em'] = $this->createEmMock($repoMock);
        $di['pager'] = $pager;

        $service = new \Box\Mod\Email\Service();
        $service->setDi($di);

        $result = $service->getTemplateList([]);
        $this->assertIsArray($result);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['list']);
    }

    public function testGetTemplateListKeepsNormalizedDoctrineRows(): void
    {
        $willReturn = [
            'total' => 1,
            'list' => [[
                'id' => 1,
                'action_code' => 'mod_support_ticket_open',
                'category' => null,
                'enabled' => false,
                'subject' => null,
                'description' => null,
                'is_custom' => false,
                'is_overridden' => false,
            ]],
        ];

        $qbMock = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);

        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['paginateDoctrineQuery'])
            ->disableOriginalConstructor()
            ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('paginateDoctrineQuery')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['em'] = $this->createEmMock($repoMock);
        $di['pager'] = $pager;

        $service = new \Box\Mod\Email\Service();
        $service->setDi($di);

        $result = $service->getTemplateList([]);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['list']);
        $this->assertSame('mod_support_ticket_open', $result['list'][0]['action_code']);
        $this->assertTrue($result['list'][0]['has_default']);
        $this->assertNotSame('', $result['list'][0]['subject']);
        $this->assertNotNull($result['list'][0]['category']);
    }

    public function testBatchSend(): void
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

        $db = $this->createMock('Box_Database');
        $db->expects($this->exactly(1))
            ->method('findAll')->willReturn([$queueModel]);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'log_enabled' => 1,
                'cancel_after' => 1,
            ]);

        $extension = $this->createMock(\Box\Mod\Extension\Service::class);
        $isExtensionActiveReturn = false;
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn($isExtensionActiveReturn);

        $di = $this->getDi();
        $di['db'] = $db;
        $di['em'] = $this->createEmMock();
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(function ($name) use ($extension) {
            if ($name == 'extension') {
                return $extension;
            }
        });
        $di['mod'] = $di->protect(fn () => $modMock);

        $service->setDi($di);

        $result = $service->batchSend();

        $this->assertNull($result);
    }

    public function testResetTemplateByCode(): void
    {
        $service = new \Box\Mod\Email\Service();

        $templateEntity = $this->createTemplateEntity('mod_email_test');
        $templateEntity->setIsCustom(false);
        $templateEntity->setIsOverridden(true);

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->method('findOneByActionCode')->willReturn($templateEntity);

        $emMock = $this->createEmMock($repoMock);
        $emMock->expects($this->atLeastOnce())->method('flush');

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $emMock;
        $di['logger'] = $this->createMock('Box_Log');

        $service->setDi($di);

        $result = $service->resetTemplateByCode('mod_email_test');

        $this->assertTrue($result);
    }

    public function testResetTemplateByCodeException(): void
    {
        $service = new \Box\Mod\Email\Service();

        $di = $this->getDi();
        $di['em'] = $this->createEmMock();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->resetTemplateByCode('mod_email_missing');
    }

    public function testResetTemplateByCodeThrowsExceptionForCustomTemplate(): void
    {
        $service = new \Box\Mod\Email\Service();

        $templateEntity = $this->createTemplateEntity('mod_email_test');
        $templateEntity->setIsCustom(true);

        $repoMock = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->method('findOneByActionCode')->willReturn($templateEntity);

        $emMock = $this->createEmMock($repoMock);

        $di = $this->getDi();
        $di['db'] = $this->createMock('Box_Database');
        $di['em'] = $emMock;

        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Custom email template mod_email_test cannot be reset to a default');
        $service->resetTemplateByCode('mod_email_test');
    }

    public function testSendMail(): void
    {
        $dbMock = $this->createMock('\Box_Database');

        $queueEmail = new \Model_ModEmailQueue();
        $queueEmail->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ModEmailQueue')
            ->willReturn($queueEmail);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['em'] = $this->createEmMock();

        $di['logger'] = $this->createMock('Box_Log');
        $modMock = $this->createMock('\stdClass');
        $extension = $this->createMock(\Box\Mod\Extension\Service::class);
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
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
        $this->assertTrue($result);
    }
}

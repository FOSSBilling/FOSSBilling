<?php

namespace Box\Tests\Mod\Email\Api;

class Api_AdminTest extends \BBTestCase
{
    public function testEmailGetList(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = new \Box\Mod\Email\Service();

        $willReturn = [
            'list' => [
                'id' => 1,
            ],
        ];

        $pager = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = new \Pimple\Container();
        $di['pager'] = $pager;

        $adminApi->setDi($di);
        $emailService->setDi($di);

        $service = $emailService;
        $adminApi->setService($service);

        $result = $adminApi->email_get_list([]);
        $this->assertIsArray($result);

        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);
    }

    public function testEmailGet(): void
    {
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

        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['getEmailById', 'toApiArray'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getEmailById')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($expected);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->email_get($data);

        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testSend(): void
    {
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
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['sendMail'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di = new \Pimple\Container();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($emailService);

        $result = $adminApi->send($data);

        $this->assertTrue($result);
    }

    public function testResend(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);

        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['resend'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('resend')
            ->willReturn(true);

        $adminApi->setService($emailService);

        $result = $adminApi->email_resend($data);

        $this->assertTrue($result);
    }

    public function testResendExceptionEmailNotFound(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Email not found');
        $adminApi->email_resend($data);
    }

    public function testDeleteExceptionEmailNotFound(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Email not found');
        $adminApi->email_delete($data);
    }

    public function testEmailDelete(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = new \Box\Mod\Email\Service();

        $data = [
            'id' => 1,
        ];

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $loggerMock = $this->getMockBuilder('Box_Log')->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $loggerMock;
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $adminApi->setService($emailService);

        $result = $adminApi->email_delete($data);

        $this->assertTrue($result);
    }

    public function testTemplateGetList(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = new \Box\Mod\Email\Service();

        $willReturn = [
            'list' => [
                [
                    'id' => 1,
                ],
            ],
        ];

        $pager = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = new \Pimple\Container();
        $di['pager'] = $pager;

        $adminApi->setDi($di);
        $emailService->setDi($di);

        $service = $emailService;
        $adminApi->setService($service);

        $result = $adminApi->template_get_list([]);
        $this->assertIsArray($result);

        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);
    }

    public function testTemplateGet(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $model = new \Model_EmailTemplate();
        $model->loadBean(new \DummyBean());

        $db = $this->getMockBuilder('\Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateToApiArray'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateToApiArray')
            ->willReturn([]);
        $adminApi->setService($emailService);

        $result = $adminApi->template_get($data);
        $this->assertIsArray($result);
    }

    public function testTemplateDelete(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $model = new \Model_EmailTemplate();
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $loggerMock = $this->getMockBuilder('Box_Log')->getMock();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;
        $di['logger'] = $loggerMock;
        $adminApi->setDi($di);

        $result = $adminApi->template_delete($data);
        $this->assertTrue($result);
    }

    public function testtemplateDeleteTemplateNotFound(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Email template not found');
        $adminApi->template_delete($data);
    }

    public function testTemplateCreate(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $modelId = random_int(1, 100);

        $templateModel = new \Model_EmailTemplate();
        $templateModel->loadBean(new \DummyBean());
        $templateModel->id = $modelId;

        $data = [
            'action_code' => 'Action_code',
            'subject' => 'Subject',
            'content' => 'Content',
        ];

        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateCreate'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateCreate')
            ->willReturn($templateModel);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($emailService);

        $result = $adminApi->template_create($data);
        $this->assertEquals($result, $modelId);
    }

    public function testTemplateSendToNotSetException(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->template_send(['code' => 'code']);
    }

    public function testTemplateUpdate(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $id = random_int(1, 100);
        $data = [
            'id' => $id,
            'enabled' => '1',
            'category' => 'Category',
            'action_code' => 'Action_code',
            'subject' => 'Subject',
            'content' => 'Content',
        ];

        $emailTemplateModel = new \Model_EmailTemplate();
        $emailTemplateModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($emailTemplateModel);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['updateTemplate'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('updateTemplate')
            ->with($emailTemplateModel, $data['enabled'], $data['category'], $data['subject'], $data['content'])
            ->willReturn(true);
        $adminApi->setService($emailService);
        $adminApi->setDi($di);

        $result = $adminApi->template_update($data);
        $this->assertEquals($result, true);
    }

    public function testTemplateReset(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $id = random_int(1, 100);
        $data = [
            'code' => 'CODE',
        ];

        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['resetTemplateByCode'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('resetTemplateByCode')
            ->willReturn(true);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($emailService);

        $result = $adminApi->template_reset($data);
        $this->assertEquals($result, $id);
    }

    public function testBatchTemplateGenerate(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateBatchGenerate'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateBatchGenerate')
            ->willReturn(true);

        $adminApi->setService($emailService);

        $result = $adminApi->batch_template_generate();
        $this->assertTrue($result);
    }

    public function testBatchTemplateDisable(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateBatchDisable'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateBatchDisable')
            ->willReturn(true);

        $adminApi->setService($emailService);

        $result = $adminApi->batch_template_disable([]);
        $this->assertTrue($result);
    }

    public function testBatchTemplateEnable(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['templateBatchEnable'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateBatchEnable')
            ->willReturn(true);

        $adminApi->setService($emailService);

        $result = $adminApi->batch_template_enable([]);
        $this->assertTrue($result);
    }

    public function testSendTest(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['sendTemplate'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willReturn(true);

        $adminApi->setService($emailService);

        $result = $adminApi->send_test([]);
        $this->assertTrue($result);
    }

    public function testBatchSendmail(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['batchSend'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('batchSend');

        $isExtensionActiveReturn = false;
        $extension = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->getMock();
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->with('mod', 'demo')
            ->willReturn(false);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $extension);

        $adminApi->setService($emailService);
        $adminApi->setDi($di);

        $result = $adminApi->batch_sendmail();
        $this->assertNull($result);
    }

    public function testTemplateSend(): void
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['sendTemplate'])->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
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
        $this->assertTrue($result);
    }

    public function testTemplateRender(): void
    {
        $adminApi = $this->getMockBuilder(\Box\Mod\Email\Api\Admin::class)->onlyMethods(['template_get'])->getMock();
        $adminApi->expects($this->atLeastOnce())
            ->method('template_get')
            ->willReturn(['vars' => [], 'content' => 'content']);

        $loader = new \Twig\Loader\ArrayLoader();
        $twig = $this->getMockBuilder(\Twig\Environment::class)->setConstructorArgs([$loader, ['debug' => false]])->getMock();

        $di = new \Pimple\Container();
        $di['twig'] = $twig;

        $systemService = $this->getMockBuilder(\Box\Mod\System\Service::class)->onlyMethods(['renderString'])->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('renderString')
            ->willReturn('rendered');

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $adminApi->setDi($di);

        $result = $adminApi->template_render(['id' => 5]);
        $this->assertEquals($result, 'rendered');
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Api\Admin::class)->onlyMethods(['email_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('email_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }
}

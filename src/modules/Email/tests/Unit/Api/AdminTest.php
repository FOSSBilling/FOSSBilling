<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Email\Entity\EmailTemplate;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

test('email get list', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $willReturn = [
        'list' => [
            'id' => 1,
        ],
    ];

    $pager = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pager
        ->shouldReceive('paginateDoctrineQuery')
        ->atLeast()->once()
        ->andReturn($willReturn);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $repo->shouldReceive('getSearchQueryBuilder')->andReturn($qb);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $di = container();
    $di['pager'] = $pager;

    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->email_get_list([]);
    expect($result)->toBeArray();

    expect($result)->toHaveKey('list');
    expect($result['list'])->toBeArray();
});

test('email get', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

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

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, $id);
    $model->setClientId($client_id);
    $model->setSender($sender);
    $model->setRecipients($recipients);
    $model->setSubject($subject);
    $model->setContentHtml($content_html);
    $model->setContentText($content_text);
    $model->setCreatedAt(new DateTime('-1 day'));
    $model->setUpdatedAt(new DateTime());

    $expected = $model->toApiArray();

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneByIdOrFail')
        ->once()
        ->with($data['id'])
        ->andReturn($model);

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);
    $service->shouldNotReceive('toApiArray');

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($service);

    $result = $adminApi->email_get($data);

    expect($result)->toBeArray();
    expect($expected)->toEqual($result);
});

test('send', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'to' => 'to@example.com',
        'to_name' => 'Recipient Name',
        'from' => 'from@example.com',
        'from_name' => 'Sender Name',
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
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

test('resend', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, 1);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneByIdOrFail')
        ->atLeast()->once()
        ->with(1)
        ->andReturn($model);

    $di = container();
    $adminApi->setDi($di);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);
    $emailService
        ->shouldReceive('resend')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->email_resend($data);

    expect($result)->toBeTrue();
});

test('resend exception email not found', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneByIdOrFail')
        ->andThrow(new FOSSBilling\InformationException('Email not found'));

    $di = container();
    $adminApi->setDi($di);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);
    $adminApi->setService($emailService);

    $this->expectException(FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('Email not found');
    $adminApi->email_resend($data);
});

test('delete exception email not found', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneByIdOrFail')
        ->andThrow(new FOSSBilling\InformationException('Email not found'));

    $di = container();
    $adminApi->setDi($di);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);
    $adminApi->setService($emailService);

    $this->expectException(FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('Email not found');
    $adminApi->email_delete($data);
});

test('email delete', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, 1);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $loggerStub = $this->createStub('\Box_Log');

    $di = container();
    $di['em'] = $em;
    $di['logger'] = $loggerStub;
    $adminApi->setDi($di);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneByIdOrFail')
        ->atLeast()->once()
        ->with(1)
        ->andReturn($model);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $adminApi->setService($emailService);

    $result = $adminApi->email_delete($data);

    expect($result)->toBeTrue();
});

test('template get list', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $willReturn = [
        'list' => [
            [
                'id' => 1,
                'action_code' => 'mod_email_test',
            ],
        ],
    ];

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplateList')->atLeast()->once()->andReturn($willReturn);

    $di = container();

    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_get_list([]);
    expect($result)->toBeArray();

    expect($result)->toHaveKey('list');
    expect($result['list'])->toBeArray();
});

test('template get', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = new EmailTemplate('mod_email_test', 1);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with(1)->andReturn($model);
    $emailService
    ->shouldReceive('templateToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_get($data);
    expect($result)->toBeArray();
});

test('template delete', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $model = (new EmailTemplate('custom_email_test', 1))->setIsCustom(true);
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with(1)->andReturn($model);
    $emailService->shouldReceive('hasDefaultTemplate')->never();

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('deleteAssociationsForTemplate')->atLeast()->once()->with(1);
    $emailService->shouldReceive('getTemplateGroupRepository')->andReturn($templateGroupRepo);

    $loggerStub = $this->createStub('\Box_Log');
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('remove')->atLeast()->once()->with($model);
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = $loggerStub;
    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_delete($data);
    expect($result)->toBeTrue();
});

test('template delete template not found', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->andThrow(new FOSSBilling\Exception('Email template not found'));

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Email template not found');
    $adminApi->template_delete($data);
});

test('template create', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $modelId = 1;

    $templateModel = new EmailTemplate('Action_code', $modelId);

    $data = [
        'action_code' => 'Action_code',
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
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

test('template send to not set exception', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $di = container();
    $adminApi->setDi($di);
    $this->expectException(FOSSBilling\Exception::class);
    $adminApi->template_send(['code' => 'code']);
});

test('template update', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $id = 1;
    $data = [
        'id' => $id,
        'enabled' => '1',
        'category' => 'Category',
        'action_code' => 'Action_code',
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $emailTemplateModel = new EmailTemplate('Action_code', $id);

    $di = container();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with($id)->andReturn($emailTemplateModel);
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

test('template group get list returns assigned groups', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $id = 1;
    $emailTemplateModel = new EmailTemplate('mod_staff_client_order', $id);

    $group = new Box\Mod\Staff\Entity\AdminGroup();
    Tests\Helpers\setEntityId($group, 5);
    $group->setName('Support Staff');

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with($id)->andReturn($emailTemplateModel);
    $emailService->shouldReceive('getTemplateGroupIds')->atLeast()->once()->with($emailTemplateModel)->andReturn([5]);

    $adminGroupRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminGroupRepository::class);
    $adminGroupRepo->shouldReceive('findBy')->atLeast()->once()->with(['id' => [5]])->andReturn([$group]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('getAdminGroupRepository')->andReturn($adminGroupRepo);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['staff' => $staffService]));
    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->template_group_get_list(['id' => $id]);

    expect($result)->toBe([
        ['id' => 5, 'name' => 'Support Staff', 'protected' => false],
    ]);
});

test('template group get list returns empty array when no groups are assigned', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $id = 1;
    $emailTemplateModel = new EmailTemplate('mod_staff_client_order', $id);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with($id)->andReturn($emailTemplateModel);
    $emailService->shouldReceive('getTemplateGroupIds')->atLeast()->once()->andReturn([]);

    $di = container();
    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->template_group_get_list(['id' => $id]);

    expect($result)->toBe([]);
});

test('template group add assigns a group to a template', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $id = 1;
    $emailTemplateModel = new EmailTemplate('mod_staff_client_order', $id);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with($id)->andReturn($emailTemplateModel);
    $emailService->shouldReceive('addTemplateToGroup')->atLeast()->once()->with($emailTemplateModel, 5)->andReturn(true);

    $di = container();
    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->template_group_add(['id' => $id, 'group_id' => 5]);
    expect($result)->toBeTrue();
});

test('template group remove unassigns a group from a template', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $id = 1;
    $emailTemplateModel = new EmailTemplate('mod_staff_client_order', $id);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->with($id)->andReturn($emailTemplateModel);
    $emailService->shouldReceive('removeTemplateFromGroup')->atLeast()->once()->with($emailTemplateModel, 5)->andReturn(true);

    $di = container();
    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->template_group_remove(['id' => $id, 'group_id' => 5]);
    expect($result)->toBeTrue();
});

test('template reset', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $id = 1;
    $data = [
        'code' => 'CODE',
    ];

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('resetTemplateByCode')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_reset($data);
    expect($result)->toBeTrue();
});

test('batch template generate', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateBatchGenerate')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_generate();
    expect($result)->toBeTrue();
});

test('batch template disable', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateBatchDisable')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_disable([]);
    expect($result)->toBeTrue();
});

test('batch template enable', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('templateBatchEnable')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_enable([]);
    expect($result)->toBeTrue();
});

test('send test', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService
    ->shouldReceive('sendTemplate')
    ->atLeast()->once()
    ->andReturn(true);

    $adminApi->setService($emailService);

    $result = $adminApi->send_test([]);
    expect($result)->toBeTrue();
});

test('batch sendmail', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('batchSend')->atLeast()->once();

    $isExtensionActiveReturn = false;
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension
    ->shouldReceive('isExtensionActive')
    ->atLeast()->once()
    ->andReturn($isExtensionActiveReturn);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['extension' => $extension]));

    $adminApi->setService($emailService);
    $adminApi->setDi($di);

    $result = $adminApi->batch_sendmail();
    expect($result)->toBeNull();
});

test('template send', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();
    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
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

test('template render', function (): void {
    $adminApi = Mockery::mock(Box\Mod\Email\Api\Admin::class)->makePartial();

    $twigStub = $this->createStub(Twig\Environment::class);

    $di = container();
    $di['twig'] = $twigStub;

    $systemService = Mockery::mock(Box\Mod\System\Service::class)->makePartial();
    $systemService
    ->shouldReceive('renderEmailTplString')
    ->atLeast()->once()
    ->andReturn('rendered');

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->atLeast()->once()->andReturn(new EmailTemplate('mod_email_test', 5));
    $emailService->shouldReceive('templateToApiArray')->atLeast()->once()->andReturn(['vars' => [], 'content' => 'content']);

    $di['mod_service'] = $di->protect(moduleService(['system' => $systemService]));

    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->template_render(['id' => 5]);
    expect('rendered')->toEqual($result);
});

test('batch delete', function (): void {
    $activityMock = Mockery::mock(Box\Mod\Email\Api\Admin::class)->makePartial();
    $activityMock->shouldReceive('email_delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

test('batch template delete skips built-in templates with defaults', function (): void {
    $adminApi = new Box\Mod\Email\Api\Admin();

    $builtinTemplate = new EmailTemplate('mod_email_test', 1);
    $builtinTemplate->setIsCustom(false);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $emailService->shouldReceive('getTemplate')->once()->andReturn($builtinTemplate);
    $emailService->shouldReceive('hasDefaultTemplate')->once()->andReturn(true);

    $emMock = Mockery::mock();
    $emMock->shouldReceive('flush')->once();

    $staffService = Mockery::mock();
    $staffService->shouldReceive('checkPermissionsAndThrowException')->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($emailService, $staffService) {
        if ($name === 'email') {
            return $emailService;
        }
        if ($name === 'Staff') {
            return $staffService;
        }
    });
    $di['em'] = $emMock;

    $adminApi->setDi($di);
    $adminApi->setService($emailService);

    $result = $adminApi->batch_template_delete(['ids' => [1]]);
    expect($result)->toBeTrue();
});

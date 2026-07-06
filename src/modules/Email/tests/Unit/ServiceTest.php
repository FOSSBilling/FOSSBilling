<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Email\Entity\EmailTemplate;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

function emailTemplate(string $actionCode = '', ?int $id = null, array $data = []): EmailTemplate
{
    $template = new EmailTemplate($actionCode, $id);

    if (array_key_exists('category', $data)) {
        $template->setCategory($data['category']);
    }

    if (array_key_exists('enabled', $data)) {
        $template->setEnabled((bool) $data['enabled']);
    }

    if (array_key_exists('subject', $data)) {
        $template->setSubject($data['subject']);
    }

    if (array_key_exists('content', $data)) {
        $template->setContent($data['content']);
    }

    if (array_key_exists('description', $data)) {
        $template->setDescription($data['description']);
    }

    if (array_key_exists('vars', $data)) {
        $template->setVars($data['vars']);
    }

    if (array_key_exists('is_custom', $data)) {
        $template->setIsCustom((bool) $data['is_custom']);
    }

    if (array_key_exists('is_overridden', $data)) {
        $template->setIsOverridden((bool) $data['is_overridden']);
    }

    if (array_key_exists('last_error', $data)) {
        $template->setLastError($data['last_error']);
    }

    if (array_key_exists('error_checked_at', $data)) {
        $template->setErrorCheckedAt($data['error_checked_at']);
    }

    return $template;
}

/**
 * Build a partial Mockery EntityManager that returns the correct repository mock
 * for each Email entity class requested. Used because the service eagerly fetches
 * all repositories inside setDi().
 *
 * @return Doctrine\ORM\EntityManagerInterface&Mockery\MockInterface
 */
function emailBuildEm(
    ?Box\Mod\Email\Repository\ActivityClientEmailRepository $activityRepo = null,
    ?Box\Mod\Email\Repository\EmailTemplateRepository $templateRepo = null,
    ?Box\Mod\Email\Repository\QueuedEmailRepository $queueRepo = null,
    bool $ignoreMissing = true,
    ?Box\Mod\Email\Repository\EmailTemplateGroupRepository $templateGroupRepo = null,
) {
    $activityRepo ??= Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class)->shouldIgnoreMissing();
    $templateRepo ??= Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class)->shouldIgnoreMissing();
    $queueRepo ??= Mockery::mock(Box\Mod\Email\Repository\QueuedEmailRepository::class)->shouldIgnoreMissing();
    $templateGroupRepo ??= Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class)->shouldIgnoreMissing();

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    if ($ignoreMissing) {
        $em->shouldIgnoreMissing();
    }
    $em->shouldReceive('getRepository')->andReturnUsing(static fn (string $class): object => match ($class) {
        Box\Mod\Email\Entity\ActivityClientEmail::class => $activityRepo,
        EmailTemplate::class => $templateRepo,
        Box\Mod\Email\Entity\QueuedEmail::class => $queueRepo,
        Box\Mod\Email\Entity\EmailTemplateGroup::class => $templateGroupRepo,
        default => $activityRepo,
    });

    return $em;
}

test('di returns dependency injection container', function (): void {
    $service = new Box\Mod\Email\Service();

    $di = container();

    $service->setDi($di);

    $result = $service->getDi();
    expect($result)->toBe($di);
});

test('rmByClient removes emails for client', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('deleteByClientId')
        ->once()
        ->with(1)
        ->andReturn(3);

    $di['em'] = emailBuildEm($repo);
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $result = $service->rmByClient($client);
    expect($result)->toBeTrue();
});

test('ActivityClientEmail toApiArray returns sanitized API array', function (): void {
    $id = 10;
    $client_id = 5;
    $sender = 'sender@example.com';
    $recipients = 'recipient@example.com';
    $subject = 'Subject';
    $content_html = '<script>alert("x")</script><p>HTML</p>';
    $content_text = 'TEXT';
    $created = new DateTime('-1 day');
    $updated = new DateTime();

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, $id);
    $model->setClientId($client_id);
    $model->setSender($sender);
    $model->setRecipients($recipients);
    $model->setSubject($subject);
    $model->setContentHtml($content_html);
    $model->setContentText($content_text);
    $model->setCreatedAt($created);
    $model->setUpdatedAt($updated);

    $expected = [
        'id' => $id,
        'client_id' => $client_id,
        'sender' => $sender,
        'recipients' => $recipients,
        'subject' => $subject,
        'content_html' => FOSSBilling\Tools::sanitizeContent($content_html),
        'content_text' => $content_text,
        'has_attachment' => false,
        'created_at' => $created->format('Y-m-d H:i:s'),
        'updated_at' => $updated->format('Y-m-d H:i:s'),
    ];

    $result = $model->toApiArray();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('setVars encrypts and sets variables', function (): void {
    $service = new Box\Mod\Email\Service();

    $di = container();
    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once()
        ->andReturn('encrypted-vars');

    $em = emailBuildEm();
    $em->shouldReceive('flush')->atLeast()->once();

    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $service->setDi($di);

    $t = emailTemplate();
    $vars = [
        'param1' => 'value1',
    ];

    $result = $service->setVars($t, $vars);
    expect($result)->toBeTrue();
});

test('getVars decrypts and returns variables', function (): void {
    $service = new Box\Mod\Email\Service();

    $di = container();
    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('decrypt')
        ->atLeast()->once()
        ->andReturn('{"param1":"value1"}');

    $expected = ['param1' => 'value1'];

    $di['crypt'] = $cryptMock;
    $service->setDi($di);

    $t = emailTemplate(data: ['vars' => 'haNUZYeNjo1oXhH6OkoKuHGPxakyKY10qR3O/DSy9Og=']);

    $result = $service->getVars($t);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('sendTemplate returns false when template does not exist', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $data = [
        'code' => 'mod_email_test_not_existing',
        'to' => 'example@example.com',
        'default_subject' => 'SUBJECT',
        'default_description' => 'DESCRIPTION',
    ];

    $emailTemplate = emailTemplate();

    $templateRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $templateRepo->shouldReceive('findOneByActionCode')->andReturn(null);

    $em = emailBuildEm(null, $templateRepo);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $di['api_admin'] = function () use ($di) {
        $api = new FOSSBilling\Api\Proxy(new Model_Admin());
        $api->setDi($di);

        return $api;
    };

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
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
    $service = new Box\Mod\Email\Service();

    $di = container();

    $emailTemplate = emailTemplate(data: ['enabled' => true]);

    $templateRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $templateRepo->shouldReceive('findOneByActionCode')->andReturn($emailTemplate);

    $em = emailBuildEm(null, $templateRepo);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('value');
    $systemService->shouldReceive('renderEmailTplString')
        ->atLeast()->once()
        ->andReturn('rendered content');

    $twigStub = Mockery::mock(Twig\Environment::class);

    $di['api_admin'] = function () use ($di) {
        $api = new FOSSBilling\Api\Proxy(new Model_Admin());
        $api->setDi($di);

        return $api;
    };
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();
    $di['validator'] = $validatorMock;

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $modMock = Mockery::mock(FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'from_name' => 'Test',
            'from_email' => 'test@test.com',
        ]);

    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $di['twig'] = $twigStub;
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['mod_service'] = $di->protect(moduleService(['system' => $systemService]));
    $di['tools'] = new FOSSBilling\Tools();

    $service->setDi($di);

    $result = $service->sendTemplate($data);

    expect($result)->toBeTrue();
});

test('sendTemplate forwards the attachment to the queue and strips it from the stored vars', function (): void {
    $data = [
        'code' => 'mod_email_test',
        'to' => 'example@example.com',
        'default_subject' => 'SUBJECT',
        'default_template' => 'TEMPLATE',
        'default_description' => 'DESCRIPTION',
        'attachment' => [
            'content' => '%PDF-1.4 fake invoice contents',
            'name' => 'Invoice-BB0001.pdf',
            'mime' => 'application/pdf',
        ],
    ];
    $service = new Box\Mod\Email\Service();

    $di = container();

    $emailTemplate = emailTemplate(data: ['enabled' => true]);

    $templateRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $templateRepo->shouldReceive('findOneByActionCode')->andReturn($emailTemplate);

    /** @var Box\Mod\Email\Entity\QueuedEmail|null $persistedQueue */
    $persistedQueue = null;
    $em = emailBuildEm(null, $templateRepo);
    $em->shouldReceive('persist')
        ->atLeast()->once()
        ->with(Mockery::on(function ($entity) use (&$persistedQueue): bool {
            if ($entity instanceof Box\Mod\Email\Entity\QueuedEmail) {
                $persistedQueue = $entity;
            }

            return true;
        }));
    $em->shouldReceive('flush')->atLeast()->once();

    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('value');
    $systemService->shouldReceive('renderEmailTplString')
        ->atLeast()->once()
        ->andReturn('rendered content');

    $di['api_admin'] = function () use ($di) {
        $api = new FOSSBilling\Api\Proxy(new Model_Admin());
        $api->setDi($di);

        return $api;
    };
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();
    $di['validator'] = $validatorMock;

    $encryptedVars = null;
    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once()
        ->with(Mockery::on(function ($json) use (&$encryptedVars): bool {
            $encryptedVars = $json;

            return true;
        }), Mockery::any())
        ->andReturn('encrypted');

    $modMock = Mockery::mock(FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'from_name' => 'Test',
            'from_email' => 'test@test.com',
        ]);

    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $di['twig'] = Mockery::mock(Twig\Environment::class);
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['mod_service'] = $di->protect(moduleService(['system' => $systemService]));
    $di['tools'] = new FOSSBilling\Tools();

    $service->setDi($di);

    $result = $service->sendTemplate($data);

    expect($result)->toBeTrue();
    expect($persistedQueue)->not->toBeNull();
    expect($persistedQueue->getAttachmentName())->toBe('Invoice-BB0001.pdf');
    expect($persistedQueue->getAttachmentContent())->toBe('%PDF-1.4 fake invoice contents');
    expect($persistedQueue->getAttachmentMime())->toBe('application/pdf');
    expect($encryptedVars)->not->toContain('fake invoice contents');
});

dataset('sendTemplateExistsStaffProvider', fn (): array => [
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
]);

test('sendTemplate handles to_staff and to_client options', function (array $data, string $clientGetExpects, string $staffResolveExpects): void {
    $service = new Box\Mod\Email\Service();

    $di = container();

    $emailTemplate = emailTemplate(data: ['enabled' => true]);

    $templateRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $templateRepo->shouldReceive('findOneByActionCode')->andReturn($emailTemplate);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('getGroupIdsForTemplate')->andReturn([3]);

    $em = emailBuildEm(null, $templateRepo, null, true, $templateGroupRepo);

    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('value');

    $system->shouldReceive('renderEmailTplString')
        ->atLeast()->once()
        ->andReturn('value');

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $groupMemberRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminGroupMemberRepository::class);
    if ($staffResolveExpects === 'atLeastOnce') {
        $groupMemberRepo->shouldReceive('getActiveStaffInGroups')
            ->atLeast()->once()
            ->with([3])
            ->andReturn([
                0 => [
                    'id' => 1,
                    'email' => 'staff@fossbilling.org',
                    'name' => 'George',
                    'signature' => '',
                    'timezone' => null,
                ],
            ]);
        $staffServiceMock->shouldReceive('getAdminGroupMemberRepository')->atLeast()->once()->andReturn($groupMemberRepo);
    } else {
        $groupMemberRepo->shouldReceive('getActiveStaffInGroups')->never();
        $staffServiceMock->shouldReceive('getAdminGroupMemberRepository')->never();
    }

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class);

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
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

    $twigStub = Mockery::mock(Twig\Environment::class);

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()->once();

    $di['api_admin'] = function () use ($di) {
        $api = new FOSSBilling\Api\Proxy(new Model_Admin());
        $api->setDi($di);

        return $api;
    };

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();
    $di['validator'] = $validatorMock;

    $modMock = Mockery::mock(FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'from_name' => 'Test',
            'from_email' => 'test@test.com',
        ]);

    $di['em'] = $em;
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['twig'] = $twigStub;
    $di['crypt'] = $cryptMock;
    $di['mod_service'] = $di->protect(moduleService([
        'staff' => $staffServiceMock,
        'system' => $system,
        'client' => $clientServiceMock,
    ]));
    $di['tools'] = new FOSSBilling\Tools();

    $service->setDi($di);

    $result = $service->sendTemplate($data);

    expect($result)->toBeTrue();
})->with('sendTemplateExistsStaffProvider');

test('sendTemplate does not send to staff when template has no assigned groups', function (): void {
    $service = new Box\Mod\Email\Service();

    $di = container();

    $emailTemplate = emailTemplate(data: ['enabled' => true]);

    $templateRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $templateRepo->shouldReceive('findOneByActionCode')->andReturn($emailTemplate);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('getGroupIdsForTemplate')->andReturn([]);

    $em = emailBuildEm(null, $templateRepo, null, true, $templateGroupRepo);

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('getAdminGroupMemberRepository')->never();

    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')->atLeast()->once()->andReturn('value');
    $systemService->shouldReceive('renderEmailTplString')->atLeast()->once()->andReturn('rendered');

    $modMock = Mockery::mock(FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([
        'from_name' => 'Test',
        'from_email' => 'test@test.com',
    ]);

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('encrypt')->atLeast()->once();

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->byDefault();

    $twigStub = Mockery::mock(Twig\Environment::class);

    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $di['validator'] = $validatorMock;
    $di['twig'] = $twigStub;
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['mod_service'] = $di->protect(moduleService(['staff' => $staffServiceMock, 'system' => $systemService]));

    $service->setDi($di);

    $result = $service->sendTemplate([
        'code' => 'mod_email_test',
        'to_staff' => 1,
        'default_subject' => 'SUBJECT',
        'default_template' => 'TEMPLATE',
        'default_description' => 'DESCRIPTION',
    ]);

    expect($result)->toBeFalse();
});

test('getTemplateGroupIds delegates to the template group repository', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('getGroupIdsForTemplate')->once()->with(5)->andReturn([1, 2]);

    $service->setDi($di);
    $ref = new ReflectionProperty($service, 'templateGroupRepository');
    $ref->setValue($service, $templateGroupRepo);

    $template = emailTemplate(id: 5);

    expect($service->getTemplateGroupIds($template))->toBe([1, 2]);
});

test('addTemplateToGroup assigns a template to an existing staff group', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $template = emailTemplate(id: 5);
    $group = new Box\Mod\Staff\Entity\AdminGroup();
    Tests\Helpers\setEntityId($group, 3);

    $adminGroupRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminGroupRepository::class);
    $adminGroupRepo->shouldReceive('find')->once()->with(3)->andReturn($group);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('getAdminGroupRepository')->andReturn($adminGroupRepo);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('findAssociation')->once()->with(5, 3)->andReturn(null);

    $em = emailBuildEm(null, null, null, true, $templateGroupRepo);
    $em->shouldReceive('persist')->once()->with(Mockery::type(Box\Mod\Email\Entity\EmailTemplateGroup::class));
    $em->shouldReceive('flush')->atLeast()->once();

    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['staff' => $staffService]));
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    expect($service->addTemplateToGroup($template, 3))->toBeTrue();
});

test('addTemplateToGroup is idempotent when the association already exists', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $template = emailTemplate(id: 5);
    $group = new Box\Mod\Staff\Entity\AdminGroup();
    Tests\Helpers\setEntityId($group, 3);

    $adminGroupRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminGroupRepository::class);
    $adminGroupRepo->shouldReceive('find')->once()->with(3)->andReturn($group);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('getAdminGroupRepository')->andReturn($adminGroupRepo);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('findAssociation')->once()->with(5, 3)
        ->andReturn(new Box\Mod\Email\Entity\EmailTemplateGroup($template, 3));

    $em = emailBuildEm(null, null, null, true, $templateGroupRepo);
    $em->shouldReceive('persist')->never();

    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['staff' => $staffService]));
    $service->setDi($di);

    expect($service->addTemplateToGroup($template, 3))->toBeTrue();
});

test('addTemplateToGroup throws when the staff group does not exist', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $template = emailTemplate(id: 5);

    $adminGroupRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminGroupRepository::class);
    $adminGroupRepo->shouldReceive('find')->once()->with(3)->andReturn(null);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('getAdminGroupRepository')->andReturn($adminGroupRepo);

    $di['mod_service'] = $di->protect(moduleService(['staff' => $staffService]));
    $service->setDi($di);

    $service->addTemplateToGroup($template, 3);
})->throws(FOSSBilling\InformationException::class, 'Staff group not found');

test('removeTemplateFromGroup removes an existing association', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $template = emailTemplate(id: 5);
    $association = new Box\Mod\Email\Entity\EmailTemplateGroup($template, 3);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('findAssociation')->once()->with(5, 3)->andReturn($association);

    $em = emailBuildEm(null, null, null, true, $templateGroupRepo);
    $em->shouldReceive('remove')->once()->with($association);
    $em->shouldReceive('flush')->atLeast()->once();

    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    expect($service->removeTemplateFromGroup($template, 3))->toBeTrue();
});

test('removeTemplateFromGroup is a no-op when no association exists', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $template = emailTemplate(id: 5);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('findAssociation')->once()->with(5, 3)->andReturn(null);

    $em = emailBuildEm(null, null, null, true, $templateGroupRepo);
    $em->shouldReceive('remove')->never();

    $di['em'] = $em;
    $service->setDi($di);

    expect($service->removeTemplateFromGroup($template, 3))->toBeTrue();
});

test('assignAllGroupsToTemplate links a template to every existing staff group', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $groupA = new Box\Mod\Staff\Entity\AdminGroup();
    Tests\Helpers\setEntityId($groupA, 10);
    $groupB = new Box\Mod\Staff\Entity\AdminGroup();
    Tests\Helpers\setEntityId($groupB, 20);

    $adminGroupRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminGroupRepository::class);
    $adminGroupRepo->shouldReceive('findAll')->once()->andReturn([$groupA, $groupB]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('getAdminGroupRepository')->andReturn($adminGroupRepo);

    $em = emailBuildEm();
    $persistedGroupIds = [];
    $em->shouldReceive('persist')->andReturnUsing(function ($entity) use (&$persistedGroupIds): void {
        if ($entity instanceof Box\Mod\Email\Entity\EmailTemplateGroup) {
            $persistedGroupIds[] = $entity->getAdminGroupId();
        }
    });
    $em->shouldReceive('flush')->atLeast()->once();

    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['staff' => $staffService]));
    $service->setDi($di);

    $template = emailTemplate('mod_staff_client_order', 1);

    $ref = new ReflectionMethod($service, 'assignAllGroupsToTemplate');
    $ref->invoke($service, $template);

    expect($persistedGroupIds)->toBe([10, 20]);
});

test('resend resends email', function (): void {
    $service = new Box\Mod\Email\Service();

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
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension->shouldReceive('isExtensionActive')
        ->atLeast()->once()
        ->andReturn($isExtensionActiveReturn);

    $config = [];
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $extension);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, 1);
    $model->setClientId(1);
    $model->setSender('sender@exemple.com');
    $model->setRecipients('recipient@example.com');
    $model->setSubject('Email Title');
    $model->setContentHtml('<b>Content</b>');
    $model->setContentText('Content');

    $result = $service->resend($model);

    expect($result)->toBeTrue();
});

test('templateToApiArray returns API array for template', function (): void {
    $id = 1;
    $action_code = 'code';
    $category = 'category';
    $enabled = 1;
    $subject = 'Subject';
    $description = 'Description';
    $content = 'content';

    $model = emailTemplate($action_code, $id, [
        'category' => $category,
        'enabled' => $enabled,
        'subject' => $subject,
        'description' => $description,
        'content' => $content,
    ]);

    $expected = [
        'id' => $id,
        'action_code' => $action_code,
        'category' => $category,
        'enabled' => true,
        'subject' => $subject,
        'description' => $description,
        'is_custom' => false,
        'has_default' => false,
        'is_overridden' => false,
    ];

    $service = new Box\Mod\Email\Service();
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

    $model = emailTemplate($action_code, $id, [
        'category' => $category,
        'enabled' => $enabled,
        'subject' => $subject,
        'description' => $description,
        'content' => $content,
    ]);

    $expected = [
        'id' => $id,
        'action_code' => $action_code,
        'category' => $category,
        'enabled' => true,
        'subject' => $subject,
        'description' => $description,
        'is_custom' => false,
        'has_default' => false,
        'is_overridden' => false,
        'content' => $content,
        'vars' => [
            'param1' => 'value1',
        ],
        'subject_override' => $subject,
        'content_override' => $content,
        'has_error' => false,
        'last_error' => null,
    ];

    $serviceMock = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $serviceMock->shouldReceive('getVars')
        ->atLeast()->once()
        ->andReturn(['param1' => 'value1']);

    $result = $serviceMock->templateToApiArray($model, true);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('vars');
    expect($result['vars'])->toBeArray();

    expect($result)->toBe($expected);
});

dataset('template_updateProvider', fn (): array => [
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
]);

test('updateTemplate updates template', function (array $data, string $templateRenderExpects): void {
    $id = 1;
    $model = emailTemplate(id: $id);

    $emailService = new Box\Mod\Email\Service();

    $loggerStub = new Tests\Helpers\TestLogger();

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('decrypt')
        ->never();
    $configMock = ['salt' => md5(random_bytes(13))];

    $twigStub = Mockery::mock(Twig\Environment::class);

    $di = container();
    $di['logger'] = $loggerStub;
    $di['crypt'] = $cryptMock;
    $di['config'] = $configMock;
    $di['twig'] = $twigStub;

    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    if ($templateRenderExpects === 'atLeastOnce') {
        $systemServiceMock->shouldReceive('renderEmailTplString')
            ->atLeast()->once();
    } else {
        $systemServiceMock->shouldReceive('renderEmailTplString')->never();
    }

    $di['mod_service'] = $di->protect(moduleService(['system' => $systemServiceMock]));

    $emailService->setDi($di);

    $templateModel = emailTemplate();

    $result = $emailService->updateTemplate($templateModel, $data['enabled'], $data['category'], $data['subject'], @$data['content']);
    expect($result)->toBeTrue();
})->with('template_updateProvider');

test('templateCreate creates new template', function (): void {
    $service = new Box\Mod\Email\Service();

    $em = emailBuildEm();
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $data = [
        'action_code' => 'Action_code',
        'subject' => 'Subject',
        'content' => 'Content',
        'category' => 'category',
    ];

    $result = $service->templateCreate($data['action_code'], $data['subject'], $data['content'], 1, $data['category']);

    expect($result)->toBeInstanceOf(EmailTemplate::class);
    expect($result->getActionCode())->toBe($data['action_code']);
});

test('templateBatchDisable disables all templates', function (): void {
    $service = new Box\Mod\Email\Service();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->templateBatchDisable();

    expect($result)->toBeTrue();
});

test('templateBatchEnable enables all templates', function (): void {
    $service = new Box\Mod\Email\Service();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->templateBatchEnable();

    expect($result)->toBeTrue();
});

test('batchSend processes email queue', function (): void {
    $service = new Box\Mod\Email\Service();

    $queueModel = new Box\Mod\Email\Entity\QueuedEmail();
    \Tests\Helpers\setEntityId($queueModel, 1);
    $queueModel->setPriority(10);
    $queueModel->setTries(10);
    $queueModel->setSubject('subject');
    $queueModel->setClientId(1);
    $queueModel->setSender('sender@example.com');
    $queueModel->setRecipient('receiver@example.com');
    $queueModel->setContent('content');
    $queueModel->setFromName('From Name');
    $queueModel->setToName('To Name');

    $queueRepo = Mockery::mock(Box\Mod\Email\Repository\QueuedEmailRepository::class);
    $queueRepo->shouldReceive('findDueBatch')
        ->once()
        ->with(0)
        ->andReturn([$queueModel]);

    $em = emailBuildEm(null, null, $queueRepo);
    $em->shouldReceive('flush')->atLeast()->once();

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'log_enabled' => 1,
            'cancel_after' => 1,
        ]);

    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $isExtensionActiveReturn = false;
    $extension->shouldReceive('isExtensionActive')
        ->atLeast()->once()
        ->andReturn($isExtensionActiveReturn);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
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

test('sendMail queues email for sending', function (): void {
    $em = emailBuildEm();
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;

    $di['logger'] = new Tests\Helpers\TestLogger();
    $modMock = Mockery::mock('\stdClass');
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(function ($name) use ($extension) {
        if ($name == 'system') {
        } elseif ($name == 'extension') {
            return $extension;
        }
    });

    $service = new Box\Mod\Email\Service();
    $service->setDi($di);

    $to = 'receiver@example.com';
    $from = 'sender@example.com';
    $subject = 'Important message';
    $content = 'content';
    $result = $service->sendMail($to, $from, $subject, $content);
    expect($result)->toBeTrue();
});

test('sendMail queues the given attachment onto the queued email', function (): void {
    $em = emailBuildEm();
    /** @var Box\Mod\Email\Entity\QueuedEmail|null $persistedQueue */
    $persistedQueue = null;
    $em->shouldReceive('persist')
        ->atLeast()->once()
        ->with(Mockery::on(function ($queue) use (&$persistedQueue): bool {
            if ($queue instanceof Box\Mod\Email\Entity\QueuedEmail) {
                $persistedQueue = $queue;
            }

            return true;
        }));
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;

    $service = new Box\Mod\Email\Service();
    $service->setDi($di);

    $attachment = [
        'content' => '%PDF-1.4 fake invoice contents',
        'name' => 'Invoice-BB0001.pdf',
        'mime' => 'application/pdf',
    ];

    $result = $service->sendMail('receiver@example.com', 'sender@example.com', 'Invoice created', 'content', null, null, null, null, false, false, $attachment);

    expect($result)->toBeTrue();
    expect($persistedQueue)->not->toBeNull();
    expect($persistedQueue->getAttachmentName())->toBe('Invoice-BB0001.pdf');
    expect($persistedQueue->getAttachmentContent())->toBe('%PDF-1.4 fake invoice contents');
    expect($persistedQueue->getAttachmentMime())->toBe('application/pdf');
});

test('getBrokenTemplateCount returns count from repository', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $repoMock = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class)->shouldIgnoreMissing();
    $repoMock->shouldReceive('countBroken')
        ->once()
        ->andReturn(3);

    $service->setDi($di);

    $ref = new ReflectionProperty($service, 'templateRepository');
    $ref->setValue($service, $repoMock);

    expect($service->getBrokenTemplateCount())->toBe(3);
});

test('validateAllTemplates reports invalid templates', function (): void {
    $serviceMock = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $di = container();

    $validTemplate = emailTemplate('mod_email_valid', 1, [
        'enabled' => true,
        'subject' => 'Hello',
        'content' => 'World',
    ]);

    $invalidTemplate = emailTemplate('mod_email_broken', 2, [
        'enabled' => true,
        'subject' => 'Hello',
        'content' => 'Broken',
    ]);

    $repoMock = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class)->shouldIgnoreMissing();
    $repoMock->shouldReceive('findAll')
        ->once()
        ->andReturn([$validTemplate, $invalidTemplate]);

    $systemMock = Mockery::mock();
    $systemMock->shouldReceive('renderEmailTplString')
        ->times(3)
        ->andReturnUsing(function (string $template, array $vars): string {
            expect($vars)->toBe([]);

            if ($template === 'Broken') {
                throw new FOSSBilling\InformationException('Email template syntax error: Unknown "filter" filter');
            }

            return $template;
        });

    $emMock = emailBuildEm();
    $emMock->shouldReceive('flush')->once();

    $di['mod_service'] = $di->protect(function ($name) use ($systemMock) {
        if ($name === 'System' || $name === 'system') {
            return $systemMock;
        }
    });
    $di['em'] = $emMock;

    $serviceMock->setDi($di);

    $ref = new ReflectionProperty($serviceMock, 'templateRepository');
    $ref->setValue($serviceMock, $repoMock);

    $result = $serviceMock->validateAllTemplates();

    expect($result['valid'])->toBe(1);
    expect($result['invalid'])->toBe(1);
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['action_code'])->toBe('mod_email_broken');
});

test('validateAllTemplates clears previous errors on valid templates', function (): void {
    $serviceMock = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $di = container();

    $template = emailTemplate('mod_email_recovery', 1, [
        'enabled' => true,
        'subject' => 'Hello',
        'content' => 'World',
        'last_error' => 'Previous error',
    ]);

    $repoMock = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class)->shouldIgnoreMissing();
    $repoMock->shouldReceive('findAll')
        ->once()
        ->andReturn([$template]);

    $systemMock = Mockery::mock();
    $systemMock->shouldReceive('renderEmailTplString')
        ->twice()
        ->andReturnArg(0);

    $emMock = emailBuildEm();
    $emMock->shouldReceive('flush')->once();

    $di['mod_service'] = $di->protect(function ($name) use ($systemMock) {
        if ($name === 'System' || $name === 'system') {
            return $systemMock;
        }
    });
    $di['em'] = $emMock;

    $serviceMock->setDi($di);

    $ref = new ReflectionProperty($serviceMock, 'templateRepository');
    $ref->setValue($serviceMock, $repoMock);

    $result = $serviceMock->validateAllTemplates();

    expect($result['valid'])->toBe(1);
    expect($result['invalid'])->toBe(0);
    expect($template->hasError())->toBeFalse();
});

test('validateAllTemplates renders templates with stored vars to enforce sandbox policy', function (): void {
    $serviceMock = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $di = container();

    $template = emailTemplate('mod_email_sandbox', 1, [
        'enabled' => true,
        'subject' => 'Hello {{ name }}',
        'content' => '{{ content|disallowed_filter }}',
        'vars' => 'encrypted-vars',
    ]);

    $repoMock = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class)->shouldIgnoreMissing();
    $repoMock->shouldReceive('findAll')
        ->once()
        ->andReturn([$template]);

    $cryptMock = Mockery::mock('\Box_Crypt');
    $cryptMock->shouldReceive('decrypt')
        ->once()
        ->with('encrypted-vars', Mockery::type('string'))
        ->andReturn('{"name":"Ada","content":"Body"}');

    $systemMock = Mockery::mock();
    $systemMock->shouldReceive('renderEmailTplString')
        ->once()
        ->with('{{ content|disallowed_filter }}', ['name' => 'Ada', 'content' => 'Body'])
        ->andThrow(new FOSSBilling\InformationException('Email template contains disallowed Twig syntax: Filter "disallowed_filter" is not allowed'));
    $systemMock->shouldReceive('renderEmailTplString')
        ->with('Hello {{ name }}', Mockery::any())
        ->never();

    $emMock = emailBuildEm();
    $emMock->shouldReceive('flush')->once();

    $di['crypt'] = $cryptMock;
    $di['mod_service'] = $di->protect(function ($name) use ($systemMock) {
        if ($name === 'System' || $name === 'system') {
            return $systemMock;
        }
    });
    $di['em'] = $emMock;

    $serviceMock->setDi($di);

    $ref = new ReflectionProperty($serviceMock, 'templateRepository');
    $ref->setValue($serviceMock, $repoMock);

    $result = $serviceMock->validateAllTemplates();

    expect($result['valid'])->toBe(0);
    expect($result['invalid'])->toBe(1);
    expect($template->hasError())->toBeTrue();
    expect($template->getLastError())->toContain('disallowed Twig syntax');
});

test('templateCreate validates subject and content', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $systemMock = Mockery::mock();
    $systemMock->shouldReceive('renderEmailTplString')
        ->once()
        ->withArgs(fn ($tpl): bool => str_contains((string) $tpl, 'valid subject'))
        ->andReturn('rendered');
    $systemMock->shouldReceive('renderEmailTplString')
        ->once()
        ->withArgs(fn ($tpl): bool => str_contains((string) $tpl, 'valid content'))
        ->andReturn('rendered');

    $emMock = emailBuildEm();
    $emMock->shouldReceive('persist')->once();
    $emMock->shouldReceive('flush')->once();

    $di['mod_service'] = $di->protect(function ($name) use ($systemMock) {
        if ($name === 'System' || $name === 'system') {
            return $systemMock;
        }
    });
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $template = $service->templateCreate('mod_test_code', 'valid subject', 'valid content', 1, 'test');

    expect($template)->toBeInstanceOf(EmailTemplate::class);
});

test('templateCreate throws on invalid content', function (): void {
    $service = new Box\Mod\Email\Service();
    $di = container();

    $systemMock = Mockery::mock();
    $systemMock->shouldReceive('renderEmailTplString')
        ->once()
        ->andReturn('rendered');
    $systemMock->shouldReceive('renderEmailTplString')
        ->once()
        ->andThrow(new FOSSBilling\InformationException('Email template syntax error: Unknown "bad_filter" filter'));

    $di['mod_service'] = $di->protect(function ($name) use ($systemMock) {
        if ($name === 'System' || $name === 'system') {
            return $systemMock;
        }
    });

    $service->setDi($di);

    $service->templateCreate('mod_test_broken', 'subject', '{{ x|bad_filter }}', 1);
})->throws(FOSSBilling\InformationException::class, 'Email template syntax error');

test('EmailTemplate hasError returns true when lastError is set', function (): void {
    $template = new EmailTemplate('test_code', 1);

    expect($template->hasError())->toBeFalse();

    $template->setLastError('Some error message');
    expect($template->hasError())->toBeTrue();

    $template->clearError();
    expect($template->hasError())->toBeFalse();
    expect($template->getLastError())->toBeNull();
    expect($template->getErrorCheckedAt())->toBeNull();
});

test('EmailTemplate toApiArray includes has_error and last_error in deep mode', function (): void {
    $template = emailTemplate('mod_test_code', 1, [
        'enabled' => true,
        'subject' => 'Test Subject',
        'content' => 'Test Content',
        'is_custom' => true,
        'last_error' => 'Some syntax error',
    ]);

    $serviceMock = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $serviceMock->shouldReceive('getVars')
        ->atLeast()->once()
        ->andReturn([]);

    $result = $serviceMock->templateToApiArray($template, true);

    expect($result['has_error'])->toBeTrue();
    expect($result['last_error'])->toBe('Some syntax error');
});

test('queuedAttachmentToArray returns null when the queue has no attachment', function (): void {
    $service = new Box\Mod\Email\Service();
    $queue = new Box\Mod\Email\Entity\QueuedEmail();

    $ref = new ReflectionMethod($service, 'queuedAttachmentToArray');
    $result = $ref->invoke($service, $queue);

    expect($result)->toBeNull();
});

test('queuedAttachmentToArray converts a queued attachment into a mail-ready array', function (): void {
    $service = new Box\Mod\Email\Service();
    $queue = new Box\Mod\Email\Entity\QueuedEmail();
    $queue->setAttachmentName('Invoice-BB0001.pdf');
    $queue->setAttachmentContent('%PDF-1.4 fake invoice contents');
    $queue->setAttachmentMime('application/pdf');

    $ref = new ReflectionMethod($service, 'queuedAttachmentToArray');
    $result = $ref->invoke($service, $queue);

    expect($result)->toBe([
        'content' => '%PDF-1.4 fake invoice contents',
        'name' => 'Invoice-BB0001.pdf',
        'mime' => 'application/pdf',
    ]);
});

test('loggedAttachmentToArray returns null when the logged email has no attachment', function (): void {
    $service = new Box\Mod\Email\Service();
    $email = new Box\Mod\Email\Entity\ActivityClientEmail();

    $ref = new ReflectionMethod($service, 'loggedAttachmentToArray');
    $result = $ref->invoke($service, $email);

    expect($result)->toBeNull();
});

test('loggedAttachmentToArray converts a logged attachment into a mail-ready array', function (): void {
    $service = new Box\Mod\Email\Service();
    $email = new Box\Mod\Email\Entity\ActivityClientEmail();
    $email->setAttachmentName('Invoice-BB0001.pdf');
    $email->setAttachmentContent('%PDF-1.4 fake invoice contents');
    $email->setAttachmentMime('application/pdf');

    $ref = new ReflectionMethod($service, 'loggedAttachmentToArray');
    $result = $ref->invoke($service, $email);

    expect($result)->toBe([
        'content' => '%PDF-1.4 fake invoice contents',
        'name' => 'Invoice-BB0001.pdf',
        'mime' => 'application/pdf',
    ]);
});

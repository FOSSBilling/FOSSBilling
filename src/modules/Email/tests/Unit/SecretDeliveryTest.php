<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Email\Entity\EmailTemplate;
use Box\Mod\Email\Entity\QueuedEmail;
use Box\Mod\Email\Service;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

use function Tests\Helpers\container;

function secretDeliveryContext(string $code = 'mod_client_password_reset_request', array $settings = [], ?Closure $beforeSend = null): array
{
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 4)], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(QueuedEmail::class), $em->getClassMetadata(EmailTemplate::class)]);
    $template = (new EmailTemplate($code))->setEnabled(true)->setIsCustom(true)->setSubject('Reset password')->setContent('private-reset-token');
    $em->persist($template);
    $em->flush();
    $flushes = new class {
        public int $count = 0;

        public function onFlush(): void
        {
            ++$this->count;
        }
    };
    $em->getEventManager()->addEventListener(['onFlush'], $flushes);
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension->shouldReceive('isExtensionActive')->andReturnUsing($beforeSend ?? static fn (): bool => false);
    $activity = Mockery::mock(Box\Mod\Activity\Service::class);
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('renderEmailTplString', 'renderEmailSubjectString')->andReturnUsing(static fn (string $text): string => $text);
    $system->shouldReceive('getParamValue')->andReturn('sender@example.com');
    $module = Mockery::mock(FOSSBilling\Core\Module::class);
    $module->shouldReceive('getConfig')->andReturn($settings + ['mailer' => 'custom', 'custom_dsn' => 'null://null', 'bcc_email' => 'audit@example.com,invalid-bcc', 'reply_to' => 'reply@example.com', 'log_enabled' => true]);
    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn (): object => $module);
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'extension' => $extension,
        'activity' => $activity,
        default => $system,
    });
    $service = new Service();
    $service->setDi($di);

    return [$service, $em, $flushes, $activity, $di];
}

test('secret templates never persist a body or attachment during successful delivery', function (string $code): void {
    $previous = getenv('APP_ENV');
    putenv('APP_ENV=prod');

    try {
        [$service, $em, $flushes, $activity, $di] = secretDeliveryContext($code);
        $activity->shouldNotReceive('logEmail');
        expect($service->sendTemplate(['code' => $code, 'to' => 'client@example.com', 'attachment' => ['name' => 'secret.txt', 'content' => 'private-attachment']]))->toBeTrue();
        expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)
            ->and($em->getUnitOfWork()->getScheduledEntityInsertions())->toBeEmpty()
            ->and($flushes->count)->toBe(0);
        expect(array_filter($di['logger']->calls, static fn (array $call): bool => $call['method'] === 'warning'))->toBeEmpty();
    } finally {
        putenv($previous === false ? 'APP_ENV' : 'APP_ENV=' . $previous);
    }
})->with(['mod_client_password_reset_request', 'mod_client_password_reset_information', 'mod_client_confirm', 'mod_client_signup', 'mod_client_signup_admin', 'mod_staff_password_reset_request', 'mod_staff_password_reset_approve']);

test('secret direct mail is synchronous even when send_now is false', function (): void {
    [$service, $em, $flushes, $activity] = secretDeliveryContext();
    $activity->shouldNotReceive('logEmail');
    $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', send_now: false, skipHistory: true);
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)->and($flushes->count)->toBe(0);
});

test('interrupted secret setup cannot leave an ordinary queued retry', function (): void {
    [$service, $em, $flushes, $activity] = secretDeliveryContext(beforeSend: static function (): never {
        throw new RuntimeException('interrupted before transport');
    });
    $activity->shouldNotReceive('logEmail');
    expect(fn () => $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', send_now: true, skipHistory: true))->toThrow(RuntimeException::class, 'interrupted');
    expect($em->getRepository(QueuedEmail::class)->findDueBatch())->toBeEmpty()
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)
        ->and($flushes->count)->toBe(0);
});

test('demo mode does not retain secret messages', function (): void {
    [$service, $em, $flushes] = secretDeliveryContext(beforeSend: static fn (): bool => true);
    $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', skipHistory: true);
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)->and($flushes->count)->toBe(0);
});

test('failed secret transport cannot persist a retry or history', function (bool $throw): void {
    $previous = getenv('APP_ENV');
    putenv('APP_ENV=prod');

    try {
        [$service, $em, $flushes, $activity] = secretDeliveryContext(settings: ['mailer' => 'invalid']);
        $activity->shouldNotReceive('logEmail');
        $send = fn () => $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', throw_exceptions: $throw, skipHistory: true);
        if ($throw) {
            expect($send)->toThrow(FOSSBilling\Core\Exception\BaseException::class);
        } else {
            expect($send())->toBeTrue();
        }
        expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)->and($flushes->count)->toBe(0);
    } finally {
        putenv($previous === false ? 'APP_ENV' : 'APP_ENV=' . $previous);
    }
})->with([false, true]);

test('ordinary mail retains queued attachments and delivery history', function (): void {
    $previous = getenv('APP_ENV');
    putenv('APP_ENV=prod');

    try {
        [$service, $em, , $activity, $di] = secretDeliveryContext('custom_notice');
        $activity->shouldReceive('logEmail')->once();
        $service->sendTemplate(['code' => 'custom_notice', 'to' => 'client@example.com', 'attachment' => ['name' => 'notice.txt', 'content' => 'attachment']]);
        expect($em->getConnection()->fetchOne('SELECT content FROM email_queue'))->toBe('private-reset-token')
            ->and($em->getRepository(QueuedEmail::class)->findOneBy([])->getAttachmentName())->toBe('notice.txt');
        $service->batchSend();
        expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0);
        expect(array_filter($di['logger']->calls, static fn (array $call): bool => $call['method'] === 'warning'))->toHaveCount(1);
    } finally {
        putenv($previous === false ? 'APP_ENV' : 'APP_ENV=' . $previous);
    }
});

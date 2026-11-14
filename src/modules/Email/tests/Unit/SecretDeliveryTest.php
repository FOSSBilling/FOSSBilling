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
    $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', send_now: false, sensitiveDelivery: true);
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)->and($flushes->count)->toBe(0);
});

test('interrupted secret setup cannot leave an ordinary queued retry', function (): void {
    [$service, $em, $flushes, $activity] = secretDeliveryContext(beforeSend: static function (): never {
        throw new RuntimeException('interrupted before transport');
    });
    $activity->shouldNotReceive('logEmail');
    expect(fn () => $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', send_now: true, sensitiveDelivery: true))->toThrow(RuntimeException::class, 'interrupted');
    expect($em->getRepository(QueuedEmail::class)->findDueBatch())->toBeEmpty()
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)
        ->and($flushes->count)->toBe(0);
});

test('demo mode does not retain secret messages', function (): void {
    [$service, $em, $flushes] = secretDeliveryContext(beforeSend: static fn (): bool => true);
    expect($service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', sensitiveDelivery: true))->toBeFalse();
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)->and($flushes->count)->toBe(0);
});

test('failed secret transport cannot persist a retry or history', function (bool $throw): void {
    $previous = getenv('APP_ENV');
    putenv('APP_ENV=prod');

    try {
        [$service, $em, $flushes, $activity] = secretDeliveryContext(settings: ['mailer' => 'invalid']);
        $activity->shouldNotReceive('logEmail');
        $send = fn () => $service->sendMail('client@example.com', 'sender@example.com', 'Reset', 'private-reset-token', throw_exceptions: $throw, sensitiveDelivery: true);
        if ($throw) {
            expect($send)->toThrow(FOSSBilling\Core\Exception\BaseException::class);
        } else {
            expect($send())->toBeFalse();
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

// Exercise the real renderer: Twig diagnostics can include evaluated token values.
test('secret rendering failures cannot expose tokens through stored errors or logs', function (string $code, string $part, string $source): void {
    [$service, $em, , , $di] = secretDeliveryContext($code);
    $template = $em->getRepository(EmailTemplate::class)->findOneBy([]);
    $template->setSubject($part === 'subject' ? $source : 'Reset password');
    $template->setContent($part === 'content' ? $source : 'Reset password');
    $em->flush();
    $system = Mockery::mock(Box\Mod\System\Service::class)->makePartial();
    $system->shouldReceive('getParamValue')->andReturn('sender@example.com');
    $di['api_guest'] = new class {
        public function system_company(): array
        {
            return [];
        }
    };
    $di['twig_factory'] = new FOSSBilling\Core\Twig\TwigFactory($di);
    $system->setDi($di);
    $di['mod_service'] = $di->protect(fn (): object => $system);
    $token = str_repeat('abcdef12', 8);

    try {
        $service->sendTemplate(['code' => $code, 'to' => 'client@example.com', 'link' => ['hash' => $token]]);
        $this->fail('The malicious template must fail to render');
    } catch (FOSSBilling\Core\Exception\InformationException $e) {
        expect($e->getMessage())->toBe('An error occurred while processing sensitive data')
            ->and($e->getPrevious())->toBeNull();
    }
    $em->clear();
    $stored = $em->getRepository(EmailTemplate::class)->findOneBy([]);
    expect($stored->getLastError())->toBe('An error occurred while processing sensitive data')
        ->and(json_encode($stored->toApiArray()))->not->toContain($token)
        ->and(json_encode($di['logger']->calls))->not->toContain($token)
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0);
})->with(['mod_client_password_reset_request', 'mod_staff_password_reset_request'])
    ->with(['subject', 'content'])
    ->with([
        'date diagnostic' => '{% if link.hash|length == 64 %}{{ link.hash|date }}{% endif %}',
        'dynamic missing key' => '{{ {}[link.hash] }}',
        'sandbox attribute' => '{{ attribute({}, link.hash) }}',
    ]);

test('ordinary template errors retain useful diagnostics', function (): void {
    $di = container();
    $di['api_guest'] = new class {
        public function system_company(): array
        {
            return [];
        }
    };
    $di['mod'] = $di->protect(fn () => new class {
        public function getConfig(): array
        {
            return [];
        }
    });
    $di['twig_factory'] = new FOSSBilling\Core\Twig\TwigFactory($di);
    $system = new Box\Mod\System\Service();
    $system->setDi($di);
    expect(fn () => $system->renderEmailTplString('{{ "invalid-date"|date }}', []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'invalid-date');
});

test('protected rendering errors redact production debug stack traces', function (string $source): void {
    $code = <<<'CODE'
        define('DEBUG', true);
        define('PATH_ROOT', $argv[1] . '/src');
        define('PATH_CONFIG', PATH_ROOT . '/config-sample.php');
        require PATH_ROOT . '/vendor/autoload.php';
        putenv('APP_ENV=prod');
        $twig = new Twig\Environment(new Twig\Loader\ArrayLoader(), ['strict_variables' => true]);
        $di = new Pimple\Container();
        $di['api_guest'] = new class {
            public function system_period_title(array $data): string {
                return (new Box\Mod\System\Service())->getPeriod($data['code']);
            }
        };
        $twig->addExtension(new Twig\Extension\AttributeExtension(FOSSBilling\Core\Twig\Extension\LegacyExtension::class));
        $twig->addRuntimeLoader(new Twig\RuntimeLoader\FactoryRuntimeLoader([
            FOSSBilling\Core\Twig\Extension\LegacyExtension::class => fn () => new FOSSBilling\Core\Twig\Extension\LegacyExtension($di),
        ]));
        $twig->addExtension(new Twig\Extension\SandboxExtension(FOSSBilling\Core\Twig\EmailPolicy::create(), true));
        try {
            FOSSBilling\Core\Twig\SandboxedStringRenderer::render($twig, $argv[2], ['token' => str_repeat('abcdef12', 8)], 'Email template', protectErrors: true);
        } catch (FOSSBilling\Core\Exception\InformationException $e) {
            echo $e->getMessage();
        }
        CODE;
    $process = new Symfony\Component\Process\Process([PHP_BINARY, '-d', 'error_log=/dev/stderr', '-r', $code, dirname(PATH_TESTS), $source]);
    $process->mustRun();
    expect($process->getOutput())->toBe('Email template rendering failed')
        ->and($process->getErrorOutput())->toContain('Exception: Email template rendering failed')
        ->and($process->getErrorOutput())->not->toContain(str_repeat('abcdef12', 8));
})->with(['{{ token|date }}', '{{ token|period_title }}']);

test('ordinary synchronous mail failure remains accepted for queued retry', function (): void {
    $previous = getenv('APP_ENV');
    putenv('APP_ENV=prod');

    try {
        [$service, $em] = secretDeliveryContext('custom_notice', settings: ['mailer' => 'invalid']);
        expect($service->sendMail('client@example.com', 'sender@example.com', 'Notice', 'Body', send_now: true))->toBeTrue();
        $queued = $em->getRepository(QueuedEmail::class)->findOneBy([]);
        expect($queued->getStatus())->toBe(QueuedEmail::STATUS_FAILED)->and($queued->getTries())->toBe(1);
    } finally {
        putenv($previous === false ? 'APP_ENV' : 'APP_ENV=' . $previous);
    }
});

test('protected diagnostic scopes restore ordinary errors after success and failure', function (bool $fail): void {
    try {
        FOSSBilling\Core\Exception\BaseException::withProtectedDiagnostics(function () use ($fail): void {
            expect((new FOSSBilling\Core\Exception\BaseException('private token'))->getMessage())->toBe('An error occurred while processing sensitive data');
            FOSSBilling\Core\Exception\BaseException::withProtectedDiagnostics(static fn () => null);
            expect((new FOSSBilling\Core\Exception\BaseException('private token'))->getMessage())->toBe('An error occurred while processing sensitive data');
            if ($fail) {
                throw new RuntimeException('interrupted render');
            }
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('interrupted render');
    }
    expect((new FOSSBilling\Core\Exception\BaseException('ordinary diagnostic'))->getMessage())->toBe('ordinary diagnostic');
})->with([false, true]);

test('secret transport and setup diagnostics never retain secrets in production debug mode', function (string $failure, string $entry, bool $throw): void {
    $process = new Symfony\Component\Process\Process([
        PHP_BINARY, '-d', 'error_log=/dev/stderr', '-d', 'zend.exception_ignore_args=0', '-d', 'zend.exception_string_param_max_len=1024', PATH_TESTS . '/Fixtures/secret-transport-diagnostics.php', $failure, $entry, $throw ? '1' : '0',
    ]);
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($process->getOutput() . $process->getErrorOutput())->not->toContain(str_repeat('abcdef12', 8))
        ->not->toContain('private-attachment-marker')
        ->and($process->getErrorOutput())->toContain('ordinary-body-control')
        ->and($result['ignore_args_after_secret'])->toBe('0')
        ->and($result['secret_queue_count'])->toBe(0)
        ->and($result['ordinary_accepted'])->toBeTrue()
        ->and($result['ordinary_queue_count'])->toBe(1)
        ->and($result['ordinary_logs'])->toContain('Unknown mail transport');
    if ($throw || $failure === 'setup' || ($failure === 'sender' && $entry === 'template')) {
        expect($result['error'])->toBe('An error occurred while processing sensitive data');
    } else {
        expect($result['result'])->toBeFalse()->and($result['error'])->toBeNull();
    }
})->with(['unknown', 'dsn', 'setup', 'sender'])->with(['direct', 'template'])->with([false, true]);

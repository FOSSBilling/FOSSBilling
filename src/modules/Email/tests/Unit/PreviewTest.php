<?php

declare(strict_types=1);

test('bundled email previews render using only synthetic contexts in the real sandbox', function (): void {
    $di = Tests\Helpers\container();
    $currency = new Box\Mod\Currency\Entity\Currency('USD');
    $repo = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repo->shouldReceive('findDefault')->andReturn($currency);
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Currency\Entity\Currency::class)->andReturn($repo);
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getCurrencyRepository')->andReturn($repo);
    $currencyService->shouldReceive('formatCurrency')->andReturnUsing(fn ($amount, $code, $attributes = [], $locale = null) => (new Twig\Extra\Intl\IntlExtension())->formatCurrency($amount, $code, $attributes, $locale ?? 'en_US'));
    $di['mod_service'] = $di->protect(fn (string $name) => match (strtolower($name)) {
        'currency' => $currencyService,
        default => throw new LogicException('Unexpected preview service: ' . $name),
    });
    $emailModule = new class {
        public function getConfig(): array
        {
            return [];
        }
    };
    $di['mod'] = $di->protect(fn (string $name) => match (strtolower($name)) {
        'email' => $emailModule,
        default => throw new LogicException('Unexpected preview module: ' . $name),
    });
    $di['api_guest'] = new class {
        public function system_period_title(array $data): string
        {
            return 'Monthly';
        }

        public function system_company(): array
        {
            return ['name' => 'Example Company', 'signature' => 'Example signature', 'email' => 'billing@example.com', 'url' => 'https://example.com'];
        }
    };
    $url = new FOSSBilling\Core\Url();
    $url->setDi($di);
    $di['url'] = $url;
    $email = new Box\Mod\Email\Service();
    $email->setDi($di);
    $twig = (new FOSSBilling\Core\Twig\TwigFactory($di))->createEmailEnvironment('UTC', 'en_US');
    $twig->enableStrictVariables();
    $files = (new Symfony\Component\Finder\Finder())->files()->in(PATH_MODS)->path('templates/email')->name('mod_*.html.twig');
    $errors = [];
    $count = 0;
    foreach ($files as $file) {
        ++$count;
        $code = str_replace('.html.twig', '', $file->getFilename());

        try {
            $twig->createTemplate($file->getContents())->render($email->getVars(new Box\Mod\Email\Entity\EmailTemplate($code)));
        } catch (Throwable $e) {
            $errors[$code] = $e->getMessage();
        }
    }
    expect($count)->toBeGreaterThan(0)->and($errors)->toBe([]);
});

test('custom action editor variables match actual delivery inputs under strict Twig', function (bool $requiresInvoice): void {
    $template = (new Box\Mod\Email\Entity\EmailTemplate('custom_delivery', 1))
        ->setIsCustom(true)->setEnabled(true)->setSubject('Custom message')
        ->setContent($requiresInvoice ? '{{ invoice.id }}' : 'Generic custom message');
    $di = Tests\Helpers\container();
    $repo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $repo->shouldReceive('findOneByActionCode')->with('custom_delivery')->andReturn($template);
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Email\Entity\EmailTemplate::class)->andReturn($repo);
    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    if ($requiresInvoice) {
        $service->shouldNotReceive('sendMail');
    } else {
        $service->shouldReceive('sendMail')->once()->with('recipient@example.com', 'sender@example.com', 'Custom message', 'Generic custom message', null, 'Example', null, null, false, false, null)->andReturnTrue();
    }
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
    $twig = (new FOSSBilling\Core\Twig\TwigFactory($di))->createEmailEnvironment('UTC', 'en_US');
    $twig->enableStrictVariables();
    $inputs = [];
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('renderEmailTplString', 'renderEmailSubjectString')->andReturnUsing(function ($source, array $vars) use ($twig, &$inputs): string {
        $inputs[] = $vars;

        return $twig->createTemplate($source)->render($vars);
    });
    $system->shouldReceive('getParamValue')->andReturnUsing(fn ($key) => $key === 'company_email' ? 'sender@example.com' : 'Example');
    $di['mod_service'] = $di->protect(fn ($name) => match (strtolower($name)) {
        'email' => $service,
        'system' => $system,
        default => throw new LogicException('Unexpected delivery service: ' . $name),
    });
    $service->setDi($di);
    $api = Mockery::mock(Box\Mod\Email\Api\Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $api->shouldReceive('checkPermissions')->with('email', 'send_emails');
    $api->setDi($di);
    $api->setService($service);
    expect($service->getVars($template))->toBe([])
        ->and($service->templateToApiArray($template, true)['vars'])->toBe([]);
    $send = fn () => $api->template_send(['code' => 'custom_delivery', 'to' => 'recipient@example.com']);
    if ($requiresInvoice) {
        expect($send)->toThrow(Twig\Error\RuntimeError::class);
    } else {
        expect($send())->toBeTrue();
    }
    expect($inputs)->not->toBeEmpty();
    foreach ($inputs as $vars) {
        expect($vars)->toBe($service->getVars($template))->not->toHaveKey('invoice');
    }
})->with([false, true]);

test('custom templates validate without caller variables while delivery remains strict', function (string $operation, string $source, bool $valid): void {
    $di = Tests\Helpers\container();
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
    $factory = new class($di) extends FOSSBilling\Core\Twig\TwigFactory {
        public function createEmailEnvironment(?string $timezone = null, ?string $locale = null): Twig\Environment
        {
            $twig = parent::createEmailEnvironment('UTC', 'en_US');
            $twig->enableStrictVariables();

            return $twig;
        }
    };
    $di['twig_factory'] = $factory;
    $system = new Box\Mod\System\Service();
    $system->setDi($di);
    $di['mod_service'] = $di->protect(fn () => $system);
    $service = new Box\Mod\Email\Service();
    $template = (new Box\Mod\Email\Entity\EmailTemplate('custom_validation', 1))
        ->setIsCustom(true)->setSubject('{{ c.first_name }}')->setContent($source);
    $repo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateRepository::class);
    $repo->shouldReceive('findAll')->andReturn([$template]);
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Email\Entity\EmailTemplate::class)->andReturn($repo);
    $service->setDi($di);
    $validate = fn () => match ($operation) {
        'create' => $service->templateCreate('custom_validation', '{{ c.first_name }}', $source),
        'update' => $service->updateTemplate($template, subject: '{{ c.first_name }}', content: $source),
        'bulk' => $service->validateAllTemplates(),
    };
    if ($operation === 'bulk') {
        expect($validate())->toMatchArray(['valid' => (int) $valid, 'invalid' => (int) !$valid]);
    } elseif ($valid) {
        expect($validate())->not->toBeFalse();
    } else {
        expect($validate)->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    }
    expect(fn () => $system->renderEmailTplString('{{ c.first_name }}', []))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class)
        ->and($system->renderEmailTplString('{{ c.first_name }}', ['c' => ['first_name' => 'Alice']]))->toBe('Alice');
})->with(['create', 'update', 'bulk'])->with([
    'caller variables' => ['Hello {{ c.first_name }}', true],
    'invalid syntax' => ['{% if %}', false],
    'sandbox violation' => ['{% include "private" %}', false],
]);

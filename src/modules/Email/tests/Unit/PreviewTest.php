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
    $url = new FOSSBilling\Url();
    $url->setDi($di);
    $di['url'] = $url;
    $email = new Box\Mod\Email\Service();
    $email->setDi($di);
    $twig = (new FOSSBilling\Twig\TwigFactory($di))->createEmailEnvironment('UTC', 'en_US');
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

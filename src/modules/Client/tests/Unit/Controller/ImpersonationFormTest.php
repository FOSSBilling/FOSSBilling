<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use FOSSBilling\Core\Twig\Extension\ApiExtension;
use FOSSBilling\Core\Twig\Extension\FOSSBillingExtension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function Tests\Helpers\container;

test('client summary renders a native POST impersonation form with a body CSRF token', function (): void {
    $source = (new Filesystem())->readFile(Path::join(PATH_MODS, 'Client/templates/admin/mod_client_manage.html.twig'));
    // Render the embedded summary-actions block independently of the client dashboard.
    expect(preg_match('/{% block summary_actions %}(.*?){% endblock %}/s', $source, $matches))->toBe(1);
    $twig = new Environment(new ArrayLoader(['actions' => $matches[1]]), ['strict_variables' => true, 'autoescape' => 'html']);
    $di = container();
    $twig->addGlobal('app_area', 'admin');
    $twig->addFilter(new TwigFilter('url', (new FOSSBillingExtension($di))->url(...), ['needs_environment' => true, 'is_safe' => ['html']]));
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('api_url', (new ApiExtension($di))->apiUrl(...), ['needs_environment' => true, 'is_safe' => ['html']]));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $config): string => ''));
    $token = 'csrf-token<&"';
    $html = $twig->render('actions', ['client' => ['id' => 5], 'CSRFToken' => $token, 'app_area' => 'admin', 'can_delete_client' => false]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form')->item(0);
    expect($xpath->query('//form'))->toHaveCount(1)
        ->and($form->getAttribute('method'))->toBe('post')
        ->and($form->getAttribute('action'))->toContain('/client/login/5')->not->toContain('CSRFToken')->not->toContain($token)
        ->and($form->getAttribute('target'))->toBe('_blank')
        ->and($form->hasAttribute('data-fb-api'))->toBeFalse()
        ->and($xpath->evaluate('string(//form/input[@type="hidden"][@name="CSRFToken"]/@value)'))->toBe($token)
        ->and($xpath->query('//form/button[@type="submit"]'))->toHaveCount(1)
        ->and($xpath->query('//a[contains(@href, "client/login/")]'))->toHaveCount(0);
});

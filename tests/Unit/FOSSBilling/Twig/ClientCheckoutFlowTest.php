<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Tests\Support\PermissiveStub;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

function checkoutFlowTwig(): Environment
{
    $fs = new Filesystem();
    $twig = new Environment(new ArrayLoader([
        'account' => $fs->readFile(PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_client.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro recaptcha() %}CAPTCHA{% endmacro %}',
    ]), ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, ?string $role = null): string => '/api/' . $role . '/' . $path));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $options): string => 'data-fb-api="checkout"'));
    $twig->addFunction(new TwigFunction('antispam_honeypot', static fn (): array => ['enabled' => true, 'field' => 'demo_trap']));

    return $twig;
}

test('checkout account forms retain required fields, spam checks and safe password inputs', function (): void {
    $required = ['last_name', 'company', 'birthday', 'gender', 'address_1', 'address_2', 'city', 'country', 'state', 'postcode', 'phone'];
    $html = checkoutFlowTwig()->render('account', [
        'request' => ['checkout' => true, 'password' => 'NEVER-ECHO-THIS'],
        'settings' => ['signup_tos' => 'explicit'], 'CSRFToken' => 'demo', 'client' => null,
        'guest' => new PermissiveStub(['client_required' => $required, 'client_custom_fields' => ['custom_1' => ['active' => true, 'required' => true, 'title' => 'Reference']], 'system_countries' => ['GB' => 'United Kingdom']]),
    ]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//form[@data-unsaved-changes]'))->toHaveCount(2);
    expect($dom->getElementById('email')->getAttribute('autocomplete'))->toBe('section-signin username')
        ->and($dom->getElementById('reg-email')->getAttribute('autocomplete'))->toBe('section-signup username')
        ->and($dom->getElementById('reg-email')->getAttribute('autocapitalize'))->toBe('none')
        ->and($dom->getElementById('reg-email')->getAttribute('spellcheck'))->toBe('false')
        ->and($dom->getElementById('password')->getAttribute('enterkeyhint'))->toBe('go');
    foreach (array_merge($required, ['custom_1', 'first_name', 'email', 'password', 'password_confirm']) as $name) {
        expect($xpath->query('//*[@name="' . $name . '"]')->item(0)->hasAttribute('required'))->toBeTrue($name);
        $control = $xpath->query('//*[@name="' . $name . '"]')->item(0);
        expect($xpath->query('//label[@for="' . $control->getAttribute('id') . '" and contains(@class, "form-label")]'))->toHaveCount(1);
    }
    expect($html)->not->toContain('NEVER-ECHO-THIS')
        ->toContain('autocomplete="section-signin current-password"', 'autocomplete="section-signup new-password"', 'data-auth-confirm-password', 'data-auth-show-passwords', 'CAPTCHA', 'name="demo_trap"', 'id="signup-terms"', '/password-reset');
    expect($xpath->query('//*[@id="in-tab"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="up-tab"]')->length)->toBe(1);
});

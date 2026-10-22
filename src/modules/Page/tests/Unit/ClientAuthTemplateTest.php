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

function renderClientAuth(string $template, array $overrides = []): string
{
    $fs = new Filesystem();
    $templates = [
        'layout_public.html.twig' => '{% block body %}{% endblock %}{% block js %}{% endblock %}',
        'layout_auth.html.twig' => $fs->readFile(PATH_THEMES . '/default/client/html/layout_auth.html.twig'),
        'partial_company_logo.html.twig' => $fs->readFile(PATH_MODS . '/Page/templates/client/partial_company_logo.html.twig'),
        'partial_company_logo_shared.html.twig' => $fs->readFile(PATH_THEMES . '/default/shared/html/partial_company_logo_shared.html.twig'),
        'partial_auth_password_help.html.twig' => $fs->readFile(PATH_THEMES . '/default/client/html/partial_auth_password_help.html.twig'),
        'macro_functions.html.twig' => '{% macro recaptcha() %}<div data-test-captcha>CAPTCHA</div>{% endmacro %}',
        'new-password' => $fs->readFile(PATH_MODS . '/Client/templates/client/mod_client_set_new_password.html.twig'),
    ];
    foreach (['login', 'signup', 'password-reset'] as $name) {
        $templates[$name] = $fs->readFile(PATH_MODS . '/Page/templates/client/mod_page_' . $name . '.html.twig');
    }
    $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('url', static fn (string $path): string => '/' . ltrim($path, '/')));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, ?string $role = null): string => '/api/' . ($role ?? 'guest') . '/' . $path));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $options): string => 'method="post" data-fb-api="' . htmlspecialchars(json_encode($options), ENT_QUOTES) . '"', ['is_safe' => ['html']]));
    $twig->addFunction(new TwigFunction('render_widgets', static fn (string $slot): string => '<!-- ' . $slot . ' -->', ['is_safe' => ['html']]));
    $twig->addFunction(new TwigFunction('antispam_honeypot', static fn (): array => ['enabled' => true, 'field' => 'demo_trap']));
    $settings = ['login_page_show_logo' => true, 'login_page_logo_url' => '/brand', 'show_password_reset_link' => true, 'show_signup_link' => true, 'signup_tos' => 'explicit'];

    return $twig->render($template, array_replace([
        'request' => [], 'hash' => 'demo-reset-token', 'CSRFToken' => 'demo-csrf-token', 'company' => ['name' => 'Acme Hosting'], 'public_logo_url' => '/light.svg', 'public_dark_logo_url' => '/dark.svg',
        'guest' => new PermissiveStub(['client_required' => [], 'client_custom_fields' => [], 'system_timezones' => []]),
    ], $overrides, ['settings' => array_replace($settings, $overrides['settings'] ?? [])]));
}

test('login keeps redirect, branding, extension hooks and configurable links', function (): void {
    $html = renderClientAuth('login', ['redirect_uri' => '/order?checkout=1']);
    expect($html)->toContain('/light.svg', '/dark.svg', '/brand', 'client.page.login.form.before', 'client.page.login.form.after', '/api/guest/client/login', 'demo-csrf-token', 'autocomplete="username"', 'autocomplete="current-password"', 'checkout=1', '/signup', '/password-reset');
    $hidden = renderClientAuth('login', ['settings' => ['login_page_show_logo' => false, 'show_signup_link' => false, 'show_password_reset_link' => false]]);
    expect($hidden)->not->toContain('/light.svg', 'href="/signup"', 'href="/password-reset"');
});

test('authentication pages retain custom logo variants links and single-logo fallbacks', function (string $page): void {
    $html = renderClientAuth($page, ['public_logo_url' => '/custom-light.svg', 'public_dark_logo_url' => '/custom-dark.svg']);
    expect($html)->toContain('class="fb-image-contain hide-theme-light img-fluid" src="/custom-dark.svg"', 'class="fb-image-contain hide-theme-dark img-fluid" src="/custom-light.svg"', 'href="/brand"', 'rel="noopener noreferrer"');
    foreach (['public_logo_url', 'public_dark_logo_url'] as $variant) {
        $html = renderClientAuth($page, ['public_logo_url' => null, 'public_dark_logo_url' => null, $variant => '/single.svg']);
        expect(substr_count($html, 'src="/single.svg"'))->toBe(2);
    }
    expect(renderClientAuth($page, ['settings' => ['login_page_show_logo' => false]]))->not->toContain('<img');
})->with(['login', 'signup', 'password-reset', 'new-password']);

test('registration honours configured required and custom fields while retaining anti-spam and terms', function (): void {
    $fields = ['last_name', 'company', 'birthday', 'gender', 'address_1', 'address_2', 'city', 'country', 'state', 'postcode', 'phone'];
    $html = renderClientAuth('signup', [
        'guest' => new PermissiveStub(['client_required' => $fields, 'system_default_country' => 'GB', 'system_countries' => ['GB' => 'United Kingdom'], 'system_timezones' => [], 'client_custom_fields' => ['custom_1' => ['active' => true, 'required' => true, 'title' => 'Reference'], 'custom_2' => ['active' => false]]]),
        'request' => ['first_name' => '<Jane>', 'gender' => 'nonbinary', 'custom_1' => 'DEMO7'],
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    foreach (array_merge($fields, ['custom_1', 'email', 'password', 'password_confirm']) as $name) {
        $input = $xpath->query('//*[@name="' . $name . '"]')->item(0);
        expect($input)->not->toBeNull()->and($input->hasAttribute('required'))->toBeTrue();
    }
    expect($html)->toContain('&lt;Jane&gt;', 'DEMO7', 'name="demo_trap"', 'data-test-captcha', 'Terms of Service', 'Privacy Policy', 'data-timezone-select', 'js-phone-input', '/api/guest/client/create', 'demo-csrf-token', 'data-auth-confirm-password', 'type="checkbox" value="" id="flexCheckDefault" required')
        ->and($html)->not->toContain('name="custom_2"');
});

test('minimal registration leaves unrequired fields out and supports implicit terms', function (): void {
    $html = renderClientAuth('signup', ['settings' => ['signup_tos' => 'implicit']]);
    expect($html)->toContain('By creating an account', 'Already have an account?', 'autocomplete="new-password"', 'Registration submitted.')
        ->and($html)->not->toContain('name="address_1"', 'id="flexCheckDefault"');
});

test('reset request keeps neutral persistent feedback, email validation and captcha', function (): void {
    $html = renderClientAuth('password-reset');
    expect($html)->toContain('/api/guest/client/reset_password', 'type="email"', 'autocomplete="username"', 'data-test-captcha', 'role="status"', 'hidden', 'If an account matches that address', 'status.hidden = false', '/login', 'demo-csrf-token');
});

test('reset completion keeps the token and password requirements with a path back to recovery', function (): void {
    $html = renderClientAuth('new-password');
    expect($html)->toContain('/api/guest/client/update_password', 'name="hash" value="demo-reset-token"', 'autocomplete="new-password"', 'minlength="8"', 'data-auth-confirm-password', 'uppercase and lowercase letters and a number', 'Request Another Reset Link', 'Password changed. Sign in with your new password.', 'demo-csrf-token');
});

test('expired reset link explains how to request a replacement', function (): void {
    expect(renderClientAuth('password-reset', ['request' => ['expired' => 1]]))->toContain('This reset link has expired or has already been used.', 'Send Reset Link');
});

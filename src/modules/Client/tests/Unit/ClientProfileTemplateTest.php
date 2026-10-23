<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\ClientThemeRenderer;
use Tests\Support\PermissiveStub;
use Twig\TwigFilter;
use Twig\TwigFunction;

function clientProfileTemplate(array $overrides = []): string
{
    $twig = ClientThemeRenderer::environment([
        'layout_default.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
    ], strict: true);
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path): string => '/api/client/' . $path));
    $twig->addFilter(new TwigFilter('currency_name', static fn (string $currency): string => $currency));
    foreach (['fb_api_form', 'fb_api_link'] as $helper) {
        $twig->addFunction(new TwigFunction($helper, static fn (array $options = []): string => 'data-fb-api="' . htmlspecialchars(json_encode($options), ENT_QUOTES) . '"', ['is_safe' => ['html']]));
    }
    $profile = array_fill_keys(['first_name', 'last_name', 'phone_cc', 'phone', 'billing_email', 'birthday', 'gender', 'company', 'company_vat', 'company_number', 'address_1', 'address_2', 'city', 'country', 'state', 'postcode', 'timezone'], '');
    $profile = array_replace($profile, ['email' => 'client@example.test', 'currency' => 'USD', 'email_approved' => false], $overrides['profile'] ?? []);

    return $twig->render('mod_client_profile.html.twig', array_replace([
        'profile' => $profile, 'client' => new PermissiveStub(['profile_api_key_get' => 'demo-key']), 'request' => ['ajax' => false],
        'guest' => new PermissiveStub([
            'client_required' => ['last_name', 'country', 'phone'], 'client_is_email_validation_required' => true,
            'system_countries' => ['GB' => 'United Kingdom'], 'system_timezones' => ['Europe' => ['Europe/London']],
            'client_custom_fields' => ['custom_1' => ['active' => true, 'required' => true, 'title' => 'Account reference'], 'custom_2' => ['active' => false, 'title' => 'Hidden field']],
        ]),
    ], array_diff_key($overrides, ['profile' => null])));
}

test('profile details preserve required fields, phone integration and configured custom fields', function (): void {
    $html = clientProfileTemplate(['profile' => ['first_name' => 'Jane <Client>', 'custom_1' => 'ACME-7', 'timezone' => 'Europe/London']]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($document->getElementById('billing_email')->getAttribute('autocomplete'))->toBe('billing email')
        ->and($document->getElementById('email')->getAttribute('autocapitalize'))->toBe('none')
        ->and($document->getElementById('email')->getAttribute('spellcheck'))->toBe('false');
    expect($html)->toContain('Jane &lt;Client&gt;', 'Account reference', 'ACME-7', 'data-timezone-select', 'js-phone-input', 'name="phone_cc"', 'Changing this address requires email verification again.')
        ->and($html)->not->toContain('Hidden field', 'Avatar is derived');
    foreach (['first_name', 'last_name', 'email', 'country', 'phone', 'custom_1'] as $id) {
        expect($document->getElementById($id)->hasAttribute('required'))->toBeTrue();
    }
    expect($document->getElementById('gender')->getElementsByTagName('option')->item(0)->hasAttribute('selected'))->toBeTrue()
        ->and($document->getElementById('profile-update')->hasAttribute('data-unsaved-changes'))->toBeTrue()
        ->and($document->getElementById('profile-password')->hasAttribute('data-unsaved-changes'))->toBeTrue()
        ->and($xpath->query('//fieldset/legend')->length)->toBe(5);
});

test('profile retains verification controls only when required and unverified', function (bool $required, bool $approved, bool $visible): void {
    $html = clientProfileTemplate([
        'profile' => ['email_approved' => $approved],
        'guest' => new PermissiveStub(['client_is_email_validation_required' => $required]),
    ]);
    if ($visible) {
        expect($html)->toContain('Verify Your Email Address', '/api/client/client/resend_email_verification', 'client@example.test');
    } else {
        expect($html)->not->toContain('Resend Verification Email');
    }
})->with([[true, false, true], [true, true, false], [false, false, false]]);

test('profile keeps API Key and currency available without exposing a key by default', function (?string $currency, string $message): void {
    $html = clientProfileTemplate(['profile' => ['currency' => $currency]]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    expect($document->getElementById('api-key')->getAttribute('type'))->toBe('password')
        ->and($document->getElementById('api-key')->hasAttribute('readonly'))->toBeTrue()
        ->and($html)->toContain('Show API Key', '/api/client/profile/api_key_reset', 'update them.', $message, 'aria-labelledby="pass-tab"', 'role="tablist"');
})->with([
    ['USD', 'Your currency was set with your first order'],
    [null, 'Your currency will be set when you place your first order'],
]);

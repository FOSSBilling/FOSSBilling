<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Support\StrictTemplateRenderer;

/*
 * default/shared/html/partial_recaptcha_shared.html.twig and
 * partial_empty_list_shared.html.twig are the bodies of the `recaptcha()`
 * and `empty_list()` macros, byte-identical between the default admin and
 * client themes' own macro_functions.html.twig, extracted here so a fix
 * only needs to land once. Each theme's macro `{% include %}`s these rather
 * than delegating to a shared macro namespace - every real call site uses
 * the parens-less `mf.recaptcha` form, and a macro that calls another
 * template's macro namespace it imported at that OTHER template's top level
 * silently fails ("Call to a member function getTemplateForMacro() on
 * null") under that calling convention, even though it works fine when
 * called as `mf.recaptcha()`. `{% include %}` has no such limitation - it
 * always renders with the current context and Twig globals like `guest`,
 * regardless of how the enclosing macro was invoked.
 */

test('shared recaptcha partial renders nothing when the recaptcha config is disabled', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_recaptcha_shared.html.twig',
        [
            'guest' => new PermissiveCallableStub([
                'extension_is_on' => true,
                'antispam_recaptcha' => [
                    'enabled' => false,
                    'captcha_provider' => 'recaptcha_v2',
                    'publickey' => 'test-site-key',
                ],
            ]),
        ],
    );

    expect(trim($html))->toBe('');
});

test('shared recaptcha partial renders the reCAPTCHA v2 widget when configured', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_recaptcha_shared.html.twig',
        [
            'guest' => new PermissiveCallableStub([
                'extension_is_on' => true,
                'antispam_recaptcha' => [
                    'enabled' => true,
                    'captcha_provider' => 'recaptcha_v2',
                    'publickey' => 'test-site-key',
                ],
            ]),
        ],
    );

    expect($html)
        ->toContain('g-recaptcha')
        ->toContain('data-sitekey="test-site-key"')
        ->not->toContain('cf-turnstile')
        ->not->toContain('h-captcha');
});

test('shared recaptcha partial renders the Turnstile widget when configured', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_recaptcha_shared.html.twig',
        [
            'guest' => new PermissiveCallableStub([
                'extension_is_on' => true,
                'antispam_recaptcha' => [
                    'enabled' => true,
                    'captcha_provider' => 'turnstile',
                    'turnstile_site_key' => 'test-turnstile-key',
                ],
            ]),
        ],
    );

    expect($html)
        ->toContain('cf-turnstile')
        ->toContain('data-sitekey="test-turnstile-key"')
        ->not->toContain('g-recaptcha');
});

test('shared empty_list partial uses the default colspan and message', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_empty_list_shared.html.twig',
        [
            'colspan' => null,
            'message' => null,
        ],
    );

    expect($html)
        ->toContain('colspan="5"')
        ->toContain('The list is empty');
});

test('shared empty_list partial honors a custom colspan and message', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_empty_list_shared.html.twig',
        [
            'colspan' => 3,
            'message' => 'Nothing here yet',
        ],
    );

    expect($html)
        ->toContain('colspan="3"')
        ->toContain('Nothing here yet');
});

/*
 * partial_company_logo.html.twig used to be two near-identical files (one
 * per theme). Unified into one, kept only under default/shared/html so it
 * resolves through TwigLoader's shared-html fallback for both areas - every
 * call site already included it by plain filename, none used a theme-scoped
 * path. Each param combination below matches exactly what a real call site
 * in that theme passes today (see admin's partial_admin_auth_card.html.twig
 * / layout_default.html.twig and client's layout_default.html.twig / the
 * Page, System and Client module templates), so these lock in that no
 * existing caller's rendered output changed.
 */

test('shared company logo partial renders admin-style params: no height, inline style, title attribute', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_company_logo.html.twig',
        [
            'company' => new Tests\Support\PermissiveStub(['name' => 'Acme Hosting']),
            'logo_url' => 'https://example.com/logo.png',
            'dark_logo_url' => 'https://example.com/logo-dark.png',
            'link_url' => '/',
            'image_style' => 'height: 60px; width: auto;',
            'fallback_class' => 'h1',
        ],
    );

    // Not asserting the absence of `target=`: link_target is the one param
    // this partial reads via `is defined` rather than `|default(...)` first
    // (matching production, where a real admin call site never passes it at
    // all) - the harness's auto-stubbing of referenced-but-unset variables
    // fills it in for exactly that reason, which a real render never does.
    expect($html)
        ->toContain('style="height: 60px; width: auto;"')
        ->toContain('title="Acme Hosting"')
        ->not->toContain('height="')
        ->not->toContain('rel=')
        ->not->toContain('d-sm-none');
});

test('shared company logo partial renders the admin fallback span with fallback_class when there is no logo', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_company_logo.html.twig',
        [
            'company' => new Tests\Support\PermissiveStub(['name' => 'Acme Hosting']),
            'logo_url' => null,
            'dark_logo_url' => null,
            'fallback_class' => 'h1',
        ],
    );

    expect($html)
        ->toContain('<span class="h1">Acme Hosting</span>')
        ->not->toContain('<img');
});

test('shared company logo partial renders client-style params: height, target, rel, mobile name', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_company_logo.html.twig',
        [
            'company' => new Tests\Support\PermissiveStub(['name' => 'Acme Hosting']),
            'logo_url' => 'https://example.com/logo.png',
            'dark_logo_url' => 'https://example.com/logo-dark.png',
            'link_url' => '/',
            'link_target' => '_blank',
            'link_rel' => 'noopener noreferrer',
            'image_height' => '50px',
        ],
    );

    expect($html)
        ->toContain('height="50px"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('style=');
});

test('shared company logo partial renders the mobile name span only when show_mobile_name is true', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_company_logo.html.twig',
        [
            'company' => new Tests\Support\PermissiveStub(['name' => 'Acme Hosting']),
            'logo_url' => 'https://example.com/logo.png',
            'dark_logo_url' => 'https://example.com/logo-dark.png',
            'link_url' => '/',
            'show_mobile_name' => true,
        ],
    );

    expect($html)->toContain('<span class="d-sm-none">Acme Hosting</span>');
});

test('shared company logo partial renders the client fallback span without a class when there is no logo', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_company_logo.html.twig',
        [
            'company' => new Tests\Support\PermissiveStub(['name' => 'Acme Hosting']),
            'logo_url' => null,
            'dark_logo_url' => null,
        ],
    );

    expect($html)
        ->toContain('<span>Acme Hosting</span>')
        ->not->toContain('<img');
});

/*
 * partial_status_name_shared.html.twig: admin's status_name() macro was a
 * strict superset of client's (more status branches, plus a null/empty
 * guard client's version lacked) - and client's own copy turned out to
 * have zero callers anywhere in the codebase, not even client's own
 * status_badge() macro, so standardizing on admin's version changes no
 * existing caller's output.
 */

test('shared status_name partial renders a dash for a null or empty status', function (): void {
    expect(trim((new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_status_name_shared.html.twig',
        ['status' => null],
    )))->toBe('-');

    expect(trim((new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_status_name_shared.html.twig',
        ['status' => ''],
    )))->toBe('-');
});

test('shared status_name partial translates a known status shared by both themes', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_status_name_shared.html.twig',
        ['status' => 'failed_renew'],
    );

    expect(trim($html))->toBe('Failed Renewal');
});

test('shared status_name partial translates a status only admin\'s version used to know about', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_status_name_shared.html.twig',
        ['status' => 'completed'],
    );

    expect(trim($html))->toBe('Completed');
});

test('shared status_name partial title-cases and translates an unrecognized status as-is', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/shared/html/partial_status_name_shared.html.twig',
        ['status' => 'some_custom_status'],
    );

    expect(trim($html))->toBe('Some Custom Status');
});

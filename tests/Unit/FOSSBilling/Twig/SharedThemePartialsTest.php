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

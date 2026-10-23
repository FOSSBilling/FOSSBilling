<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\StrictTemplateRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function noticeTwig(string $theme): Environment
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html',
        PATH_MODS . '/Widgets/templates/' . $theme . '/widgets',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));

    return $twig;
}

test('both notice entrypoints escape copy and actions and preserve Tabler dismissal', function (string $theme): void {
    $html = noticeTwig($theme)->render('partial_notice.html.twig', [
        'notice_type' => 'error', 'notice_title' => 'Problem <test>', 'notice_message' => '<script>alert(1)</script>',
        'notice_dismissible' => true, 'notice_role' => 'alert', 'notice_icon' => true,
        'notice_actions' => [['link' => '/support?topic=one&context=two', 'text' => 'Get <help>']],
    ]);
    expect($html)->toContain('alert-danger', 'data-fb-notice', 'role="alert"', 'aria-hidden="true"', '#alert-triangle',
        'Problem &lt;test&gt;', '&lt;script&gt;', 'Get &lt;help&gt;', 'topic=one&amp;context=two', 'data-bs-dismiss="alert"', 'aria-label="Close"')
        ->not->toContain('<script>', '<test>');
})->with(['client', 'admin']);

test('static notices stay out of live regions and keep explicit status feedback opt-in', function (string $theme): void {
    $twig = noticeTwig($theme);
    $static = $twig->render('partial_notice.html.twig', ['notice_message' => 'Information']);
    expect($static)->not->toContain('role=', 'aria-live=', 'btn-close', '<svg');
    expect($twig->render('partial_notice.html.twig', ['notice_type' => 'success', 'notice_role' => 'status', 'notice_message' => 'Saved']))
        ->toContain('alert-success', 'role="status"');
})->with(['client', 'admin']);

test('legacy client flash messages retain their classes and message order', function (): void {
    $html = noticeTwig('client')->render('partial_message.html.twig', ['flashes' => ['error' => ['First <message>', 'Second'], 'notice' => ['Details']]]);
    expect($html)->toContain('h-block error', 'h-block notice', 'alert-danger', 'alert-info', 'First &lt;message&gt;', 'role="alert"', 'role="status"');
    expect(strpos($html, 'First'))->toBeLessThan(strpos($html, 'Second'));
});

test('widget error entrypoints share diagnostic rendering with escaped theme-aware details', function (string $theme): void {
    $html = noticeTwig($theme)->render('mod_widgets_error.html.twig', [
        'widget' => ['slot' => 'content <before>', 'mod_name' => 'Example', 'template' => 'widget'],
        'error' => '<script>bad()</script>' . str_repeat('LongDiagnostic', 30),
    ]);
    expect($html)->toContain('data-fb-notice', 'role="alert"', 'content &lt;before&gt;', 'widget.html.twig', '&lt;script&gt;bad()&lt;/script&gt;', 'bg-body-tertiary', 'text-danger-emphasis')
        ->not->toContain('bg-light', '<script>');
})->with(['client', 'admin']);

test('configured dashboard notice keeps branding content CTA and widget hooks', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(PATH_MODS . '/Index/templates/client/mod_index_dashboard.html.twig', [
        'client' => null, 'settings' => new Tests\Support\PermissiveStub([
            'showcase_enabled' => true, 'showcase_text' => 'Custom company notice',
            'showcase_button_url' => '/company/status', 'showcase_button_title' => 'Company Status',
        ]),
    ]);
    expect($html)->toContain('data-fb-notice', 'Custom company notice', 'href="/company/status"', 'Company Status');
});

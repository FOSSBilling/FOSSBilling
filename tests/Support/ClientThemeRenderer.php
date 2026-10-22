<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

// cspell:ignore Deutsch

namespace Tests\Support;

use FOSSBilling\Twig\Enum\AppArea;
use FOSSBilling\Twig\TwigLoader;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;

/** Renders the client shell with deterministic API responses, without a database. */
final class ClientThemeRenderer
{
    public static function render(array $overrides = [], string $template = 'layout_default.html.twig'): string
    {
        $twig = new Environment(new TwigLoader(AppArea::CLIENT,
            PATH_THEMES . '/default/client', PATH_THEMES . '/default/shared/html',
        ), ['strict_variables' => true]);
        $twig->addFilter(new TwigFilter('trans', fn (string $text): string => $text));
        $twig->addFilter(new TwigFilter('format_datetime', fn (?string $date): string => $date ?? ''));
        $twig->addFilter(new TwigFilter('url', fn (string $path): string => '/' . ltrim($path, '/')));
        $twig->addFilter(new TwigFilter('asset_url', fn (string $path): string => '/themes/default/client/assets/' . $path));
        $twig->addFilter(new TwigFilter('public_asset_url', fn (string $path): string => '/public/assets/' . $path));
        $twig->addFilter(new TwigFilter('script_tag', fn (string $path): string => '<script src="' . $path . '"></script>', ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('format_currency', fn ($value): string => '$' . number_format((float) $value, 2)));
        $twig->addFunction(new TwigFunction('render_widgets', fn (string $slot): string => '<!-- widget:' . $slot . ' -->', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('svg_sprite', fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('avatar', fn (): string => '<span class="db-avatar rounded-circle p-1">JD</span>', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('debug_bar_render_head', fn (): string => ''));
        $twig->addFunction(new TwigFunction('debug_bar_render', fn (): string => ''));
        $settings = json_decode(file_get_contents(PATH_THEMES . '/default/client/config/settings_data.json.example'), true)['presets']['Default'];
        $context = array_replace([
            'client' => new PermissiveStub(['support_notification_get_list' => ['unread_count' => 0, 'latest_id' => 0, 'list' => []]]),
            'flashes' => [],
            'profile' => ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test', 'company' => 'Acme', 'currency' => 'USD', 'balance' => 42],
            'active_menu' => 'invoices',
            'current_active_menu' => 'invoices',
            'support_url' => '/support',
            'settings' => $settings,
            'guest' => new PermissiveStub([
                'system_locale' => 'en_GB',
                'system_get_pending_messages' => [],
                'system_current_url' => '/invoice',
                'system_company' => new PermissiveStub(['name' => 'Acme Hosting', 'signature' => 'Acme Hosting', 'logo_url' => '/logo.svg', 'logo_url_dark' => '/logo-dark.svg', 'favicon_url' => '', 'bank_info_pagebottom' => false]),
                'extension_languages' => [['locale' => 'en_GB', 'title' => 'English'], ['locale' => 'de_DE', 'title' => 'Deutsch']],
                'support_guest_tickets_enabled' => true,
                'support_kb_enabled' => true,
                'invoice_funds_enabled' => true,
                'extension_is_on' => false,
            ]),
        ], $overrides);
        $context['settings'] = array_replace($settings, $overrides['settings'] ?? []);

        if (in_array($template, ['layout_default.html.twig', 'layout_public.html.twig'], true)) {
            return $twig->createTemplate('{% extends "' . $template . '" %}{% block meta_title %}Client area{% endblock %}{% block content %}<div class="card"><div class="card-body"><h1>Invoices</h1><p>View and manage your invoices.</p></div></div>{% endblock %}')->render($context);
        }

        return $twig->render($template, $context);
    }
}

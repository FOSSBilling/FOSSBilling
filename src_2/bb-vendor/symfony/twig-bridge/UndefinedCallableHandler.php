<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

use Symfony\Bundle\FullStack;
use Twig\Error\SyntaxError;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @internal
 */
class UndefinedCallableHandler
{
    private const FILTER_COMPONENTS = [
        'humanize' => 'form',
        'trans' => 'translation',
        'yaml_encode' => 'yaml',
        'yaml_dump' => 'yaml',
    ];

    private const FUNCTION_COMPONENTS = [
        'asset' => 'asset',
        'asset_version' => 'asset',
        'dump' => 'debug-bundle',
        'encore_entry_link_tags' => 'webpack-encore-bundle',
        'encore_entry_script_tags' => 'webpack-encore-bundle',
        'expression' => 'expression-language',
        'form_widget' => 'form',
        'form_errors' => 'form',
        'form_label' => 'form',
        'form_help' => 'form',
        'form_row' => 'form',
        'form_rest' => 'form',
        'form' => 'form',
        'form_start' => 'form',
        'form_end' => 'form',
        'csrf_token' => 'form',
        'logout_url' => 'security-http',
        'logout_path' => 'security-http',
        'is_granted' => 'security-core',
        'link' => 'web-link',
        'preload' => 'web-link',
        'dns_prefetch' => 'web-link',
        'preconnect' => 'web-link',
        'prefetch' => 'web-link',
        'prerender' => 'web-link',
        'workflow_can' => 'workflow',
        'workflow_transitions' => 'workflow',
        'workflow_has_marked_place' => 'workflow',
        'workflow_marked_places' => 'workflow',
    ];

    private const FULL_STACK_ENABLE = [
        'form' => 'enable "framework.form"',
        'security-core' => 'add the "SecurityBundle"',
        'security-http' => 'add the "SecurityBundle"',
        'web-link' => 'enable "framework.web_link"',
        'workflow' => 'enable "framework.workflows"',
    ];

    public static function onUndefinedFilter(string $name): TwigFilter|false
    {
        if (!isset(self::FILTER_COMPONENTS[$name])) {
            return false;
        }

        throw new SyntaxError(self::onUndefined($name, 'filter', self::FILTER_COMPONENTS[$name]));
    }

    public static function onUndefinedFunction(string $name): TwigFunction|false
    {
        if (!isset(self::FUNCTION_COMPONENTS[$name])) {
            return false;
        }

        if ('webpack-encore-bundle' === self::FUNCTION_COMPONENTS[$name]) {
            return new TwigFunction($name, static function () { return ''; });
        }

        throw new SyntaxError(self::onUndefined($name, 'function', self::FUNCTION_COMPONENTS[$name]));
    }

    private static function onUndefined(string $name, string $type, string $component): string
    {
        if (class_exists(FullStack::class) && isset(self::FULL_STACK_ENABLE[$component])) {
            return sprintf('Did you forget to %s? Unknown %s "%s".', self::FULL_STACK_ENABLE[$component], $type, $name);
        }

        return sprintf('Did you forget to run "composer require symfony/%s"? Unknown %s "%s".', $component, $type, $name);
    }
}

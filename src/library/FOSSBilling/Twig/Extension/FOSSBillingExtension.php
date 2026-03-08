<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig\Extension;

use FOSSBilling\Environment as AppEnvironment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

class FOSSBillingExtension
{
    public function __construct(private ?\Pimple\Container $di)
    {
    }

    private function getLoadedAssets(): array
    {
        if (!$this->di->offsetExists('loaded_assets')) {
            $this->di['loaded_assets'] = [];
        }

        return $this->di['loaded_assets'];
    }

    private function markAssetAsLoaded(string $path): void
    {
        $assets = $this->getLoadedAssets();
        $assets[] = $this->normalizeAssetPath($path);
        $this->di['loaded_assets'] = $assets;
    }

    private function isAssetLoaded(string $path): bool
    {
        $normalizedPath = $this->normalizeAssetPath($path);
        $loadedAssets = $this->getLoadedAssets();

        return in_array($normalizedPath, $loadedAssets, true);
    }

    private function normalizeAssetPath(string $path): string
    {
        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path);
            $path = $parsed['path'] ?? $path;
        }

        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        return ltrim($path, '/\\');
    }

    #[AsTwigFunction('render_widgets', isSafe: ['html'], needsEnvironment: true)]
    public function renderWidgets(Environment $env, string $slot, array $context = []): string
    {
        $widgets = $this->di['mod_service']('Widgets')->getSlotWidgets($slot);

        if (empty($widgets)) {
            return '';
        }

        $output = '';

        foreach ($widgets as $widget) {
            try {
                $templateName = 'widgets/' . $widget['template'] . '.html.twig';
                $output .= $env->render($templateName, $context);
            } catch (\Throwable $e) {
                $output .= $env->render('widgets/mod_widgets_error.html.twig', array_merge($context, [
                    'widget' => [
                        'slot' => $slot,
                        'mod_name' => $widget['module'],
                        'template' => $widget['template'],
                    ],
                    'error' => AppEnvironment::isDevelopment() ? $e->getMessage() : null,
                ]));
            }
        }

        return $output;
    }

    #[AsTwigFunction('svg_sprite', isSafe: ['html'], needsEnvironment: true)]
    public function svgSprite(Environment $env): string
    {
        $globals = $env->getGlobals();
        $themeCode = $globals['theme']['code'] ?? null;

        if ($themeCode === null) {
            return '';
        }

        $spritePath = Path::join(PATH_THEMES, $themeCode, 'assets/build/symbol/icons-sprite.svg');
        $filesystem = new Filesystem();

        if (!$filesystem->exists($spritePath)) {
            return '';
        }

        return $filesystem->readFile($spritePath);
    }

    #[AsTwigFilter('asset_url', isSafe: ['html'], needsEnvironment: true)]
    public function assetUrl(Environment $env, $asset): string
    {
        $globals = $env->getGlobals();
        $themeCode = $globals['current_theme'] ?? ($globals['theme']['code'] ?? null);

        return SYSTEM_URL . 'themes/' . $themeCode . '/assets/' . $asset;
    }

    #[AsTwigFilter('daysleft')]
    public function daysleft(string $dateTime): int
    {
        $timeLeft = strtotime($dateTime) - time();

        return intval($timeLeft / 86400);
    }

    #[AsTwigFilter('file_size')]
    public function fileSize(int $size): string
    {
        return \FOSSBilling\Tools::humanReadableBytes($size);
    }

    #[AsTwigFilter('hash')]
    public function hash($value, $algo = 'xxh128'): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new \InvalidArgumentException(sprintf('Hash algorithm "%s" is not supported.', $algo));
        }

        return hash($algo, (string) $value);
    }

    #[AsTwigFilter('script_tag', isSafe: ['html'])]
    public function scriptTag($path): string
    {
        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        return sprintf('<script src="%s?%s"></script>', $path, \FOSSBilling\Version::VERSION);
    }

    #[AsTwigFilter('stylesheet_tag', isSafe: ['html'])]
    public function stylesheetTag($path, $media = 'screen'): string
    {
        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, \FOSSBilling\Version::VERSION, $media);
    }

    #[AsTwigFilter('timeago')]
    public function timeago(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return '';
        }

        $timeAgo = time() - $timestamp;
        $tokens = [
            315_705_600 => __trans('decade'),
            31_570_560 => __trans('year'),
            2_630_880 => __trans('month'),
            604_800 => __trans('week'),
            86400 => __trans('day'),
            3600 => __trans('hour'),
            60 => __trans('minute'),
            1 => __trans('second'),
        ];
        foreach ($tokens as $unit => $text) {
            if ($timeAgo < $unit) {
                continue;
            }
            $numberOfUnits = floor($timeAgo / $unit);

            return sprintf('%d %s%s', $numberOfUnits, $text, ($numberOfUnits > 1) ? 's' : '');
        }

        return '';
    }

    #[AsTwigFilter('trans')]
    public function trans(?string $text): string
    {
        return __trans($text);
    }

    #[AsTwigFilter('truncate')]
    public function truncate(string $text, int $length = 30, string $suffix = '...'): string
    {
        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . $suffix;
        }

        return $text;
    }

    #[AsTwigFilter('url', isSafe: ['html'], needsEnvironment: true)]
    public function url(Environment $env, string $path, ?array $query = null, bool $detectAppArea = true): string
    {
        $globals = $env->getGlobals();

        if ($detectAppArea && isset($globals['app_area']) && $globals['app_area'] === 'admin') {
            return $this->di['url']->adminLink($path, $query);
        }

        return $this->di['url']->link($path, $query);
    }
}

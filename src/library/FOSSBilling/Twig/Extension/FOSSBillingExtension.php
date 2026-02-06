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

use Twig\Attribute\AsTwigFilter;
use Twig\Environment;

class FOSSBillingExtension
{
    private ?\Pimple\Container $di = null;

    /**
     * FOSSBillingExtension constructor.
     *
     * @param ?\Pimple\Container $di dependency injection container
     */
    public function __construct(?\Pimple\Container $di)
    {
        $this->di = $di;
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


    /**
     * Get API URL for a given path and query parameters.
     * Automatically detects the role, where possible and no role is specified,
     * based on Twig global. Otherwise, defaults to 'guest'.
     *
     * @param Environment $env    twig environment
     * @param string      $action API action
     * @param array|null  $query  URL query parameters
     * @param string|null $role   user role (admin, client, guest)
     *
     * @return string the generated API URL
     */
    #[AsTwigFilter('api_url', isSafe: ['html'], needsEnvironment: true)]
    public function apiUrl(Environment $env, string $action, ?array $query = null, ?string $role = null): string
    {
        $globals = $env->getGlobals();

        if (is_null($role) && isset($globals['app_area'])) {
            $role = $globals['app_area'];
        }

        if (!in_array($role, ['admin', 'client', 'guest'])) {
            $role = 'guest';
        }

        return $this->di['url']->link("api/{$role}/{$action}", $query, $role);
    }

    /**
     * Get the URL for an asset in the current theme.
     *
     * @param Environment $env   twig environment
     * @param string      $asset asset path relative to the theme's assets directory
     *
     * @return string the generated asset URL
     */
    #[AsTwigFilter('asset_url', isSafe: ['html'], needsEnvironment: true)]
    public function assetUrl(Environment $env, $asset): string
    {
        $globals = $env->getGlobals();

        return SYSTEM_URL . 'themes/' . $globals['current_theme'] . '/assets/' . $asset;
    }

    /**
     * Get the number of days left until a given date.
     *
     * @param string $dateTime date and time in ISO 8601 format
     *
     * @return int the number of days left until the date
     */
    #[AsTwigFilter('daysleft')]
    public function daysleft(string $dateTime): int
    {
        $timeLeft = strtotime($dateTime) - time();

        return intval($timeLeft / 86400);
    }

    /**
     * Get the file size in a human-readable format.
     *
     * @param int $size file size in bytes
     *
     * @return string the human-readable file size
     */
    #[AsTwigFilter('file_size')]
    public function fileSize(int $size): string
    {
        return \FOSSBilling\Tools::humanReadableBytes($size);
    }

    /**
     * Hash a given value using the specified algorithm.
     *
     * @param mixed  $value the value to hash
     * @param string $algo  the hashing algorithm to use (default: 'xxh128')
     *
     * @return string the hashed value
     *
     * @throws \InvalidArgumentException if the specified hashing algorithm is not supported
     */
    #[AsTwigFilter('hash')]
    public function hash($value, $algo = 'xxh128'): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new \InvalidArgumentException(sprintf('Hash algorithm "%s" is not supported.', $algo));
        }
        return hash($algo, (string) $value);
    }

    /**
     * Generate a script tag for a given asset path, ensuring that the same asset is not included multiple times.
     *
     * @param string $path the path of the asset
     *
     * @return string the generated script tag or an empty string if the asset has already been included
     */
    #[AsTwigFilter('script_tag', isSafe: ['html'])]
    public function scriptTag($path): string
    {
        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        return sprintf('<script src="%s?%s"></script>', $path, \FOSSBilling\Version::VERSION);
    }

    /**
     * Generate a stylesheet link tag for a given asset path, ensuring that the same asset is not included multiple times.
     *
     * @param string $path  the path of the asset
     * @param string $media the media attribute for the link tag (default: 'screen')
     *
     * @return string the generated link tag or an empty string if the asset has already been included
     */
    #[AsTwigFilter('stylesheet_tag', isSafe: ['html'])]
    public function stylesheetTag($path, $media = 'screen'): string
    {
        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, \FOSSBilling\Version::VERSION, $media);
    }

    /**
     * Get the time ago in a human-readable format.
     *
     * @param string $dateTime date and time in ISO 8601 format
     *
     * @return string the time ago in a human-readable format
     */
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

    /**
     * Translate a given text using the translation function.
     *
     * @param string|null $text the text to translate
     *
     * @return string the translated text
     */
    #[AsTwigFilter('trans')]
    public function trans(?string $text): string
    {
        return __trans($text);
    }

    /**
     * Truncate a string to a specified length and append a suffix.
     *
     * @param string $text   the text to truncate
     * @param int    $length the maximum length of the truncated string
     * @param string $suffix the suffix to append if the text is truncated
     *
     * @return string the truncated string
     */
    #[AsTwigFilter('truncate')]
    public function truncate(string $text, int $length = 30, string $suffix = '...'): string
    {
        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . $suffix;
        }

        return $text;
    }

    /**
     * Generate URL for a given path and query parameters. Detects the app area
     * (admin or client) automatically, unless specified otherwise in which
     * case uses base URL.
     *
     * @param Environment $env           twig environment
     * @param string      $path          URL path
     * @param array|null  $query         URL query parameters
     * @param bool        $detectAppArea Whether to detect the app area
     *                                   (admin or client). Default true.
     *
     * @return string the generated URL
     */
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

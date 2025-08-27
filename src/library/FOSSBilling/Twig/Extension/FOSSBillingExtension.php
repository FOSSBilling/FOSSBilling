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
     * @param ?\Pimple\Container $di
     */
    public function __construct(?\Pimple\Container $di)
    {
        $this->di = $di;
    }

    /**
     * Get API URL for a given path and query parameters.
     * Automatically detects the role, where possible and no role is specified,
     * based on Twig global. Otherwise, defaults to 'guest'.
     *
     * @param \Twig\Environment     $env    Twig environment.
     * @param string                $action API action.
     * @param array|null            $query  URL query parameters.
     * @param string|null           $role   User role (admin, client, guest).
     *
     * @return string The generated API URL.
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
     * Get the number of days left until a given date.
     *
     * @param string $dateTime Date and time in ISO 8601 format.
     *
     * @return int The number of days left until the date.
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
     * @param int $size File size in bytes.
     *
     * @return string The human-readable file size.
     */
    #[AsTwigFilter('file_size')]
    public function fileSize(int $size): string
    {
        return \FOSSBilling\Tools::humanReadableBytes($size);
    }

    /**
     * Get Gravatar URL for a given email address.
     *
     * @param string    $email  Email address.
     * @param int       $size   Size of Gravatar image (default is 20).
     *
     * @return string The Gravatar URL.
     */
    #[AsTwigFilter('gravatar')]
    public function gravatar(string $email, int $size = 20): string
    {
        $hash = hash('sha256', strtolower(trim($email)));
        return (empty($email)) ? '' : "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp&r=g";
    }

    /**
     * Get the time ago in a human-readable format.
     *
     * @param string $dateTime Date and time in ISO 8601 format.
     *
     * @return string The time ago in a human-readable format.
     */
    #[AsTwigFilter('timeago')]
    public function timeago(string $dateTime): string
    {
        $timeAgo = time() - strtotime($dateTime);
        $timeAgo = ($timeAgo < 1) ? 1 : $timeAgo;
        $tokens = [
            315_705_600 => __trans('decade'),
            31_570_560 => __trans('year'),
            2_630_880 => __trans('month'),
            604_800 => __trans('week'),
            86400 => __trans('day'),
            3600 => __trans('hour'),
            60 => __trans('minute'),
            1 => __trans('second')
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
     * @param string|null $text The text to translate.
     *
     * @return string The translated text.
     */
    #[AsTwigFilter('trans')]
    public function trans(string|null $text): string
    {
        return __trans($text);
    }

    /**
     * Truncate a string to a specified length and append a suffix.
     *
     * @param string    $text   The text to truncate.
     * @param int       $length The maximum length of the truncated string.
     * @param string    $suffix The suffix to append if the text is truncated.
     *
     * @return string The truncated string.
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
     * @param \Twig\Environment $env            Twig environment.
     * @param string            $path           URL path.
     * @param array|null        $query          URL query parameters.
     * @param bool              $detectAppArea  Whether to detect the app area
     *                                          (admin or client). Default true.
     *
     * @return string The generated URL.
     */
    #[AsTwigFilter('url', isSafe: ['html'], needsEnvironment: true)]
    public function url(Environment $env, string $path, ?array $query = null, bool $detectAppArea = true): string
    {
        $globals = $env->getGlobals();

        if ($detectAppArea && isset($globals['app_area']) && $globals['app_area'] === 'admin') {
            return $this->di['url']->adminLink($path, $query);
        } else {
            return $this->di['url']->link($path, $query);
        }
    }
}

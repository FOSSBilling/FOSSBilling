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
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

class ApiExtension
{
    public function __construct(private ?\Pimple\Container $di)
    {
    }

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

    #[AsTwigFunction('fb_api', isSafe: ['html'])]
    public function fbApi(array $config): string
    {
        $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $json = str_replace("'", "\\'", $json);

        return 'data-fb-api=\'' . $json . '\'';
    }

    #[AsTwigFunction('fb_api_form', isSafe: ['html'])]
    public function fbApiForm(array $config = []): string
    {
        $tag = $config['tag'] ?? null;
        $content = $config['content'] ?? '';
        $action = $config['action'] ?? null;
        unset($config['tag'], $config['content'], $config['action']);

        $config['type'] = 'form';
        $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $json = str_replace("'", "\\'", $json);
        $attr = 'method="post" data-fb-api=\'' . $json . '\'';

        if ($tag === 'form') {
            $actionAttr = $action ? 'action="' . htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') . '" ' : '';

            return '<form ' . $actionAttr . $attr . '>' . $content . '</form>';
        }

        return $attr;
    }

    #[AsTwigFunction('fb_api_link', isSafe: ['html'])]
    public function fbApiLink(array $config): string
    {
        $tag = $config['tag'] ?? null;
        $content = $config['content'] ?? '';
        $href = $config['href'] ?? null;
        unset($config['tag'], $config['content']);

        $config['type'] = 'link';
        $attr = $this->fbApi($config);

        if ($tag === 'a') {
            $hrefAttr = $href ? 'href="' . htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8') . '" ' : '';

            return '<a ' . $hrefAttr . $attr . '>' . $content . '</a>';
        }

        $hrefAttr = $href ? 'href="' . htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8') . '" ' : '';

        return $hrefAttr . $attr;
    }
}

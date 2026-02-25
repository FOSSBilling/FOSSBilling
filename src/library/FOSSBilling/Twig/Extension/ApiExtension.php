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
    private ?\Pimple\Container $di = null;

    public function __construct(?\Pimple\Container $di)
    {
        $this->di = $di;
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
     * Core function for generating data-fb-api attributes.
     *
     * @param array $config API configuration
     *
     * @return string HTML attribute string
     *
     * @throws \RuntimeException on invalid configuration
     */
    #[AsTwigFunction('fb_api', isSafe: ['html'])]
    public function fbApi(array $config): string
    {
        $config = $this->validateFbApiConfig($config);

        try {
            $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            error_log('fb_api: failed to encode JSON: ' . $e->getMessage());

            return 'data-fb-api=\'{}\'';
        }

        return 'data-fb-api=\'' . $json . '\'';
    }

    /**
     * Generate data-fb-api attribute for forms, or full <form> tag.
     *
     * Usage - just attribute (backward compatible):
     *   <form method="post" action="{{ 'api/admin/order/update'|link }}" {{ fb_api_form({reload: true}) }}>
     *       ...fields...
     *   </form>
     *
     * Usage - full tag (simplified):
     *   {{ fb_api_form({tag: 'form', action: 'api/admin/order/update'|link, reload: true, content: '...fields...'}) }}
     *
     * @param array $config Config with optional 'tag', 'action', 'content', and other API options
     *
     * @return string HTML attribute string or full <form> tag
     *
     * @throws \RuntimeException on invalid configuration
     */
    #[AsTwigFunction('fb_api_form', isSafe: ['html'])]
    public function fbApiForm(array $config = []): string
    {
        $tag = $config['tag'] ?? null;
        $content = $config['content'] ?? '';
        $action = $config['action'] ?? null;
        unset($config['tag'], $config['content'], $config['action']);

        $config['type'] = 'form';

        try {
            $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new \RuntimeException('fb_api_form: failed to encode JSON: ' . $e->getMessage());
        }

        $attr = 'method="post" data-fb-api=\'' . $json . '\'';

        if ($tag === 'form') {
            $actionAttr = $action ? 'action="' . htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') . '" ' : '';

            return '<form ' . $actionAttr . $attr . '>' . $content . '</form>';
        }

        return $attr;
    }

    /**
     * Generate data-fb-api attribute for links, or full <a> tag.
     *
     * Usage - just attribute (backward compatible):
     *   <a href="{{ 'api/admin/client/delete'|link({id: client.id}) }}" {{ fb_api_link({reload: true}) }}>Delete</a>
     *
     * Usage - full tag (eliminates duplicate href):
     *   {{ fb_api_link({tag: 'a', href: 'api/admin/client/delete'|link({id: client.id}), reload: true, content: '<svg class="icon"><use xlink:href="#delete"/></svg><span>Delete</span>'}) }}
     *
     * @param array $config Config with optional 'tag', 'href', 'content', and other API options
     *
     * @return string HTML attribute string or full <a> tag
     *
     * @throws \RuntimeException on invalid configuration
     */
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

    /**
     * Validate fb_api configuration and normalize values.
     *
     * @param array $config Raw configuration
     *
     * @return array Validated and normalized configuration
     *
     * @throws \RuntimeException on invalid options
     */
    private function validateFbApiConfig(array $config): array
    {
        $allowedKeys = ['type', 'href', 'endpoint', 'params', 'message', 'redirect', 'reload', 'modal', 'callback'];

        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowedKeys, true)) {
                $suggestion = $this->getClosestMatch($key, $allowedKeys);
                $hint = $suggestion ? " Did you mean: '{$suggestion}'?" : '';

                throw new \RuntimeException("fb_api: unknown option '{$key}'{$hint}");
            }
        }

        if (isset($config['modal'])) {
            $config['modal'] = $this->validateModalConfig($config['modal']);
        }

        foreach (['href', 'message', 'redirect', 'callback'] as $key) {
            if (isset($config[$key]) && !is_string($config[$key])) {
                throw new \RuntimeException(sprintf('fb_api: "%s" must be a string', $key));
            }
        }

        if (isset($config['reload']) && !is_bool($config['reload'])) {
            throw new \RuntimeException('fb_api: "reload" must be a boolean');
        }

        if (isset($config['params']) && !is_array($config['params'])) {
            throw new \RuntimeException('fb_api: "params" must be an array');
        }

        return $config;
    }

    /**
     * Validate modal configuration.
     *
     * @param array $modal Modal configuration
     *
     * @return array Validated modal configuration
     *
     * @throws \RuntimeException on invalid modal options
     */
    private function validateModalConfig(array $modal): array
    {
        $allowedModalKeys = ['type', 'title', 'content', 'button', 'buttonColor', 'label', 'value', 'key'];

        foreach (array_keys($modal) as $key) {
            if (!in_array($key, $allowedModalKeys, true)) {
                throw new \RuntimeException("fb_api.modal: unknown option '{$key}'");
            }
        }

        $allowedTypes = ['confirm', 'danger', 'prompt'];
        $modalType = $modal['type'] ?? null;

        if ($modalType === null) {
            throw new \RuntimeException("fb_api.modal: 'type' is required");
        }

        if (!in_array($modalType, $allowedTypes, true)) {
            throw new \RuntimeException("fb_api.modal: invalid type '{$modalType}'. Allowed: " . implode(', ', $allowedTypes));
        }

        if ($modalType === 'prompt' && !isset($modal['key'])) {
            throw new \RuntimeException("fb_api.modal: 'key' is required for 'prompt' type");
        }

        return $modal;
    }

    /**
     * Find closest matching string from a list (for helpful error messages).
     *
     * @param string $input   User input
     * @param array  $options Available options
     *
     * @return string|null Closest match or null
     */
    private function getClosestMatch(string $input, array $options): ?string
    {
        $closest = null;
        $minDistance = PHP_INT_MAX;

        foreach ($options as $option) {
            $distance = levenshtein($input, $option);
            if ($distance < $minDistance && $distance <= 3) {
                $minDistance = $distance;
                $closest = $option;
            }
        }

        return $closest;
    }
}

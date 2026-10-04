<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Pimple\Container;

class Url implements InjectionAwareInterface
{
    protected ?Container $di = null;

    private string $baseUri = '';

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function setBaseUri(string $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Generates a URL.
     */
    public function get(string $uri): string
    {
        return $this->baseUri . $uri;
    }

    public function link(?string $uri = null, ?array $params = null): string
    {
        $uri = trim($uri ?? '', '/');
        $params ??= [];
        $link = $this->baseUri . $uri;
        if ($params !== []) {
            $link .= '?' . http_build_query($params);
        }

        return $link;
    }

    public function adminLink(?string $uri = null, ?array $params = null): string
    {
        $uri = trim($uri ?? '', '/');
        $uri = ADMIN_PREFIX . '/' . $uri;

        return $this->link($uri, $params);
    }

    public static function normalizeLinkPath(?string $uri = null): string
    {
        $uri = trim((string) $uri);
        if ($uri === '' || $uri === '/') {
            return '';
        }

        $uri = ltrim($uri, '/');
        $adminPrefix = defined('ADMIN_PREFIX') ? trim((string) ADMIN_PREFIX, '/') : '';
        if ($adminPrefix !== '' && str_starts_with($uri, $adminPrefix . '/')) {
            return substr($uri, strlen($adminPrefix) + 1);
        }

        if ($uri === $adminPrefix) {
            return '';
        }

        return $uri;
    }
}

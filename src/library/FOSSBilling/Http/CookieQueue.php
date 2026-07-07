<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Http;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collects cookies during request processing and attaches them to the Response
 * before emission, replacing direct setcookie() / headers_sent() calls.
 */
final class CookieQueue
{
    /** @var list<Cookie> */
    private array $cookies = [];

    public function queue(
        string $name,
        string $value = '',
        int $expires = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = false,
        ?string $sameSite = null,
    ): void {
        $this->cookies[] = new Cookie(
            $name,
            $value,
            $expires,
            $path,
            $domain,
            $secure,
            $httpOnly,
            false,
            $sameSite,
        );
    }

    public function applyToResponse(Response $response): void
    {
        foreach ($this->cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }
}

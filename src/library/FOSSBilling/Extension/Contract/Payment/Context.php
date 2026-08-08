<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Everything a payment gateway is allowed to ask of FOSSBilling.
 *
 * Gateways receive this instead of the dependency injection container, so that
 * the surface they depend on is declared, reviewable and versioned rather than
 * being "whatever core happens to have".
 */
interface Context
{
    public function logger(): LoggerInterface;

    public function httpClient(): HttpClientInterface;

    /**
     * Turn a path into an absolute URL on this installation.
     */
    public function url(string $path): string;

    /**
     * Render a merchant-configured template string (e.g. Custom's payment
     * instructions) in the sandboxed adapter Twig environment.
     *
     * @param array<string, mixed> $vars
     */
    public function renderTemplate(string $template, array $vars): string;
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use FOSSBilling\Extension\Contract\Payment\Context;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Serves the payment gateway context out of the container.
 *
 * Every container access a gateway used to make itself now lives here, behind
 * the narrow surface declared by the Context interface.
 */
final class PaymentContext implements Context
{
    public function __construct(private readonly \Pimple\Container $di)
    {
    }

    public function url(string $path): string
    {
        return $this->di['tools']->url($path);
    }

    public function logger(): LoggerInterface
    {
        return $this->di['extension_logger'];
    }

    public function httpClient(): HttpClientInterface
    {
        return $this->di['http_client'];
    }

    public function renderTemplate(string $template, array $vars): string
    {
        return $this->di['mod_service']('System')->renderAdapterTplString($template, $vars);
    }
}

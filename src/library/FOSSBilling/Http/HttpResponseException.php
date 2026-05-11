<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Http;

use Symfony\Component\HttpFoundation\Response;

final class HttpResponseException extends \RuntimeException
{
    public function __construct(private readonly Response $response)
    {
        parent::__construct('', $response->getStatusCode());
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}

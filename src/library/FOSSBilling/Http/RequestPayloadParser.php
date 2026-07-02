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

use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;

final readonly class RequestPayloadParser
{
    /**
     * @throws \FOSSBilling\Exception
     */
    public function all(Request $request): array
    {
        try {
            return $request->getPayload()->all();
        } catch (JsonException $e) {
            $message = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            throw new \FOSSBilling\Exception('Malformed JSON input: :error', [':error' => $message], 400);
        }
    }
}

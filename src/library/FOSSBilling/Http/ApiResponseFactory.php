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

use FOSSBilling\Security\RateLimitException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApiResponseFactory
{
    private const array NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-cache, must-revalidate',
        'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
    ];

    public function create(mixed $data = null, ?\Exception $exception = null): JsonResponse
    {
        if ($exception instanceof \Exception) {
            return $this->error($exception);
        }

        return new JsonResponse(['result' => $data, 'error' => null], Response::HTTP_OK, self::NO_CACHE_HEADERS);
    }

    public function error(\Exception $exception): JsonResponse
    {
        $code = $exception->getCode() ?: 9999;
        $headers = self::NO_CACHE_HEADERS;

        if ($exception instanceof RateLimitException && $exception->hasRetryAfter()) {
            $headers['Retry-After'] = (string) $exception->getRetryAfterSeconds();
        }

        return new JsonResponse(
            ['result' => null, 'error' => ['message' => $exception->getMessage(), 'code' => $code]],
            $this->getStatusCode($code),
            $headers,
        );
    }

    private function getStatusCode(int|string $code): int
    {
        // Exception codes are not guaranteed to be integers (e.g. PDOException uses SQLSTATE strings),
        // so non-numeric codes never match one of the known application error codes below.
        if (is_string($code)) {
            if (!is_numeric($code)) {
                return Response::HTTP_OK;
            }
            $code = (int) $code;
        }

        return match (true) {
            in_array($code, [201, 202, 203, 204, 205, 206, 1002, 1004], true) => Response::HTTP_UNAUTHORIZED,
            $code === 403 => Response::HTTP_FORBIDDEN,
            $code === 740 => Response::HTTP_NOT_FOUND,
            $code === 429 => Response::HTTP_TOO_MANY_REQUESTS,
            $code === 503 => Response::HTTP_SERVICE_UNAVAILABLE,
            in_array($code, [701, 879], true) => Response::HTTP_BAD_REQUEST,
            default => Response::HTTP_OK,
        };
    }
}

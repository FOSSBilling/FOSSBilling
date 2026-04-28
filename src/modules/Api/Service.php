<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Api;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'hide_permissions' => true,
        ];
    }

    public function logRequest(?string $path = null): int
    {
        $request = $this->di['request'];
        $sql = '
            INSERT INTO api_request (ip, request, created_at)
            VALUES(:ip, :request, NOW())
        ';
        $values = [
            'ip' => $request->getClientIp(),
            'request' => $path ?? $_SERVER['REQUEST_URI'] ?? null,
        ];

        return (int) $this->di['db']->exec($sql, $values);
    }

    public function getRequestCount($since, $ip = null, ?string $requestPrefix = null): int
    {
        if (!is_numeric($since)) {
            $since = strtotime($since);
        }
        $sinceIso = date('Y-m-d H:i:s', $since);
        $values = [
            'since' => $sinceIso,
        ];
        $sql = 'SELECT COUNT(id) FROM api_request WHERE created_at > :since';

        if ($ip != null) {
            $sql .= ' AND ip = :ip';
            $values['ip'] = $ip;
        }

        if ($requestPrefix !== null) {
            $sql .= ' AND request LIKE :request_prefix';
            $values['request_prefix'] = $requestPrefix . '%';
        }

        return (int) $this->di['db']->getCell($sql, $values);
    }

    public function isRateLimited(string $ip, int $maxAttempts, int $timeSpanSeconds, ?string $requestPrefix = null): bool
    {
        return $this->getRequestCount(time() - $timeSpanSeconds, $ip, $requestPrefix) >= $maxAttempts;
    }

    public function getRemainingRequests(string $ip, int $maxAttempts, int $timeSpanSeconds, ?string $requestPrefix = null): int
    {
        $count = $this->getRequestCount(time() - $timeSpanSeconds, $ip, $requestPrefix);

        return max(0, $maxAttempts - $count);
    }

    public function pruneRequests(int $maxAgeSeconds = 7200): int
    {
        $sql = 'DELETE FROM api_request WHERE UNIX_TIMESTAMP() - :age > UNIX_TIMESTAMP(created_at)';

        return (int) $this->di['db']->exec($sql, ['age' => $maxAgeSeconds]);
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): void
    {
        $di = $event->getDi();

        try {
            $service = $di['mod_service']('api');
            $service->pruneRequests();
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}

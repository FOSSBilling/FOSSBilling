<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Doctrine;

use DebugBar\DataCollector\PDO\TraceablePDO;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;

/**
 * Trace at connection creation so DBAL and legacy PDO callers use the same proxy.
 * Attaching a collector to the native PDO later only captures prepared DBAL calls.
 */
final class DebugMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $pdo = $connection->getNativeConnection();
                if (!$pdo instanceof \PDO) {
                    return $connection;
                }

                // All supported drivers use DBAL's PDO connection. Keep their platform and
                // exception conversion, but route query(), exec() and transactions through
                // the same traceable PDO used by prepare() and direct PDO consumers.
                return new PDOConnection($pdo instanceof TraceablePDO ? $pdo : new TraceablePDO($pdo));
            }
        };
    }
}

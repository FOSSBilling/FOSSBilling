<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/** Applies equally to ORM flushes, raw DBAL SQL, prepared statements and isolated managers. */
final class PaymentFenceMiddleware implements Middleware
{
    public function __construct(private readonly string $driver)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->driver) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly string $driverName)
            {
                parent::__construct($driver);
            }

            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                return new PaymentFenceConnection(parent::connect($params), $this->driverName);
            }
        };
    }
}

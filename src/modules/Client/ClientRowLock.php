<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Client;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use FOSSBilling\Core\Doctrine\RowLock;

final class ClientRowLock
{
    /** Hold the client row until the surrounding transaction ends. */
    public static function acquire(Connection $db, int $clientId): bool
    {
        if ($db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $db->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['ticket-file-client:' . $clientId]);
        }
        if ($db->getDatabasePlatform() instanceof SQLitePlatform) {
            // A deferred SQLite transaction does not acquire a write lock on SELECT.
            $db->executeStatement('UPDATE client SET id = id WHERE id = ?', [$clientId]);
        }

        return $db->fetchOne('SELECT id FROM client WHERE id = ?' . RowLock::suffix($db), [$clientId]) !== false;
    }
}

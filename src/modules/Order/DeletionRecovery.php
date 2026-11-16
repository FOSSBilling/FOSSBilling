<?php

declare(strict_types=1);

namespace Box\Mod\Order;

use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Doctrine\RowLock;
use FOSSBilling\Core\Exception\InformationException;

/** Durable deletion intent, independent of editable order state and ORM caches. */
final class DeletionRecovery
{
    public const string META_NAME = 'deletion_state';
    public const string STARTED = 'started';
    public const string SERVICE_DELETED = 'service_deleted';

    public static function getState(Connection $connection, int $orderId, bool $currentRead = false): ?string
    {
        $state = $connection->fetchOne(
            "SELECT COALESCE(value, 'started') FROM client_order_meta WHERE client_order_id = :id AND name = :name" . ($currentRead && $connection->isTransactionActive() ? RowLock::suffix($connection) : ''),
            ['id' => $orderId, 'name' => self::META_NAME],
        );

        return $state === false || $state === null ? null : (string) $state;
    }

    public static function assertNotHeld(Connection $connection, int $orderId, bool $lockRow = false): void
    {
        if (self::isHeld($connection, $orderId, $lockRow)) {
            throw new InformationException('Order deletion is awaiting recovery. Retry deletion before billing or changing the service.');
        }
    }

    public static function isHeld(Connection $connection, int $orderId, bool $lockRow = false): bool
    {
        if ($lockRow && $connection->isTransactionActive()) {
            $connection->fetchOne('SELECT id FROM client_order WHERE id = :id' . RowLock::suffix($connection), ['id' => $orderId]);
        }

        // A locking/current read must follow the parent lock: a plain SELECT
        // can still see a pre-hold snapshot under MySQL REPEATABLE READ.
        return self::getState($connection, $orderId, $lockRow) !== null;
    }

    /** Caller holds the order lifecycle lock; commit before any remote action. */
    public static function setState(Connection $connection, int $orderId, string $state): void
    {
        $connection->transactional(function () use ($connection, $orderId, $state): void {
            // Serialize hold creation with credit settlement on the parent row.
            $connection->fetchOne('SELECT id FROM client_order WHERE id = :id' . RowLock::suffix($connection), ['id' => $orderId]);
            $key = ['client_order_id' => $orderId, 'name' => self::META_NAME];
            if (self::getState($connection, $orderId) === null) {
                $connection->insert('client_order_meta', $key + ['value' => $state]);
            } else {
                $connection->update('client_order_meta', ['value' => $state], $key);
            }
        });
    }
}

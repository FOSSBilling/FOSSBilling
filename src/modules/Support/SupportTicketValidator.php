<?php

declare(strict_types=1);

namespace Box\Mod\Support;

class SupportTicketValidator
{
    public static function validateTicketCreation(array $data): void
    {
        $rel_type = isset($data['rel_type']) ? (string) $data['rel_type'] : null;
        $rel_task = isset($data['rel_task']) ? (string) $data['rel_task'] : null;
        $rel_status = isset($data['rel_status']) ? (string) $data['rel_status'] : null;

        self::validateRelType($rel_type);
        self::validateRelTask($rel_task);
        self::validateRelStatus($rel_status);

        if ($rel_task === \Model_SupportTicket::REL_TASK_UPGRADE) {
            self::validateUpgradeRequest($data, $rel_type);
        }
    }

    protected static function validateRelStatus(?string $rel_status): void
    {
        if ($rel_status !== null && !in_array($rel_status, \Model_SupportTicket::ALLOWED_REL_STATUSES, true)) {
            throw new \FOSSBilling\Exception('Invalid related status.');
        }
    }

    protected static function validateRelType(?string $rel_type): void
    {
        if ($rel_type !== null && !in_array($rel_type, \Model_SupportTicket::ALLOWED_REL_TYPES, true)) {
            throw new \FOSSBilling\Exception('Invalid related type.');
        }
    }

    protected static function validateRelTask(?string $rel_task): void
    {
        if ($rel_task !== null && !in_array($rel_task, \Model_SupportTicket::ALLOWED_REL_TASKS, true)) {
            throw new \FOSSBilling\Exception('Invalid related task.');
        }
    }

    protected static function validateUpgradeRequest(array $data, ?string $rel_type): void
    {
        $rel_id = $data['rel_id'] ?? null;
        $rel_new_value = $data['rel_new_value'] ?? null;
        if ($rel_type !== \Model_SupportTicket::REL_TYPE_ORDER || $rel_id === null || empty($rel_new_value)) {
            throw new \FOSSBilling\Exception('You must provide both an order ID and a new product ID in order to request an upgrade.');
        }
    }
}

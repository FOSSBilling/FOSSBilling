<?php

namespace Box\Mod\Support;

class SupportTicketValidator
{
    /**
     * Validates the ticket creation data.
     *
     * @param array $data
     * @throws \FOSSBilling\Exception
     */
    public static function validateTicketCreation(array $data): void
    {
        $rel_type = $data['rel_type'] ?? null;
        $rel_task = $data['rel_task'] ?? null;
        $rel_status = $data['rel_status'] ?? null;

        // Validate rel_type
        self::validateRelType($rel_type);

        // Validate rel_task
        self::validateRelTask($rel_task);

        // Validate rel_status
        self::validateRelStatus($rel_status);

        // Validate upgrade-specific logic
        if ($rel_task === \Model_SupportTicket::REL_TASK_UPGRADE) {
            self::validateUpgradeRequest($data, $rel_type);
        }
    }

    /**
     * Validates the rel_status.
     *
     * @param string|null $rel_status
     * @throws \FOSSBilling\Exception
     */
    protected static function validateRelStatus(?string $rel_status): void
    {
        if ($rel_status !== null && !in_array($rel_status, \Model_SupportTicket::ALLOWED_REL_STATUSES, true)) {
            throw new \FOSSBilling\Exception('Invalid related status.');
        }
    }

    /**
     * Validates the rel_type.
     *
     * @param string|null $rel_type
     * @throws \FOSSBilling\Exception
     */
    protected static function validateRelType(?string $rel_type): void
    {
        if ($rel_type !== null && !in_array($rel_type, \Model_SupportTicket::ALLOWED_REL_TYPES, true)) {
            throw new \FOSSBilling\Exception('Invalid related type.');
        }
    }

    /**
     * Validates the rel_task.
     *
     * @param string|null $rel_task
     * @throws \FOSSBilling\Exception
     */
    protected static function validateRelTask(?string $rel_task): void
    {
        if ($rel_task !== null && !in_array($rel_task, \Model_SupportTicket::ALLOWED_REL_TASKS, true)) {
            throw new \FOSSBilling\Exception('Invalid related task.');
        }
    }

    /**
     * Validates upgrade-specific logic.
     *
     * @param array $data
     * @throws \FOSSBilling\Exception
     */
    protected static function validateUpgradeRequest(array $data, ?string $rel_type): void
    {
        $rel_id = $data['rel_id'] ?? null;
        $rel_new_value = $data['rel_new_value'] ?? null;
        if ($rel_type !== \Model_SupportTicket::REL_TYPE_ORDER || $rel_id === null || empty($rel_new_value)) {
            throw new \FOSSBilling\Exception('You must provide both an order ID and a new product ID in order to request an upgrade.');
        }
    }
}

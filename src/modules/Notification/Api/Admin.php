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

/**
 * Notifications center management.
 *
 * Notifications are important messages for staff messages to get informed
 * about important events on FOSSBilling.
 *
 * For example cron job can inform staff members
 */

namespace Box\Mod\Notification\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of notifications.
     *
     * @return array
     */
    public function get_list($data)
    {
        $queryBuilder = $this->getService()->getSearchQueryBuilder($data);

        return $this->di['pager']->paginateDoctrineQuery($queryBuilder, \FOSSBilling\PaginationOptions::fromArray($data));
    }

    /**
     * Get notification message.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Notification ID was not passed'])]
    public function get($data)
    {
        return $this->getService()->toApiArray($this->getService()->get((int) $data['id']));
    }

    /**
     * Add new notification message.
     *
     * @return int|false - new message id
     */
    public function add($data): int|false
    {
        if (!isset($data['message'])) {
            return false;
        }

        $message = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');

        return $this->getService()->create($message);
    }

    /**
     * Remove notification message.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Notification ID was not passed'])]
    public function delete($data): bool
    {
        return $this->getService()->delete((int) $data['id']);
    }

    /**
     * Remove all notification messages.
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete_all(): bool
    {
        return $this->getService()->deleteAll();
    }
}

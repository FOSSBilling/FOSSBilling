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
 * System activity messages management.
 */

namespace Box\Mod\Activity\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get a list of activity messages.
     *
     * @param array $data Search parameters
     *
     * @return array An array containing the list of activity messages and the pager information
     */
    public function log_get_list($data)
    {
        $data['min_priority'] ??= 6;
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params);

        foreach ($pager['list'] as $key => $item) {
            if (isset($item['staff_id'])) {
                $pager['list'][$key]['staff']['id'] = $item['staff_id'];
                $pager['list'][$key]['staff']['name'] = $item['staff_name'];
                $pager['list'][$key]['staff']['email'] = $item['staff_email'];
            }
            if (isset($item['client_id'])) {
                $pager['list'][$key]['client']['id'] = $item['client_id'];
                $pager['list'][$key]['client']['name'] = $item['client_name'];
                $pager['list'][$key]['client']['email'] = $item['client_email'];
            }
        }

        return $pager;
    }

    /**
     * Add a message to the log.
     *
     * @param array $data Message data
     */
    public function log($data): bool
    {
        if (!isset($data['m'])) {
            return false;
        }

        $this->getService()->logEvent([
            'client_id' => $data['client_id'] ?? null,
            'admin_id' => $data['admin_id'] ?? null,
            'priority' => $data['priority'] ?? 6,
            'message' => $data['m'],
        ]);

        return true;
    }

    /**
     * Add an email to the log.
     *
     * @param array $data Email data
     *
     * @return bool
     */
    public function log_email($data)
    {
        if (!isset($data['subject'])) {
            error_log('Email was not logged. Subject not passed');

            return false;
        }

        $client_id = $data['client_id'] ?? null;
        $sender = $data['sender'] ?? null;
        $recipients = $data['recipients'] ?? null;
        $subject = $data['subject'];
        $content_html = $data['content_html'] ?? null;
        $content_text = $data['content_text'] ?? null;

        return $this->getService()->logEmail($subject, $client_id, $sender, $recipients, $content_html, $content_text);
    }
}

<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 *Emails history listing and management.
 */

namespace Box\Mod\Email\Api;

class Client extends \Api_Abstract
{
    /**
     * Get list of emails system had sent to client.
     *
     * @return array - paginated list
     */
    public function get_list($data)
    {
        $client = $this->getIdentity();
        $data['client_id'] = $client->id;
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'],
                'client_id' => $item['client_id'],
                'sender' => $item['sender'],
                'recipients' => $item['recipients'],
                'subject' => $item['subject'],
                'content_html' => $item['content_html'],
                'content_text' => $item['content_text'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        }

        return $pager;
    }

    /**
     * Get email details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->findOneForClientById($this->getIdentity(), $data['id']);

        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $this->getService()->toApiArray($model);
    }

    /**
     * Resend email to client once again.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function resend($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->findOneForClientById($this->getIdentity(), $data['id']);
        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $this->getService()->resend($model);
    }

    /**
     * Remove email from system.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->findOneForClientById($this->getIdentity(), $data['id']);
        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $this->getService()->rm($model);
    }
}

<?php

/**
 * Copyright 2022-2025 FOSSBilling
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

use FOSSBilling\Validation\Api\RequiredParams;

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

        return $this->getService()->getEmailLogList($data);
    }

    /**
     * Get email details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function get($data)
    {
        $model = $this->getService()->findOneForClientById($this->getIdentity(), $data['id']);

        if ($model === null) {
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
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function resend($data)
    {
        $model = $this->getService()->findOneForClientById($this->getIdentity(), $data['id']);
        if ($model === null) {
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
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function delete($data)
    {
        $model = $this->getService()->findOneForClientById($this->getIdentity(), $data['id']);
        if ($model === null) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $this->getService()->rm($model);
    }
}

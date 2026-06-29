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
 *Emails history listing and management.
 */

namespace Box\Mod\Email\Api;

use Box\Mod\Email\Entity\ActivityClientEmail;
use Box\Mod\Email\Repository\ActivityClientEmailRepository;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Api\AbstractApi
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

        $repo = $this->getDi()['em']->getRepository(ActivityClientEmail::class);
        assert($repo instanceof ActivityClientEmailRepository);
        $pager = $this->getDi()['pager']->paginateDoctrineQuery(
            $repo->getSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
        );

        return $pager;
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

        if (!$model instanceof ActivityClientEmail) {
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
        $client = $this->getIdentity();

        $this->getDi()['rate_limiter']->consumeOrThrow('client_email_resend_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('client_email_resend_account', 'client:' . $client->id);

        $model = $this->getService()->findOneForClientById($client, $data['id']);
        if (!$model instanceof ActivityClientEmail) {
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
        if (!$model instanceof ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $this->getService()->rm($model);
    }
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 *Client management.
 */

namespace Box\Mod\Client\Api;

use Box\Mod\Client\Entity\ClientBalance;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;

class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get payments information.
     *
     * @return array
     */
    public function balance_get_list($data)
    {
        $service = $this->getDi()['mod_service']('Client', 'Balance');
        $data['client_id'] = $this->getIdentity()->getId();

        [$q, $params] = $service->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($q, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $balance = $this->getDi()['em']->getRepository(ClientBalance::class)->find($item['id']) ?? throw new InformationException('Balance not found');
            $pager['list'][$key] = $service->toApiArray($balance);
        }

        return $pager;
    }

    /**
     * Get client balance.
     *
     * @return float
     */
    public function balance_get_total()
    {
        $service = $this->getDi()['mod_service']('Client', 'Balance');

        return $service->getClientBalance($this->identity);
    }

    public function is_taxable()
    {
        return $this->getService()->isClientTaxable($this->identity);
    }

    public function resend_email_verification()
    {
        $client = $this->getIdentity();

        if ($client->getEmailApproved()) {
            // Email is already validated, so we don't need to do so again
            return true;
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('client_email_verification_resend_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('client_email_verification_resend_account', 'client:' . $client->getId());

        return $this->getService()->sendEmailConfirmationForClient($client);
    }
}

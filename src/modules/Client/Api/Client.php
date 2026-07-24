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

use Box\Mod\Client\Entity\Client as ClientEntity;
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
        $client = $this->getClientEntity($this->getIdentity());
        $data['client_id'] = $client->getId();

        return $this->getDi()['pager']->paginateMappedQuery(
            $service->getSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
            fn (ClientBalance $balance): array => $service->toApiArray($balance, $client),
        );
    }

    /**
     * Get client balance.
     *
     * @return float
     */
    public function balance_get_total()
    {
        $service = $this->getDi()['mod_service']('Client', 'Balance');

        return $service->getClientBalance($this->getClientEntity($this->getIdentity()));
    }

    public function is_taxable()
    {
        return $this->getService()->isClientTaxable($this->getClientEntity($this->getIdentity()));
    }

    public function resend_email_verification()
    {
        $client = $this->getClientEntity($this->getIdentity());

        $emailApproved = $client->getEmailApproved();
        if ($emailApproved) {
            // Email is already validated, so we don't need to do so again
            return true;
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('client_email_verification_resend_ip', (string) $this->getIp());
        $clientId = $client->getId();
        $this->getDi()['rate_limiter']->consumeOrThrow('client_email_verification_resend_account', 'client:' . $clientId);

        return $this->getService()->sendEmailConfirmationForClient($client);
    }

    private function getClientEntity(ClientEntity|\Model_Admin|\Model_Client|\Model_Guest $identity): ClientEntity
    {
        if ($identity instanceof ClientEntity) {
            return $identity;
        }

        $client = $this->getDi()['em']->getRepository(ClientEntity::class)->find((int) $identity->id);
        if (!$client instanceof ClientEntity) {
            throw new InformationException('Client not found');
        }

        return $client;
    }
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Repository\TaxRepository;
use FOSSBilling\InjectionAwareInterface;

class ServiceTax implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?TaxRepository $taxRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getTaxRateForClient(\Model_Client $model, &$title = null)
    {
        $clientService = $this->di['mod_service']('client');
        if (!$clientService->isClientTaxable($model)) {
            return 0;
        }

        $tax = $this->di['db']->findOne('Tax', 'state = ? and country = ?', [$model->state, $model->country]);
        // find rate which matches clients country and state

        if ($tax instanceof \Model_Tax) {
            $title = $tax->name;

            return $tax->taxrate;
        }

        // find rate which matches clients country
        $tax = $this->di['db']->findOne('Tax', 'country = ?', [$model->country]);
        if ($tax instanceof \Model_Tax) {
            $title = $tax->name;

            return $tax->taxrate;
        }

        // find global rate
        $tax = $this->di['db']->findOne('Tax', '(state is NULL or state = "") and (country is null or country = "")');
        if ($tax instanceof \Model_Tax) {
            $title = $tax->name;

            return $tax->taxrate;
        }

        return 0;
    }

    public function getTax(\Model_Invoice $invoice)
    {
        if ($invoice->taxrate <= 0) {
            return 0;
        }

        $tax = 0;
        $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', [$invoice->id]);
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach ($invoiceItems as $item) {
            $tax += $invoiceItemService->getTax($item) * $item->quantity;
        }

        return $tax;
    }

    public function delete(\Model_Tax $model): bool
    {
        $name = $model->name;
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted tax rule %s', $name);

        return true;
    }

    public function create(array $data)
    {
        $model = new Tax();
        $model->setName($data['name']);
        $model->setCountry((!isset($data['country']) || empty($data['country'])) ? null : $data['country']);
        $model->setState((!isset($data['state']) || empty($data['state'])) ? null : $data['state']);
        $model->setTaxrate($data['taxrate']);
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $newId = $model->getId();

        $this->di['logger']->info('Created new tax rule %s', $model->getName());

        return $newId;
    }

    public function update(\Model_Tax $model, array $data): bool
    {
        $model->name = $data['name'];
        $model->country = (!isset($data['country']) || empty($data['country'])) ? null : $data['country'];
        $model->state = (!isset($data['state']) || empty($data['state'])) ? null : $data['state'];
        $model->taxrate = $data['taxrate'];
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated tax rule %s', $model->name);

        return true;
    }

    public function getSearchQuery($data): array
    {
        $sql = 'SELECT *
            FROM tax
            ORDER BY id desc';

        return [$sql, []];
    }

    public function toApiArray(\Model_Tax $model, $deep = false, $identity = null)
    {
        return $this->di['db']->toArray($model);
    }

    private function getTaxRepository(): TaxRepository
    {
        if ($this->taxRepository === null) {
            $this->taxRepository = $this->di['em']->getRepository(Tax::class);
        }

        return $this->taxRepository;
    }
}

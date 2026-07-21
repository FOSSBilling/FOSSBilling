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

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\TaxRepository;
use FOSSBilling\InjectionAwareInterface;

class ServiceTax implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?TaxRepository $taxRepository = null;
    private ?InvoiceItemRepository $invoiceItemRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getTaxRateForClient(ClientEntity $model, &$title = null)
    {
        $clientService = $this->di['mod_service']('client');
        if (!$clientService->isClientTaxable($model)) {
            return 0;
        }

        $state = $model instanceof ClientEntity ? $model->getState() : $model->state;
        $country = $model instanceof ClientEntity ? $model->getCountry() : $model->country;

        $tax = $this->getTaxRepository()->findByCountryAndState($state, $country);
        // find rate which matches clients country and state

        if ($tax instanceof Tax) {
            $title = $tax->getName();

            return (float) $tax->getTaxrate();
        }

        // find rate which matches clients country
        $tax = $this->getTaxRepository()->findByCountry($country);
        if ($tax instanceof Tax) {
            $title = $tax->getName();

            return (float) $tax->getTaxrate();
        }

        // find global rate
        $tax = $this->getTaxRepository()->findGlobal();
        if ($tax instanceof Tax) {
            $title = $tax->getName();

            return (float) $tax->getTaxrate();
        }

        return 0;
    }

    public function getTax(Invoice $invoice)
    {
        $taxrate = $invoice instanceof Entity\Invoice ? $invoice->getTaxrate() : $invoice->taxrate;
        if ($taxrate <= 0) {
            return 0;
        }

        $invoiceId = $invoice instanceof Entity\Invoice ? $invoice->getId() : $invoice->id;
        $tax = 0;
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoiceId);
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach ($invoiceItems as $item) {
            $tax += $invoiceItemService->getTax($item) * ($item instanceof InvoiceItem ? $item->getQuantity() : $item->quantity);
        }

        return $tax;
    }

    public function delete(Tax $model): bool
    {
        $name = $model->getName();
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

    public function update(Tax $model, array $data): bool
    {
        $model->name = $data['name'];
        $model->country = (!isset($data['country']) || empty($data['country'])) ? null : $data['country'];
        $model->state = (!isset($data['state']) || empty($data['state'])) ? null : $data['state'];
        $model->taxrate = $data['taxrate'];
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated tax rule %s', $model->getName());

        return true;
    }

    public function getSearchQuery($data): array
    {
        $sql = 'SELECT *
            FROM tax
            ORDER BY id desc';

        return [$sql, []];
    }

    public function toApiArray(Tax $model, $deep = false, $identity = null)
    {
        return [
            'id' => $model->getId(),
            'level' => $model->getLevel(),
            'name' => $model->getName(),
            'country' => $model->getCountry(),
            'state' => $model->getState(),
            'taxrate' => $model->getTaxrate(),
            'created_at' => $model->getCreatedAt(),
            'updated_at' => $model->getUpdatedAt(),
        ];
    }

    private function getInvoiceItemRepository(): InvoiceItemRepository
    {
        if ($this->invoiceItemRepository === null) {
            $this->invoiceItemRepository = $this->di['em']->getRepository(InvoiceItem::class);
        }

        return $this->invoiceItemRepository;
    }

    private function getTaxRepository(): TaxRepository
    {
        if ($this->taxRepository === null) {
            $this->taxRepository = $this->di['em']->getRepository(Tax::class);
        }

        return $this->taxRepository;
    }
}

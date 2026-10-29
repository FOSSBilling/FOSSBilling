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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Repository\TaxRepository;
use FOSSBilling\Core\Container\InjectionAwareInterface;

class ServiceTax implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    protected TaxRepository $taxRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->taxRepository = $di['em']->getRepository(Tax::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getTaxRepository(): TaxRepository
    {
        return $this->taxRepository;
    }

    public function getTaxRateForClient(?Client $model, &$title = null)
    {
        $clientService = $this->di['mod_service']('client');
        if (!$clientService->isClientTaxable($model)) {
            return 0;
        }

        $tax = $this->taxRepository->findOneByStateAndCountry($model?->getState(), $model?->getCountry());
        // find rate which matches clients country and state

        if ($tax instanceof Tax) {
            $title = $tax->getName();

            return $tax->getTaxrate();
        }

        // find rate which matches clients country
        $tax = $this->taxRepository->findOneByCountry($model?->getCountry());
        if ($tax instanceof Tax) {
            $title = $tax->getName();

            return $tax->getTaxrate();
        }

        // find global rate
        $tax = $this->taxRepository->findGlobalRate();
        if ($tax instanceof Tax) {
            $title = $tax->getName();

            return $tax->getTaxrate();
        }

        return 0;
    }

    public function getTax(Invoice $invoice)
    {
        if ($invoice->getTaxrate() <= 0) {
            return 0;
        }

        $tax = 0;
        $invoiceItems = $this->di['em']->getRepository(Entity\InvoiceItem::class)->findByInvoiceId((int) $invoice->getId());
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach ($invoiceItems as $item) {
            $tax += $invoiceItemService->getTax($item) * ($item->getQuantity() ?? 1);
        }

        return $tax;
    }

    public function delete(Tax $model): bool
    {
        $name = $model->getName();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted tax rule {name}', ['name' => $name]);

        return true;
    }

    public function create(array $data): ?int
    {
        $model = new Tax();
        $model->setName($data['name']);
        $model->setCountry((!isset($data['country']) || empty($data['country'])) ? null : $data['country']);
        $model->setState((!isset($data['state']) || empty($data['state'])) ? null : $data['state']);
        $model->setTaxrate($data['taxrate']);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Created new tax rule {model_name}', ['model_name' => $model->getName()]);

        return $model->getId();
    }

    public function update(Tax $model, array $data): bool
    {
        $model->setName($data['name']);
        $model->setCountry((!isset($data['country']) || empty($data['country'])) ? null : $data['country']);
        $model->setState((!isset($data['state']) || empty($data['state'])) ? null : $data['state']);
        $model->setTaxrate($data['taxrate']);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated tax rule {model_name}', ['model_name' => $model->getName()]);

        return true;
    }

    public function toApiArray(Tax $model, $deep = false, $identity = null)
    {
        return $model->toApiArray();
    }
}

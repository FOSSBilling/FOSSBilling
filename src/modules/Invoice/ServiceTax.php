<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use FOSSBilling\InjectionAwareInterface;

class ServiceTax implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

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

    public function delete(\Model_Tax $model)
    {
        $name = $model->name;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted tax rule %s', $name);

        return true;
    }

    public function create(array $data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Tax', 2);

        $model = $this->di['db']->dispense('Tax');
        $model->name = $data['name'];
        $model->country = (!isset($data['country']) || empty($data['country'])) ? null : $data['country'];
        $model->state = (!isset($data['state']) || empty($data['state'])) ? null : $data['state'];
        $model->taxrate = $data['taxrate'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new tax rule %s', $model->name);

        return $newId;
    }

    public function update(\Model_Tax $model, array $data)
    {
        $model->name = $data['name'];
        $model->country = (!isset($data['country']) || empty($data['country'])) ? null : $data['country'];
        $model->state = (!isset($data['state']) || empty($data['state'])) ? null : $data['state'];
        $model->taxrate = $data['taxrate'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Created new tax rule %s', $model->name);

        return true;
    }

    public function getSearchQuery($data)
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
}

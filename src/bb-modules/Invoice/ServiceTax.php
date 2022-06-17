<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Invoice;

use Box\InjectionAwareInterface;

class ServiceTax implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
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

    public function setupEUTaxes(array $data)
    {
        $sql = 'TRUNCATE tax;';
        $this->di['db']->exec($sql);

        $systemService = $this->di['mod_service']('System');
        $eu_countries = $systemService->getEuCountries();
        $eu_vat = $systemService->getEuVat();
        foreach ($eu_vat as $code => $taxRate) {
            $this->create(['name' => $eu_countries[$code], 'taxrate' => $taxRate, 'country' => $code]);
        }

        return true;
    }

    public function toApiArray(\Model_Tax $model, $deep = false, $identity = null)
    {
        return $this->di['db']->toArray($model);
    }
}

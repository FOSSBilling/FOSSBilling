<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Model_ProductCustomTable extends Model_ProductTable
{
    public function toAddonArray(Model_ProductCustom $model, $deep = true)
    {
        $productService = $this->di['mod_service']('Product');
        return $productService->toAddonArray($model, $deep);
    }
}
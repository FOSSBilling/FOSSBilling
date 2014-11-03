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


class Model_ClientGroupTable
{
    public function toApiArray(\Model_ClientGroup $model, $deep = false, $identity = null)
    {
        return $this->di['db']->toArray($model);
    }
}
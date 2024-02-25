<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Payment_Invoice_Subscription
{
    private $id;
    private $amount;
    private $cycle;
    private $unit;

    /**
     * Set id.
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param float $price
     */
    public function setAmount($price)
    {
        $this->amount = $price;

        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setCycle($cycle)
    {
        $this->cycle = $cycle;

        return $this;
    }

    public function getCycle()
    {
        return $this->cycle;
    }

    public function setUnit($param)
    {
        $this->unit = $param;

        return $this;
    }

    public function getUnit()
    {
        return $this->unit;
    }
}

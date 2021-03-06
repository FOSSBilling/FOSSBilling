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

class Payment_Invoice_Subscription
{
    private $id;
    private $amount;
    private $cycle;
    private $unit;
    
    /**
     * Set id
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param double $price
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
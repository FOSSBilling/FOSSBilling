<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Invoice
{
    private $id             = NULL; // BoxBilling Invoice Id
    private $number         = NULL; // Invoice number for accounting
    private $currency       = 'USD';
    private $items          = array();
    private $subscription   = NULL;
    private $buyer          = NULL;
    private $title          = 'Payment for invoice';

    public function setId($param)
    {
        $this->id = $param;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setNumber($param)
    {
        $this->number = $param;
        return $this;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setCurrency($param)
    {
        $this->currency = $param;
        return $this;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setBuyer(Payment_Invoice_Buyer $param)
    {
        $this->buyer = $param;
        return $this;
    }
    
    public function getBuyer()
    {
        return $this->buyer;
    }

    public function setItems(array $items)
    {
        foreach($items as $item) {
            if($item instanceof Payment_Invoice_Item) {
                $this->items[] = $item;
            }
        }
        return $this;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function setSubscription(Payment_Invoice_Subscription $param)
    {
        $this->subscription = $param;
        return $this;
    }

    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * @param null|string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getTotal()
    {
        $total = 0;
        foreach ($this->items as $item)  {
            $total += $item->getTotal();
        }
        return $total;
    }

    public function getTotalWithTax()
    {
        return $this->getTotal() + $this->getTax();
    }

    public function getTax()
    {
        $tax = 0;
        foreach ($this->items as $item)  {
            $tax += $item->getTax() * $item->getQuantity();
        }
        return $tax;
    }

}
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

class Payment_Invoice
{
    private $id             = NULL; // FOSSBilling Invoice Id
    private $number         = NULL; // Invoice number for accounting
    private $currency       = 'USD';
    private $items          = array();
    private $subscription   = NULL;
    private $buyer          = NULL;
    private $title          = 'Payment for invoice';

    /**
     * Set the invoice ID.
     *
     * @param mixed $param The invoice ID.
     *
     * @return $this The current object, for method chaining.
     */
    public function setId($param)
    {
        $this->id = $param;
        return $this;
    }

    /**
     * Get the invoice ID.
     *
     * @return mixed The invoice ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the invoice number.
     *
     * @param mixed $param The invoice number.
     *
     * @return $this The current object, for method chaining.
     */
    public function setNumber($param)
    {
        $this->number = $param;
        return $this;
    }

    /**
     * Get the invoice number.
     *
     * @return mixed The invoice number.
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set the currency.
     *
     * @param string $param The currency.
     *
     * @return $this The current object, for method chaining.
     */
    public function setCurrency($param)
    {
        $this->currency = $param;
        return $this;
    }

    /**
     * Get the currency.
     *
     * @return string The currency.
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the buyer.
     *
     * @param Payment_Invoice_Buyer $param The buyer object.
     *
     * @return $this The current object, for method chaining.
     */
    public function setBuyer(Payment_Invoice_Buyer $param)
    {
        $this->buyer = $param;
        return $this;
    }

    /**
     * Get the buyer.
     *
     * @return Payment_Invoice_Buyer The buyer object.
     */
    public function getBuyer()
    {
        return $this->buyer;
    }

    /**
     * Set the items.
     *
     * @param array $items An array of Payment_Invoice_Item objects.
     *
     * @return $this The current object, for method chaining.
     */
    public function setItems(array $items)
    {
        foreach ($items as $item) {
            if ($item instanceof Payment_Invoice_Item) {
                $this->items[] = $item;
            }
        }
        return $this;
    }

    /**
     * Get the items.
     *
     * @return array An array of Payment_Invoice_Item objects.
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the subscription.
     *
     * @param Payment_Invoice_Subscription $param The subscription object.
     *
     * @return $this The current object, for method chaining.
     */
    public function setSubscription(Payment_Invoice_Subscription $param)
    {
        $this->subscription = $param;
        return $this;
    }

    /**
     * Get the subscription.
     *
     * @return Payment_Invoice_Subscription The subscription object.
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * Set the title.
     *
     * @param null|string $title The title.
     *
     * @return $this The current object, for method chaining.
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get the title.
     *
     * @return null|string The title.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get the total amount of the invoice (without tax).
     *
     * @return float The total amount.
     */
    public function getTotal()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getTotal();
        }
        return $total;
    }

    /**
     * Get the total amount of the invoice (with tax).
     *
     * @return float The total amount with tax.
     */
    public function getTotalWithTax()
    {
        return $this->getTotal() + $this->getTax();
    }

    /**
     * Get the tax amount of the invoice.
     *
     * @return float The tax amount.
     */
    public function getTax()
    {
        $tax = 0;
        foreach ($this->items as $item) {
            $tax += $item->getTax() * $item->getQuantity();
        }
        return $tax;
    }
}

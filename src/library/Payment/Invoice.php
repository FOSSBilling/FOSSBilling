<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Payment_Invoice
{
    private $id; // FOSSBilling Invoice Id
    private $number; // Invoice number for accounting
    private string $currency = 'USD';
    private array $items = [];
    private ?Payment_Invoice_Subscription $subscription = null;
    private ?Payment_Invoice_Buyer $buyer = null;
    private string $title = 'Payment for invoice';

    /**
     * Set the invoice ID.
     *
     * @param mixed $param the invoice ID
     *
     * @return $this the current object, for method chaining
     */
    public function setId(mixed $param)
    {
        $this->id = $param;

        return $this;
    }

    /**
     * Get the invoice ID.
     *
     * @return mixed the invoice ID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the invoice number.
     *
     * @param mixed $param the invoice number
     *
     * @return $this the current object, for method chaining
     */
    public function setNumber(mixed $param)
    {
        $this->number = $param;

        return $this;
    }

    /**
     * Get the invoice number.
     *
     * @return mixed the invoice number
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set the currency.
     *
     * @param string $param the currency
     *
     * @return $this the current object, for method chaining
     */
    public function setCurrency($param)
    {
        $this->currency = $param;

        return $this;
    }

    /**
     * Get the currency.
     *
     * @return string the currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the buyer.
     *
     * @param Payment_Invoice_Buyer $param the buyer object
     *
     * @return $this the current object, for method chaining
     */
    public function setBuyer(Payment_Invoice_Buyer $param)
    {
        $this->buyer = $param;

        return $this;
    }

    /**
     * Get the buyer.
     *
     * @return Payment_Invoice_Buyer the buyer object
     */
    public function getBuyer()
    {
        return $this->buyer;
    }

    /**
     * Set the items.
     *
     * @param array $items an array of Payment_Invoice_Item objects
     *
     * @return $this the current object, for method chaining
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
     * @return array an array of Payment_Invoice_Item objects
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the subscription.
     *
     * @param Payment_Invoice_Subscription $param the subscription object
     *
     * @return $this the current object, for method chaining
     */
    public function setSubscription(Payment_Invoice_Subscription $param)
    {
        $this->subscription = $param;

        return $this;
    }

    /**
     * Get the subscription.
     *
     * @return Payment_Invoice_Subscription the subscription object
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * Set the title.
     *
     * @param string|null $title the title
     *
     * @return $this the current object, for method chaining
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the title.
     *
     * @return string|null the title
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get the total amount of the invoice (without tax).
     *
     * @return float the total amount
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
     * @return float the total amount with tax
     */
    public function getTotalWithTax()
    {
        return $this->getTotal() + $this->getTax();
    }

    /**
     * Get the tax amount of the invoice.
     *
     * @return float the tax amount
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

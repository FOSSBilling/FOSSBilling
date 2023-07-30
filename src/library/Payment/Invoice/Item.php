<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Invoice_Item
{
    private $id;
    private $title;
    private $description;
    private int $qty = 1;
    private $price;
    private $tax;

    /**
     * Set the id of the item.
     *
     * @param int $id The id of the item.
     *
     * @return $this The current object, for method chaining.
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the id of the item.
     *
     * @return int The id of the item.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the title of the item.
     *
     * @param string $title The title of the item.
     *
     * @return $this The current object, for method chaining.
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get the title of the item.
     *
     * @return string The title of the item.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the description of the item.
     *
     * @param string $description The description of the item.
     *
     * @return $this The current object, for method chaining.
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the description of the item.
     *
     * @return string The description of the item.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the price of the item.
     *
     * @param float $price The price of the item.
     *
     * @return $this The current object, for method chaining.
     */
    public function setPrice($price)
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Get the price of the item.
     *
     * @return float The price of the item.
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set the tax amount for the item.
     *
     * @param float $tax The tax amount for the item.
     *
     * @return $this The current object, for method chaining.
     */
    public function setTax($tax)
    {
        $this->tax = $tax;
        return $this;
    }

    /**
     * Get the tax amount for the item.
     *
     * @return float The tax amount for the item.
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * Set the quantity of the item.
     *
     * @param int $qty The quantity of the item.
     *
     * @return $this The current object, for method chaining.
     */
    public function setQuantity($qty)
    {
        $this->qty = $qty;
        return $this;
    }

    /**
     * Get the quantity of the item.
     *
     * @return int The quantity of the item.
     */
    public function getQuantity()
    {
        return $this->qty;
    }

    /**
     * Return the total price for this item.
     *
     * @return float The total price for this item.
     */
    public function getTotal()
    {
        return $this->getQuantity() * $this->getPrice();
    }

    /**
     * Return the total price for this item including tax.
     *
     * @return float The total price for this item including tax.
     */
    public function getTotalWithTax()
    {
        return $this->getTotal() + $this->getTax();
    }
}

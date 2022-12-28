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

class Payment_Transaction
{
    const STATUS_UNKNOWN        = 'unknown';
    const STATUS_PENDING        = 'pending';
    const STATUS_COMPLETE       = 'complete';

    const TXTYPE_PAYMENT        = 'payment';
    const TXTYPE_REFUND         = 'refund';
    const TXTYPE_SUBSCR_CREATE  = 'subscription_create';
    const TXTYPE_SUBSCR_CANCEL  = 'subscription_cancel';
    const TXTYPE_UNKNOWN        = 'unknown';

    private $id                 = NULL;
    private $type               = self::TXTYPE_UNKNOWN;
    private $status             = self::STATUS_UNKNOWN;
    private $currency           = NULL;
    private $amount             = NULL;
    private $subscription_id    = NULL;

    /**
     * Set the transaction ID.
     *
     * @param mixed $param The transaction ID.
     *
     * @return $this The current object, for method chaining.
     */
    public function setId($param)
    {
        $this->id = $param;
        return $this;
    }

    /**
     * Get the transaction ID.
     *
     * @return mixed The transaction ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the transaction status.
     *
     * @param string $param The transaction status.
     *
     * @return $this The current object, for method chaining.
     */
    public function setStatus($param)
    {
        $this->status = $param;
        return $this;
    }

    /**
     * Get the transaction status.
     *
     * @return string The transaction status.
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the transaction type.
     *
     * @param string $param The transaction type.
     *
     * @return $this The current object, for method chaining.
     */
    public function setType($param)
    {
        $this->type = $param;
        return $this;
    }

    /**
     * Get the transaction type.
     *
     * @return string The transaction type.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the transaction currency.
     *
     * @param string $param The transaction currency.
     *
     * @return $this The current object, for method chaining.
     */
    public function setCurrency($param)
    {
        $this->currency = $param;
        return $this;
    }

    /**
     * Get the transaction currency.
     *
     * @return string The transaction currency.
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the transaction amount.
     *
     * @param float $param The transaction amount.
     *
     * @return $this The current object, for method chaining.
     */
    public function setAmount($param)
    {
        $this->amount = $param;
        return $this;
    }

    /**
     * Get the transaction amount.
     *
     * @return float The transaction amount.
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the ID of the subscription associated with the transaction.
     *
     * @param string $param The subscription ID.
     *
     * @return $this The current object, for method chaining.
     */
    public function setSubscriptionId($param)
    {
        $this->subscription_id = $param;
        return $this;
    }

    /**
     * Get the ID of the subscription associated with the transaction.
     *
     * @return string The subscription ID.
     */
    public function getSubscriptionId()
    {
        return $this->subscription_id;
    }
}

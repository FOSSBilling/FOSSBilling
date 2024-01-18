<?php

class Payment_Adapter_Dummy
{
    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getConfig()
    {
        return [];
    }

    public function getType()
    {
        return self::TYPE_FORM;
    }

    public function getServiceUrl()
    {
        return 'https://www.google.com/?q=dummy';
    }

    public function singlePayment(Payment_Invoice $invoice)
    {
        return [];
    }

    public function recurrentPayment(Payment_Invoice $invoice)
    {
        return [];
    }

    public function ipn($data, Payment_Invoice $invoice)
    {
        $tx = new Payment_Transaction();
        $tx->setAmount($invoice->getTotal());
        $tx->setCurrency($invoice->getCurrency());
        $tx->setId(md5(uniqid($invoice->getNumber())));
        $tx->setIsValid(true);
        $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        return $tx;
    }
}

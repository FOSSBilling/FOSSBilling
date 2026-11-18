<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Exception;

class RetryableCallbackException extends \FOSSBilling\Core\Exception\InformationException
{
    public function __construct(string $message)
    {
        parent::__construct($message, null, 503);
    }
}

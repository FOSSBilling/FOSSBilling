<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Exception;

/** A renewal blocked by order state can retain a verified payment as credit. */
final class RenewalHoldException extends \FOSSBilling\Core\Exception\InformationException
{
}

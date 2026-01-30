<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Events;

use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

/**
 * Base event class for all FOSSBilling events.
 *
 * All event classes should extend this class to ensure consistent
 * access to the DI container and common event functionality.
 */
abstract class Event extends SymfonyEvent implements InjectionAwareInterface
{
    protected ?Container $di = null;

    /**
     * Timestamp when the event occurred.
     */
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }
}

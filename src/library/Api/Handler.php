<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;

final class Api_Handler implements InjectionAwareInterface
{
    protected string $type;
    protected $ip;
    protected ?Pimple\Container $di = null;

    public function __construct(protected $identity)
    {
        $role = str_replace('model_', '', strtolower($identity::class));
        $this->type = $role;
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    protected function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function getIdentity(): object
    {
        return $this->identity;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

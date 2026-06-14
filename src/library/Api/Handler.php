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

    public function call(string $method, array $data = []): mixed
    {
        return $this->getDispatcher()->dispatch($this->identity, $method, $data);
    }

    public function __call($method, $arguments)
    {
        return $this->getDispatcher()->dispatchWithArguments($this->identity, (string) $method, $arguments);
    }

    private function getDispatcher(): Api_Dispatcher
    {
        $di = $this->getDi();
        if ($di === null || !$di->offsetExists('api_dispatcher')) {
            throw new LogicException('API handler requires the api_dispatcher service');
        }

        $dispatcher = $di['api_dispatcher'];
        if (!$dispatcher instanceof Api_Dispatcher) {
            throw new LogicException('API dispatcher service must resolve to an Api_Dispatcher instance');
        }

        return $dispatcher;
    }
}

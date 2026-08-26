<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Api;

use FOSSBilling\Interfaces\InjectionAwareInterface;
use Pimple\Container;

final class Proxy implements InjectionAwareInterface
{
    protected ?Container $di = null;
    private readonly Identity $caller;
    private ?Dispatcher $dispatcher = null;

    /**
     * @param object $identity Raw identity object or pre-wrapped Identity VO
     */
    public function __construct(object $identity)
    {
        $this->caller = $identity instanceof Identity ? $identity : new Identity($identity);
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getIdentity(): object
    {
        return $this->caller->getIdentity();
    }

    public function getType(): string
    {
        return $this->caller->getRole()->value;
    }

    public function call(string $method, array $data = []): mixed
    {
        return $this->getDispatcher()->dispatch($this->caller, $method, $data);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->getDispatcher()->dispatchWithArguments($this->caller, $method, $arguments);
    }

    private function getDispatcher(): Dispatcher
    {
        if ($this->dispatcher !== null) {
            return $this->dispatcher;
        }

        if ($this->di === null || !$this->di->offsetExists('api_dispatcher')) {
            throw new \LogicException('API proxy requires the api_dispatcher service');
        }

        $dispatcher = $this->di['api_dispatcher'];
        if (!$dispatcher instanceof Dispatcher) {
            throw new \LogicException('API dispatcher service must resolve to a FOSSBilling\Api\Dispatcher instance');
        }

        return $this->dispatcher = $dispatcher;
    }
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Api;

use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;

final class Proxy implements InjectionAwareInterface
{
    protected string $type;
    protected ?Container $di = null;
    private ?Dispatcher $dispatcher = null;

    public function __construct(protected object $identity)
    {
        $this->type = Identity::typeFromObject($identity);
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
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

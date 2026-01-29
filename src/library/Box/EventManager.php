<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_EventManager implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public const GLOBAL_LISTENER_NAME = 'onEveryEvent';

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function fire($data)
    {
        if (!isset($data['event']) || empty($data['event'])) {
            error_log('Invoked event call without providing event name');

            return false;
        }

        $event = $data['event'];
        $subject = $data['subject'] ?? null;
        $params = $data['params'] ?? null;

        $this->di['logger']->setChannel('event')->debug('Fired event: ' . $event, $params);

        $e = new Box_Event($subject, $event, $params);
        $e->setDi($this->di);
        $disp = new Box_EventDispatcher();

        $this->_connectDatabaseHooks($disp, $e->getName());
        $this->_connectDatabaseHooks($disp, self::GLOBAL_LISTENER_NAME); // Also connect the global listeners (onEveryEvent)
        $this->_connectProductTypeHooks($disp, $e->getName());
        $this->_connectProductTypeHooks($disp, self::GLOBAL_LISTENER_NAME);

        $disp->notify($e);

        return $e->getReturnValue();
    }

    /**
     * @param Box_EventDispatcher $disp
     * @param string              $event
     */
    private function _connectDatabaseHooks(&$disp, $event): void
    {
        $sql = "SELECT id, rel_id, meta_value
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
            AND meta_value = :event
        ";
        $list = $this->di['db']->getAll($sql, ['event' => $event]);

        // no need to connect listeners
        if (empty($list)) {
            return;
        }

        foreach ($list as $listener) {
            $mod = $listener['rel_id'];
            $event = $listener['meta_value'];

            try {
                $s = $this->di['mod_service']($mod);

                if (method_exists($s, $event)) {
                    $disp->connect($event, [$s::class, $event]);
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
    }

    private function _connectProductTypeHooks(Box_EventDispatcher $disp, string $event): void
    {
        $registry = $this->di['product_type_registry'] ?? null;
        if (!$registry instanceof FOSSBilling\ProductTypeRegistry) {
            return;
        }

        foreach ($registry->getDefinitions() as $code => $definition) {
            if (!empty($definition['legacy'])) {
                continue;
            }

            try {
                $handler = $registry->getHandler((string) $code);
            } catch (Throwable $e) {
                error_log($e->getMessage());
                continue;
            }

            $this->connectProductTypeHook($disp, $handler, $event);
        }
    }

    private function connectProductTypeHook(Box_EventDispatcher $disp, object $handler, string $event): void
    {
        if (!method_exists($handler, $event)) {
            return;
        }

        try {
            $method = new ReflectionMethod($handler, $event);
        } catch (ReflectionException) {
            return;
        }

        if (!$this->canBeConnected($method)) {
            return;
        }

        $disp->connect($event, [$handler, $event]);
    }

    private function canBeConnected(ReflectionMethod $method): bool
    {
        if (!$method->isPublic()) {
            return false;
        }

        $parameters = $method->getParameters();
        if (!isset($parameters[0])) {
            return false;
        }

        $type = $parameters[0]->getType() instanceof ReflectionNamedType ? $parameters[0]->getType()->getName() : null;

        return $type === Box_Event::class || $type === '\Box_Event' || $type === 'Box_Event';
    }
}

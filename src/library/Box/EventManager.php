<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_EventManager implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    /** @var array<string, list<array{rel_id: mixed, meta_value: mixed}>>|null */
    private ?array $databaseListeners = null;

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
            if ($this->di !== null && isset($this->di['logger'])) {
                $this->di['logger']->warning('Invoked event call without providing event name');
            }

            return false;
        }

        $event = $data['event'];
        $subject = $data['subject'] ?? null;
        $params = $data['params'] ?? null;

        $eventContext = ['event' => $event];
        if (is_array($params)) {
            $eventContext['parameters'] = $params;
        }
        $this->di['logger']->withChannel('event')->debug('Fired event: {event}', $eventContext);

        $e = new Box_Event($subject, $event, $params);
        $e->setDi($this->di);
        $disp = new Box_EventDispatcher();

        $eventName = $e->getName();

        $this->connectDatabaseHooks($disp, $eventName);
        $this->connectDatabaseHooks($disp, self::GLOBAL_LISTENER_NAME, $eventName); // Also connect global listeners (onEveryEvent) to the fired event

        $disp->notify($e);

        return $e->getReturnValue();
    }

    public function clearListenerCache(): void
    {
        $this->databaseListeners = null;
    }

    private function connectDatabaseHooks(Box_EventDispatcher $disp, string $event, ?string $dispatchEventName = null): void
    {
        $listeners = $this->getDatabaseListeners()[$event] ?? [];

        foreach ($listeners as $listener) {
            $mod = $listener['rel_id'];
            $listenerEvent = $listener['meta_value'];
            $dispatchEvent = $dispatchEventName ?? $listenerEvent;

            try {
                $service = $this->di['mod_service']($mod);

                if (method_exists($service, $listenerEvent)) {
                    $disp->connect($dispatchEvent, [$service::class, $listenerEvent]);
                }
            } catch (Exception $e) {
                $this->di['logger']->withChannel('event')->error($e->getMessage());
            }
        }
    }

    /** @return array<string, list<array{rel_id: mixed, meta_value: mixed}>> */
    private function getDatabaseListeners(): array
    {
        if ($this->databaseListeners !== null) {
            return $this->databaseListeners;
        }

        $sql = "SELECT rel_id, meta_value
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
        ";
        $listeners = $this->di['em']->getConnection()->fetchAllAssociative($sql);
        $this->databaseListeners = [];

        foreach ($listeners as $listener) {
            $this->databaseListeners[(string) $listener['meta_value']][] = $listener;
        }

        return $this->databaseListeners;
    }
}

<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_EventManager implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

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

        $this->di['logger']->setChannel('event')->debug($event, $params);

        $e = new Box_Event($subject, $event, $params);
        $e->setDi($this->di);
        $disp = new Box_EventDispatcher();
        $this->_connectDatabaseHooks($disp, $e->getName());
        $disp->notify($e);

        return $e->getReturnValue();
    }

    /**
     * @param Box_EventDispatcher $disp
     * @param string              $event
     */
    private function _connectDatabaseHooks(&$disp, $event)
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
}

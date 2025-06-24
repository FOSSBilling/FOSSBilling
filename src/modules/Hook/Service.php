<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Hook;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'hide_permissions' => true,
        ];
    }

    public function getSearchQuery($filter)
    {
        $q = "SELECT id, rel_type, rel_id, meta_value as event, created_at, updated_at
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
        ";

        return [$q, []];
    }

    public function toApiArray($row)
    {
        return $row;
    }

    public static function onAfterAdminActivateExtension(\Box_Event $event)
    {
        $params = $event->getParameters();
        if (!isset($params['id'])) {
            $event->setReturnValue(false);
        } else {
            $di = $event->getDi();
            $ext = $di['db']->load('extension', $params['id']);
            if (is_object($ext) && $ext->type == 'mod') {
                $service = $di['mod_service']('hook');
                $service->batchConnect($ext->name);
            }
            $event->setReturnValue(true);
        }
    }

    public static function onAfterAdminDeactivateExtension(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        if ($params['type'] == 'mod') {
            $q = "DELETE FROM extension_meta
                WHERE extension = 'mod_hook'
                AND rel_type = 'mod'
                AND rel_id = :mod
                AND meta_key = 'listener'";
            $di['db']->exec($q, ['mod' => $params['id']]);
        }

        $event->setReturnValue(true);
    }

    /**
     * @return bool
     */
    public function batchConnect($mod_name = null)
    {
        // Clean up the existing list before we add to it
        $this->_disconnectUnavailable();

        $mods = [];
        if ($mod_name !== null) {
            $mods[] = $mod_name;
        } else {
            $extensionService = $this->di['mod_service']('extension');
            $mods = $extensionService->getCoreAndActiveModules();
        }

        foreach ($mods as $m) {
            $mod = $this->di['mod']($m);
            if ($mod->hasService()) {
                $class = $mod->getService();
                $reflector = new \ReflectionClass($class);
                foreach ($reflector->getMethods() as $method) {
                    if ($this->canBeConnected($method)) {
                        $this->connect(['event' => $method->getName(), 'mod' => $mod->getName()]);
                    }
                }
            }
        }

        return true;
    }

    private function canBeConnected(\ReflectionMethod $method)
    {
        $parameters = $method->getParameters();
        if (!isset($parameters[0]) || !$method->isPublic()) {
            return false;
        }

        $type = $parameters[0]->getType() instanceof \ReflectionNamedType ? $parameters[0]->getType()->getName() : null;
        if ($type == 'Box_Event' || $type == "\Box_Event") {
            return true;
        }

        return false;
    }

    /**
     * Connect event for module.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    private function connect($data)
    {
        $required = [
            'event' => 'Hook event not passed',
            'mod' => 'Param mod not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $event = $data['event'];
        $mod = $data['mod'];

        $q = "SELECT id
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND rel_id = :mod
            AND meta_key = 'listener'
            AND meta_value = :event
        ";
        if ($this->di['db']->getCell($q, ['mod' => $mod, 'event' => $event])) {
            // already connected
            return true;
        }

        $meta = $this->di['db']->dispense('extension_meta');
        $meta->extension = 'mod_hook';
        $meta->rel_type = 'mod';
        $meta->rel_id = $mod;
        $meta->meta_key = 'listener';
        $meta->meta_value = $event;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);

        return true;
    }

    /**
     * Disconnect unavailable listeners.
     */
    private function _disconnectUnavailable()
    {
        $rm_sql = 'DELETE FROM extension_meta WHERE id = :id';

        $sql = "SELECT id, rel_id, meta_value
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
        ";
        $list = $this->di['db']->getAll($sql);
        $extensionService = $this->di['mod_service']('extension');
        foreach ($list as $listener) {
            try {
                $mod_name = $listener['rel_id'];
                $event = $listener['meta_value'];

                // disconnect modules without service class
                $mod = $this->di['mod']($mod_name);
                if (!$mod->hasService()) {
                    $this->di['db']->exec($rm_sql, ['id' => $listener['id']]);

                    continue;
                }

                // Remove listeners that don't exist or aren't actually hooks
                $s = $mod->getService();
                $reflector = new \ReflectionClass($s);
                if (!$reflector->hasMethod($event) || !$this->canBeConnected($reflector->getMethod($event))) {
                    $this->di['db']->exec($rm_sql, ['id' => $listener['id']]);

                    continue;
                }

                // If the listener is for a module that's not installed and is **not** a core module, remove the listener
                $ext = $this->di['db']->findOne('extension', "type = 'mod' AND name = :mod AND status = 'installed'", ['mod' => $mod_name]);
                if (!$ext && !$extensionService->isCoreModule($mod_name)) {
                    $this->di['db']->exec($rm_sql, ['id' => $listener['id']]);

                    continue;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
    }
}

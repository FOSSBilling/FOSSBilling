<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Hook;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
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
            if (is_object($ext) && 'mod' == $ext->type) {
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
        if ('mod' == $params['type']) {
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
     * @param string $mod module name
     *
     * @return bool
     */
    public function batchConnect($mod_name = null)
    {
        $mods = [];
        if (null !== $mod_name) {
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
                    $p = $method->getParameters();
                    if ($method->isPublic()
                        && isset($p[0])
                        && $p[0]->getclass()
                        && in_array($p[0]->getclass()->getName(), ['Box_Event', '\Box_Event'])) {
                        $this->connect(['event' => $method->getName(), 'mod' => $mod->getName()]);
                    }
                }
            }
        }

        $this->_disconnectUnavailable();

        return true;
    }

    /**
     * Connect event for module.
     *
     * @param string $event - event name, ie: onAfterAdminDeactivateExtension
     * @param string $mod   - module name where hook is located
     *
     * @return bool
     *
     * @throws Box_Exception
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

                // do not disconnect core modules
                if ($extensionService->isCoreModule($mod_name)) {
                    continue;
                }

                // disconect modules without service class
                $mod = $this->di['mod']($mod_name);
                if (!$mod->hasService()) {
                    $this->di['db']->exec($rm_sql, ['id' => $listener['id']]);
                    continue;
                }

                $ext = $this->di['db']->findOne('extension', "type = 'mod' AND name = :mod AND status = 'installed'", ['mod' => $mod_name]);
                if (!$ext) {
                    $this->di['db']->exec($rm_sql, ['id' => $listener['id']]);
                    continue;
                }

                $s = $mod->getService();
                if (!method_exists($s, $event)) {
                    $this->di['db']->exec($rm_sql, ['id' => $listener['id']]);
                    continue;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
    }
}

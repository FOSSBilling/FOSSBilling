<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Hook;

use Box\Mod\Extension\Entity\Extension;
use Box\Mod\Extension\Entity\ExtensionMeta;
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
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View hooks'),
                'description' => __trans('Allows the staff member to view registered event hooks.'),
            ],
            'manage_hooks' => [
                'type' => 'bool',
                'display_name' => __trans('Manage hooks'),
                'description' => __trans('Allows the staff member to reconnect registered event hooks.'),
            ],
            'trigger_hooks' => [
                'type' => 'bool',
                'display_name' => __trans('Trigger hooks'),
                'description' => __trans('Allows the staff member to invoke hooks manually with custom event payloads.'),
            ],
        ];
    }

    public function getSearchQuery($filter): array
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

    public static function onAfterAdminActivateExtension(\Box_Event $event): void
    {
        $params = $event->getParameters();
        if (!isset($params['id'])) {
            $event->setReturnValue(false);
        } else {
            $di = $event->getDi();
            $ext = $di['em']->getRepository(Extension::class)->find($params['id']);
            if ($ext !== null && $ext->getType() === Extension::TYPE_MOD) {
                $service = $di['mod_service']('hook');
                $service->batchConnect($ext->getName());
            }
            $event->setReturnValue(true);
        }
    }

    public static function onAfterAdminDeactivateExtension(\Box_Event $event): void
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        if ($params['type'] == 'mod') {
            $q = "DELETE FROM extension_meta
                WHERE extension = 'mod_hook'
                AND rel_type = 'mod'
                AND rel_id = :mod
                AND meta_key = 'listener'";
            $di['em']->getConnection()->executeStatement($q, ['mod' => $params['id']]);
        }

        $event->setReturnValue(true);
    }

    public function batchConnect($mod_name = null): bool
    {
        // Clean up the existing list before we add to it
        $this->_disconnectUnavailable();
        $extensionService = $this->di['mod_service']('extension');

        $mods = [];
        if ($mod_name !== null) {
            $mods[] = $mod_name;
        } else {
            $mods = $extensionService->getCoreAndActiveModules();
        }

        foreach ($mods as $m) {
            $ext = $this->di['em']->getRepository(Extension::class)->findOneBy(['type' => 'mod', 'name' => $m, 'status' => 'installed']);
            if (!$ext && !$extensionService->isCoreModule($m)) {
                continue;
            }

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

    private function canBeConnected(\ReflectionMethod $method): bool
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
     * @throws \FOSSBilling\Exception
     */
    private function connect($data): bool
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
        if ($this->di['em']->getConnection()->fetchOne($q, ['mod' => $mod, 'event' => $event])) {
            // already connected
            return true;
        }

        $meta = new ExtensionMeta();
        $meta->setExtension('mod_hook');
        $meta->setRelType('mod');
        $meta->setRelId($mod);
        $meta->setMetaKey('listener');
        $meta->setMetaValue($event);
        $this->di['em']->persist($meta);
        $this->di['em']->flush();

        return true;
    }

    /**
     * Disconnect unavailable listeners.
     */
    private function _disconnectUnavailable(): void
    {
        $rm_sql = 'DELETE FROM extension_meta WHERE id = :id';

        $sql = "SELECT id, rel_id, meta_value
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
        ";
        $list = $this->di['em']->getConnection()->fetchAllAssociative($sql);
        $extensionService = $this->di['mod_service']('extension');
        foreach ($list as $listener) {
            try {
                $mod_name = $listener['rel_id'];
                $event = $listener['meta_value'];

                // disconnect modules without service class
                $mod = $this->di['mod']($mod_name);
                if (!$mod->hasService()) {
                    $this->di['em']->getConnection()->executeStatement($rm_sql, ['id' => $listener['id']]);

                    continue;
                }

                // Remove listeners that don't exist or aren't actually hooks
                $s = $mod->getService();
                $reflector = new \ReflectionClass($s);
                if (!$reflector->hasMethod($event) || !$this->canBeConnected($reflector->getMethod($event))) {
                    $this->di['em']->getConnection()->executeStatement($rm_sql, ['id' => $listener['id']]);

                    continue;
                }

                // If the listener is for a module that's not installed and is **not** a core module, remove the listener
                $ext = $this->di['em']->getRepository(Extension::class)->findOneBy(['type' => 'mod', 'name' => $mod_name, 'status' => 'installed']);
                if (!$ext && !$extensionService->isCoreModule($mod_name)) {
                    $this->di['em']->getConnection()->executeStatement($rm_sql, ['id' => $listener['id']]);

                    continue;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
    }
}

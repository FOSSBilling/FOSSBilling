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
use Box\Mod\Extension\Repository\ExtensionRepository;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    private const string BATCH_CONNECT_LOCK = 'fossbilling_hook_batch_connect';

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

    /**
     * Whether any module event listener has ever been connected.
     *
     * Listeners are normally (re)connected by the cron job's hook_batch_connect task.
     * Before cron has run for the first time (e.g. right after a fresh install), this
     * is false and every event fired by the application silently has no listeners.
     */
    public function hasConnectedListeners(): bool
    {
        $q = "SELECT 1
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
            LIMIT 1
        ";

        return (bool) $this->di['em']->getConnection()->fetchOne($q);
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
            $ext = $di['em']->getRepository(Extension::class)->find((int) $params['id']);
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

    /**
     * Serializes batchConnect() runs so two concurrent callers (e.g. overlapping cron and
     * on-demand rebuilds triggered by a login) cannot interleave connect()'s check-then-insert
     * and create duplicate listener rows. The rebuild itself runs in a single DB transaction,
     * so hasConnectedListeners() can only ever observe the previous complete set or the new
     * complete set, never a partially rebuilt one.
     *
     * Returns false if the lock could not be acquired within the timeout, so a failure to
     * initialize is never mistaken for success - callers that need listeners connected before
     * proceeding should check the return value rather than assume this always succeeds.
     */
    public function batchConnect($mod_name = null): bool
    {
        $connection = $this->di['em']->getConnection();
        if ((int) $connection->fetchOne('SELECT GET_LOCK(:name, 5)', ['name' => self::BATCH_CONNECT_LOCK]) !== 1) {
            // Another process is already rebuilding the listener set and holding the lock
            // past our wait. Report failure rather than claiming a rebuild we didn't run or
            // wait for actually completed.
            return false;
        }

        try {
            $connection->transactional(function () use ($mod_name): void {
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
                    $installed = $this->getExtensionRepository()->existsActiveByTypeAndName(Extension::TYPE_MOD, $m);
                    if (!$installed && !$extensionService->isCoreModule($m)) {
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
            });

            return true;
        } finally {
            $connection->executeStatement('SELECT RELEASE_LOCK(:name)', ['name' => self::BATCH_CONNECT_LOCK]);
        }
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
                $installed = $this->getExtensionRepository()->existsActiveByTypeAndName(Extension::TYPE_MOD, $mod_name);
                if (!$installed && !$extensionService->isCoreModule($mod_name)) {
                    $this->di['em']->getConnection()->executeStatement($rm_sql, ['id' => $listener['id']]);

                    continue;
                }
            } catch (\Exception $e) {
                $this->di['logger']->error($e->getMessage());
            }
        }
    }

    private function getExtensionRepository(): ExtensionRepository
    {
        return $this->di['em']->getRepository(Extension::class);
    }
}

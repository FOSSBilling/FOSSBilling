<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Notification;

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

    public function getSearchQueryBuilder(array $filter = []): \Doctrine\ORM\QueryBuilder
    {
        $extensionService = $this->di['mod_service']('extension');

        return $extensionService->createMetaQueryBuilder('mod_notification', 'n')
            ->andWhere('n.metaKey = :metaKey')
            ->setParameter('metaKey', 'message')
            ->orderBy('n.id', 'DESC');
    }

    public function toApiArray(ExtensionMeta $row): array
    {
        return $row->toApiArray();
    }

    public function get(int $id): ExtensionMeta
    {
        $extensionService = $this->di['mod_service']('extension');
        $meta = $extensionService->getMetaById('mod_notification', $id);
        if ($meta === null || $meta->getMetaKey() !== 'message') {
            throw new \FOSSBilling\Exception('Notification message was not found');
        }

        return $meta;
    }

    public function create(string $message): int
    {
        $extensionService = $this->di['mod_service']('extension');
        $meta = $extensionService->createMeta('mod_notification', 'message', $message, 'staff', '1');

        $id = $meta->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to create notification message: missing ID after persistence.');
        }
        $this->di['events_manager']->fire(['event' => 'onAfterAdminNotificationAdd', 'params' => ['id' => $id]]);

        return $id;
    }

    public function delete(int $id): bool
    {
        $meta = $this->get($id);
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->removeMeta($meta);

        return true;
    }

    public function deleteAll(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->deleteMeta('mod_notification', 'message');

        return true;
    }
}

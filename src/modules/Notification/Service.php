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
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?ExtensionMetaRepository $extensionMetaRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->extensionMetaRepository = isset($this->di['em'])
            ? $this->di['em']->getRepository(ExtensionMeta::class)
            : null;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getExtensionMetaRepository(): ExtensionMetaRepository
    {
        if ($this->extensionMetaRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->extensionMetaRepository = $this->di['em']->getRepository(ExtensionMeta::class);
        }

        return $this->extensionMetaRepository;
    }

    public function getSearchQueryBuilder(array $filter = []): \Doctrine\ORM\QueryBuilder
    {
        return $this->getExtensionMetaRepository()
            ->createQueryBuilderForExtension('mod_notification', 'n')
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
        $meta = $this->getExtensionMetaRepository()->findOneByExtensionAndId('mod_notification', $id);
        if (!$meta instanceof ExtensionMeta || $meta->getMetaKey() !== 'message') {
            throw new \FOSSBilling\Exception('Notification message was not found');
        }

        return $meta;
    }

    public function create(string $message): int
    {
        $meta = (new ExtensionMeta())
            ->setExtension('mod_notification')
            ->setRelType('staff')
            ->setRelId('1')
            ->setMetaKey('message')
            ->setMetaValue($message);

        $this->di['em']->persist($meta);
        $this->di['em']->flush();

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
        $this->di['em']->remove($meta);
        $this->di['em']->flush();

        return true;
    }

    public function deleteAll(): bool
    {
        $this->getExtensionMetaRepository()->deleteByExtensionAndScope('mod_notification', 'message');

        return true;
    }
}

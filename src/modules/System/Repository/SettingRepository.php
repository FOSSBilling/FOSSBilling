<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Repository;

use Box\Mod\System\Entity\Setting;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

class SettingRepository extends EntityRepository
{
    /** @var array<string, Setting|null> */
    private array $settingsByParam = [];

    /** @var array<string, Setting|null> */
    private array $publicSettingsByParam = [];

    /** @var array<string, list<Setting>> */
    private array $settingsByParams = [];

    private bool $clearCacheAfterFlush = false;

    private readonly Connection $connection;

    /** @param ClassMetadata<Setting> $class */
    public function __construct(EntityManagerInterface $entityManager, ClassMetadata $class)
    {
        parent::__construct($entityManager, $class);
        $this->connection = $entityManager->getConnection();
        $entityManager->getEventManager()->addEventListener([Events::onClear, Events::onFlush, Events::postFlush], $this);
    }

    public function findOneByParam(string $param): ?Setting
    {
        $useCache = $this->canUseRequestCache();
        if ($useCache && array_key_exists($param, $this->settingsByParam)) {
            return $this->settingsByParam[$param];
        }

        $setting = $this->findOneBy(['param' => $param]);
        $setting = $setting instanceof Setting ? $setting : null;
        if ($useCache) {
            $this->settingsByParam[$param] = $setting;
        }

        return $setting;
    }

    public function findOnePublicByParam(string $param): ?Setting
    {
        $useCache = $this->canUseRequestCache();
        if ($useCache && array_key_exists($param, $this->publicSettingsByParam)) {
            return $this->publicSettingsByParam[$param];
        }

        // Public lookups must not reuse unrestricted results.
        $setting = $this->findOneBy(['param' => $param, 'public' => true]);
        $setting = $setting instanceof Setting ? $setting : null;
        if ($useCache) {
            $this->publicSettingsByParam[$param] = $setting;
        }

        return $setting;
    }

    /**
     * @param string[] $params
     *
     * @return Setting[]
     */
    public function findByParams(array $params): array
    {
        if (!$this->canUseRequestCache()) {
            return $this->findBy(['param' => $params]);
        }

        // Cache the whole result to preserve database ordering and collation.
        $cacheKey = serialize($params);

        return $this->settingsByParams[$cacheKey] ??= $this->findBy(['param' => $params]);
    }

    public function clearRequestCache(): void
    {
        $this->settingsByParam = [];
        $this->publicSettingsByParam = [];
        $this->settingsByParams = [];
    }

    public function onClear(OnClearEventArgs $event): void
    {
        $this->clearRequestCache();
        $this->clearCacheAfterFlush = false;
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $unitOfWork = $event->getObjectManager()->getUnitOfWork();
        foreach ([
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        ] as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof Setting) {
                    $this->clearRequestCache();
                    $this->clearCacheAfterFlush = true;

                    return;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($this->clearCacheAfterFlush) {
            $this->clearRequestCache();
            $this->clearCacheAfterFlush = false;
        }
    }

    private function canUseRequestCache(): bool
    {
        if ($this->connection->isTransactionActive()) {
            // An outer transaction can roll back without an ORM invalidation event.
            $this->clearRequestCache();

            return false;
        }

        return true;
    }
}

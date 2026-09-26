<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain\Repository;

use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

class TldRepository extends EntityRepository
{
    private ?Connection $connection = null;
    /** @var list<Tld>|null */
    private ?array $activeTlds = null;
    private bool $invalidateAfterFlush = false;

    /** @param ClassMetadata<Tld> $class */
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->connection = $em->getConnection();
        $em->getEventManager()->addEventListener(['onClear', 'onFlush', 'postFlush'], $this);
    }

    /**
     * @return Tld[]
     */
    public function findAllActive(): array
    {
        if ($this->connection?->isTransactionActive()) {
            $this->activeTlds = null;

            return $this->queryAllActive();
        }

        return $this->activeTlds ??= $this->queryAllActive();
    }

    public function onClear(OnClearEventArgs $event): void
    {
        if ($event->getObjectManager() === $this->getEntityManager()) {
            $this->activeTlds = null;
            $this->invalidateAfterFlush = false;
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if ($event->getObjectManager() !== $this->getEntityManager()) {
            return;
        }

        $unitOfWork = $this->getEntityManager()->getUnitOfWork();

        foreach ([
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        ] as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof Tld || $entity instanceof TldRegistrar) {
                    $this->activeTlds = null;
                    $this->invalidateAfterFlush = true;

                    break 2;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($event->getObjectManager() === $this->getEntityManager() && $this->invalidateAfterFlush) {
            $this->activeTlds = null;
            $this->invalidateAfterFlush = false;
        }
    }

    /** @return list<Tld> */
    private function queryAllActive(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.registrar', 'tr')
            ->addSelect('tr')
            ->where('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pricing for every active TLD, keyed by the TLD string, matching the legacy
     * domain-pricing array shape consumed by the Product service.
     *
     * @return array<string, array{
     *     tld: ?string,
     *     price_registration: ?string,
     *     price_renew: ?string,
     *     price_transfer: ?string,
     *     active: int,
     *     allow_register: int|null,
     *     allow_transfer: int|null,
     *     min_years: ?int,
     *     periods: int[]|null,
     *     registrar: array{id: ?int, title: ?string},
     * }>
     */
    public function getActivePricing(): array
    {
        $pricing = [];
        foreach ($this->findAllActive() as $tld) {
            $tldName = $tld->getTld();
            if ($tldName === null) {
                continue;
            }

            $registrar = $tld->getRegistrar();
            $pricing[$tldName] = [
                'tld' => $tldName,
                'price_registration' => $tld->getPriceRegistration(),
                'price_renew' => $tld->getPriceRenew(),
                'price_transfer' => $tld->getPriceTransfer(),
                'active' => (int) $tld->isActive(),
                'allow_register' => $tld->isAllowRegister() === null ? null : (int) $tld->isAllowRegister(),
                'allow_transfer' => $tld->isAllowTransfer() === null ? null : (int) $tld->isAllowTransfer(),
                'min_years' => $tld->getMinYears(),
                'periods' => $tld->getPeriodsArray(),
                'registrar' => [
                    'id' => $registrar?->getId(),
                    'title' => $registrar?->getName(),
                ],
            ];
        }

        return $pricing;
    }

    public function findOneByTld(string $tld): ?Tld
    {
        $result = $this->findOneBy(['tld' => $tld]);

        return $result instanceof Tld ? $result : null;
    }

    public function findOneActiveById(int $id): ?Tld
    {
        $result = $this->findOneBy(['id' => $id, 'active' => true]);

        return $result instanceof Tld ? $result : null;
    }

    /**
     * @return array<int, string>
     */
    public function getIdTldPairs(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.id, t.tld')
            ->where('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($result as $row) {
            $pairs[(int) $row['id']] = $row['tld'];
        }

        return $pairs;
    }
}

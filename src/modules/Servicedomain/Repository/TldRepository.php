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
use Doctrine\ORM\EntityRepository;

class TldRepository extends EntityRepository
{
    /**
     * @return Tld[]
     */
    public function findAllActive(): array
    {
        return $this->findBy(['active' => true], ['id' => 'ASC']);
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
        $registrarNames = [];
        foreach ($this->getEntityManager()->getRepository(TldRegistrar::class)->findAll() as $registrar) {
            if ($registrar->getId() !== null) {
                $registrarNames[$registrar->getId()] = $registrar->getName();
            }
        }

        $pricing = [];
        foreach ($this->findAllActive() as $tld) {
            $tldName = $tld->getTld();
            if ($tldName === null) {
                continue;
            }

            $registrarId = $tld->getTldRegistrarId();
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
                    'id' => $registrarId,
                    'title' => $registrarId === null ? null : ($registrarNames[$registrarId] ?? null),
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

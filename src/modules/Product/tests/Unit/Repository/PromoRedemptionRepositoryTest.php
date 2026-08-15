<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\PromoRedemption;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function promoRedemptionEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBillingTestProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

// The promo is mapped as a `promo` association rather than a `promoId` field, so these queries
// have to filter on the association or they fail to compile at all.
test('promo filtered queries match only redemptions of the given promo', function (): void {
    $entityManager = promoRedemptionEntityManager();
    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Promo::class, PromoRedemption::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $promo = new Promo();
    $unused = new Promo();
    $entityManager->persist($promo);
    $entityManager->persist($unused);

    $entityManager->persist((new PromoRedemption())
        ->setPromo($promo)
        ->setClientId(1)
        ->setClientOrderId(1)
        ->setPhase(PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(PromoRedemption::STATUS_COMMITTED));
    $entityManager->flush();

    $repository = $entityManager->getRepository(PromoRedemption::class);
    $promoId = (int) $promo->getId();
    $unusedId = (int) $unused->getId();

    expect($repository->countByPromoId($promoId))->toBe(1)
        ->and($repository->countByPromoId($unusedId))->toBe(0);

    expect($repository->clientHasActiveCheckoutApplication($promoId, 1))->toBeTrue()
        ->and($repository->clientHasActiveCheckoutApplication($unusedId, 1))->toBeFalse()
        ->and($repository->clientHasActiveCheckoutApplication($promoId, 2))->toBeFalse();

    expect($repository->getUsageStatsByPromoId($promoId))
        ->toMatchArray([
            'recorded_applications' => 1,
            'checkout_applications' => 1,
            'committed_applications' => 1,
            'distinct_clients' => 1,
        ])
        ->and($repository->getUsageStatsByPromoId($unusedId)['recorded_applications'])->toBe(0);

    expect($repository->getSearchQueryBuilder(['promo_id' => $promoId])->getQuery()->getResult())->toHaveCount(1)
        ->and($repository->getSearchQueryBuilder(['promo_id' => $unusedId])->getQuery()->getResult())->toBe([]);
});

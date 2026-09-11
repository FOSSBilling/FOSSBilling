<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Email\Entity\EmailTemplate;
use Box\Mod\Email\Entity\EmailTemplateGroup;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function templateGroupEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('countTemplatesUsingGroup counts distinct templates per group', function (): void {
    $entityManager = templateGroupEntityManager();
    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [EmailTemplate::class, EmailTemplateGroup::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $templateA = new EmailTemplate('mod_email_a');
    $templateB = new EmailTemplate('mod_email_b');
    $entityManager->persist($templateA);
    $entityManager->persist($templateB);

    // Group 5 references both templates; group 6 references only template A.
    $entityManager->persist(new EmailTemplateGroup($templateA, 5));
    $entityManager->persist(new EmailTemplateGroup($templateB, 5));
    $entityManager->persist(new EmailTemplateGroup($templateA, 6));
    $entityManager->flush();

    $repository = $entityManager->getRepository(EmailTemplateGroup::class);

    expect($repository->countTemplatesUsingGroup(5))->toBe(2)
        ->and($repository->countTemplatesUsingGroup(6))->toBe(1)
        ->and($repository->countTemplatesUsingGroup(99))->toBe(0);
});

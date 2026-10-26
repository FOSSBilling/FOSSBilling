<?php

declare(strict_types=1);

use FOSSBilling\Doctrine\JsonFields;

test('the entire inventory uses standard JSON mappings and exclusions retain their intended types', function (): void {
    $em = FOSSBilling\Doctrine\EntityManagerFactory::create(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    $count = 0;
    foreach (JsonFields::FIELDS as $definition) {
        $metadata = $em->getClassMetadata($definition['entity']);
        foreach ($definition['columns'] as $property) {
            expect($metadata->getTypeOfField($property))->toBe(Doctrine\DBAL\Types\Types::JSON);
            ++$count;
        }
    }
    expect($count)->toBe(27)
        ->and($em->getClassMetadata(Box\Mod\Email\Entity\EmailTemplate::class)->hasField('vars'))->toBeFalse()
        ->and($em->getClassMetadata(Box\Mod\Staff\Entity\AdminGroup::class)->getTypeOfField('permissions'))->toBe(Doctrine\DBAL\Types\Types::JSON)
        ->and($em->getClassMetadata(Box\Mod\Invoice\Entity\InvoiceEvent::class)->getTypeOfField('snapshot'))->toBe(Doctrine\DBAL\Types\Types::JSON);
});

<?php

declare(strict_types=1);

use FOSSBilling\Doctrine\JsonFields;

$fields = [];
foreach (JsonFields::FIELDS as $definition) {
    foreach ($definition['columns'] as $property) {
        $fields[$definition['entity'] . '::' . $property] = [$definition['entity'], ucfirst($property)];
    }
}

test('all migrated fields expose nullable arrays without legacy string or Data adapters', function (string $class, string $field): void {
    $entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $get = 'get' . $field;
    $set = 'set' . $field;
    expect(method_exists($class, $get . 'Data'))->toBeFalse();
    foreach ([null, [], ['id' => '001', 'amount' => 1.0, 'enabled' => false, 'nested' => ['label' => '🚀']]] as $value) {
        $entity->$set($value);
        expect($entity->$get())->toBe($value);
    }
    $copy = $entity->$get();
    $copy['nested']['label'] = 'changed';
    expect($entity->$get()['nested']['label'])->toBe('🚀');
    expect(fn () => $entity->$set('{"old":"string"}'))->toThrow(TypeError::class);
    expect(fn () => $entity->$set(new stdClass()))->toThrow(TypeError::class);
})->with($fields);

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
    expect($count)->toBe(24)
        ->and($em->getClassMetadata(Box\Mod\Email\Entity\EmailTemplate::class)->getTypeOfField('vars'))->toBe(Doctrine\DBAL\Types\Types::TEXT)
        ->and($em->getClassMetadata(Box\Mod\Staff\Entity\AdminGroup::class)->getTypeOfField('permissions'))->toBe(Doctrine\DBAL\Types\Types::JSON)
        ->and($em->getClassMetadata(Box\Mod\Invoice\Entity\InvoiceEvent::class)->getTypeOfField('snapshot'))->toBe(Doctrine\DBAL\Types\Types::JSON);
});

<?php

declare(strict_types=1);

use FOSSBilling\Doctrine\JsonFields;

$fields = [];
foreach (JsonFields::FIELDS as $definition) {
    foreach ($definition['columns'] as $property) {
        $fields[$definition['entity'] . '::' . $property] = [$definition['entity'], ucfirst($property)];
    }
}

test('migrated JSON entity accessors preserve legacy strings and support structured updates', function (string $class, string $field): void {
    expect(method_exists($class, 'get' . $field . 'Data'))->toBeTrue();
    $entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $get = 'get' . $field;
    $set = 'set' . $field;
    $getData = $get . 'Data';
    $setData = $set . 'Data';
    foreach ([null, '', '{invalid', 'null', 'false', '42', '"legacy"', '{}', '[]', '{ "label": "🚀", "n": 1.0, "id": "001" }'] as $raw) {
        $entity->$set($raw);
        expect($entity->$get())->toBe($raw);
        $entity->$getData();
        expect($entity->$get())->toBe($raw);
    }
    $entity->$setData(['id' => '001', 'enabled' => false, 'nested' => ['label' => '🚀']]);
    expect($entity->$getData())->toBe(['id' => '001', 'enabled' => false, 'nested' => ['label' => '🚀']])
        ->and(json_decode($entity->$get(), true, 512, JSON_THROW_ON_ERROR))->toBe($entity->$getData());
    $data = $entity->$getData();
    $data['nested']['label'] = 'changed';
    expect($entity->$getData()['nested']['label'])->toBe('🚀');
    $entity->$setData(new stdClass());
    expect($entity->$get())->toBe('{}');
    $entity->$setData([]);
    expect($entity->$get())->toBe('[]');
})->with($fields);

test('the complete migration inventory uses the JSON document type and leaves encrypted fields alone', function (): void {
    $em = FOSSBilling\Doctrine\EntityManagerFactory::create(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    $count = 0;
    foreach (JsonFields::FIELDS as $definition) {
        $metadata = $em->getClassMetadata($definition['entity']);
        foreach ($definition['columns'] as $property) {
            expect($metadata->getTypeOfField($property))->toBe(FOSSBilling\Doctrine\Type\JsonDocumentType::NAME);
            ++$count;
        }
    }
    expect($count)->toBe(24)
        ->and($em->getClassMetadata(Box\Mod\Email\Entity\EmailTemplate::class)->getTypeOfField('vars'))->toBe(Doctrine\DBAL\Types\Types::TEXT)
        ->and($em->getClassMetadata(Box\Mod\Staff\Entity\AdminGroup::class)->getTypeOfField('permissions'))->toBe(Doctrine\DBAL\Types\Types::JSON)
        ->and($em->getClassMetadata(Box\Mod\Invoice\Entity\InvoiceEvent::class)->getTypeOfField('snapshot'))->toBe(Doctrine\DBAL\Types\Types::JSON);
});

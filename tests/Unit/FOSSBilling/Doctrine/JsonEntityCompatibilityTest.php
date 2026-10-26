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
    if (!method_exists($class, 'get' . $field . 'Data')) {
        $this->markTestSkipped('This module is not yet migrated.');
    }
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

<?php

declare(strict_types=1);

use Doctrine\DBAL\Platforms\SQLitePlatform;
use FOSSBilling\Doctrine\JsonDocument;
use FOSSBilling\Doctrine\Type\JsonDocumentType;

test('JSON documents preserve legacy bytes on hydration and persistence', function (?string $raw): void {
    $type = new JsonDocumentType();
    $platform = new SQLitePlatform();
    $document = $type->convertToPHPValue($raw, $platform);
    expect($type->convertToDatabaseValue($document, $platform))->toBe($raw);
})->with([null, '', 'not json', '{broken', 'null', 'false', '42', '"legacy"', '[]', '{}', '{ "id": "001", "n": 1.0, "text": "🚀" }', '"{\\"a\\":1}"']);

test('structured JSON writes encode once and retain object/list distinctions', function (): void {
    $type = new JsonDocumentType();
    $platform = new SQLitePlatform();
    $document = JsonDocument::fromData(['id' => '001', 'enabled' => false, 'label' => '🚀', 'object' => new stdClass(), 'list' => []]);
    $stored = $type->convertToDatabaseValue($document, $platform);
    $loaded = $type->convertToPHPValue($stored, $platform);
    expect($loaded->arrayData())->toBe(['id' => '001', 'enabled' => false, 'label' => '🚀', 'object' => [], 'list' => []])
        ->and($loaded->value()->object)->toBeInstanceOf(stdClass::class)
        ->and($loaded->value()->list)->toBe([])
        ->and($type->convertToDatabaseValue(['label' => '🚀'], $platform))->toBe('{"label":"\\ud83d\\ude80"}');
    expect(fn () => $type->convertToDatabaseValue('{"a":1}', $platform))->toThrow(InvalidArgumentException::class);
});

test('decoding never mutates the stored JSON document', function (): void {
    $document = JsonDocument::fromJson('{"nested":{"n":1}}');
    $object = $document->value();
    $object->nested->n = 2;
    $data = $document->arrayData();
    $data['nested']['n'] = 3;
    expect($document->json())->toBe('{"nested":{"n":1}}');
    $invalid = JsonDocument::fromJson('bad');
    expect($invalid->arrayData())->toBeNull();
    expect(fn () => $invalid->value())->toThrow(JsonException::class);
});

<?php

declare(strict_types=1);

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use FOSSBilling\Doctrine\JsonMigrationValidation;

test('standard Doctrine JSON selects native declarations and a text fallback', function (): void {
    $type = Type::getType(Types::JSON);
    expect($type->getSQLDeclaration([], new MySQLPlatform()))->toBe('JSON')
        ->and($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('JSON')
        ->and($type->getSQLDeclaration([], new SQLitePlatform()))->toBe('CLOB');
    $data = ['id' => '001', 'fraction' => 1.0, 'enabled' => false, 'label' => '🚀', 'list' => []];
    expect($type->convertToPHPValue($type->convertToDatabaseValue($data, new SQLitePlatform()), new SQLitePlatform()))->toBe($data);
});

test('preflight rejects documents that cannot safely populate array fields', function (string $raw): void {
    expect(fn () => JsonMigrationValidation::assertCompatible($raw))->toThrow(JsonException::class);
})->with(['{"a":1,"a":2}', '{"a":1,"\\u0061":2}', '{"nested":{"k":1,"k":2}}', '{"n":9223372036854775808}', '{"n":1e999}', '{broken', '{"n":0.123456789012345678901}', '{"n":1e-999}', '{"0":"object"}', '42', 'false', '"scalar"', '"{\\"a\\":1}"']);

test('preflight accepts structured documents, null and supported numbers', function (string $raw): void {
    JsonMigrationValidation::assertCompatible($raw);
    expect(true)->toBeTrue();
})->with(['{"a":1,"b":{"a":2},"items":[{"a":3},{"a":4}]}', json_encode(['quote' => 'a"b', 'brace' => '{}[]:,', 'x' => 1], JSON_THROW_ON_ERROR), '{"n":9223372036854775807}', 'null', '{}', '[]', '{"n":1e2,"m":0.1,"a":-10.50}', '{"01":"id"}']);

<?php

declare(strict_types=1);

function invokeDirectadminParseResponse(Server_Manager_Directadmin $manager, string $data): array
{
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('parseResponse');

    return $method->invokeArgs($manager, [$data]);
}

beforeEach(function (): void {
    $this->manager = new Server_Manager_Directadmin([
        'host' => 'directadmin.example.com',
        'username' => 'admin',
        'password' => 'secret',
    ]);
});

test('parseResponse decodes the fully-terminated apostrophe entity without a trailing semicolon', function (): void {
    $result = invokeDirectadminParseResponse($this->manager, 'name=O&#39;Brien');

    expect($result['name'])->toBe("O'Brien");
});

test('parseResponse decodes the legacy unterminated apostrophe entity', function (): void {
    $result = invokeDirectadminParseResponse($this->manager, 'name=O&#39Brien');

    expect($result['name'])->toBe("O'Brien");
});

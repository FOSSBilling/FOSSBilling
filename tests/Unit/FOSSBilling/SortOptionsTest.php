<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

dataset('directionProvider', fn (): array => [
    ['ASC', 'ASC'],
    ['asc', 'ASC'],
    ['DESC', 'DESC'],
    ['desc', 'DESC'],
    [' Desc ', 'DESC'],
    ['invalid', 'ASC'],
    ['', 'ASC'],
    [null, 'ASC'],
    [123, 'ASC'],
    [['DESC'], 'ASC'],
]);

test('resolves a valid sort key and direction', function (): void {
    $sort = FOSSBilling\SortOptions::fromArray(
        ['sort' => 'tld', 'direction' => 'DESC'],
        ['tld' => 't.tld', 'id' => 't.id']
    );

    expect($sort->expression)->toBe('t.tld')
        ->and($sort->direction)->toBe('DESC')
        ->and($sort->isSorted())->toBeTrue()
        ->and($sort->toOrderByClause())->toBe('t.tld DESC');
});

test('direction resolves case-insensitively with fallback', function (mixed $input, string $expected): void {
    $sort = FOSSBilling\SortOptions::fromArray(
        ['sort' => 'id', 'direction' => $input],
        ['id' => 't.id']
    );

    expect($sort->direction)->toBe($expected);
})->with('directionProvider');

test('unknown sort keys fall back to unsorted instead of throwing', function (): void {
    $allowed = ['tld' => 't.tld'];

    foreach ([[], ['sort' => 'nonexistent'], ['sort' => 't.tld'], ['sort' => 'tld; DROP TABLE tld'], ['sort' => 123], ['sort' => ['tld']]] as $data) {
        $sort = FOSSBilling\SortOptions::fromArray($data, $allowed);

        expect($sort->expression)->toBeNull()
            ->and($sort->isSorted())->toBeFalse()
            ->and($sort->toOrderByClause())->toBeNull();
    }
});

test('sort keys match case-insensitively', function (): void {
    $sort = FOSSBilling\SortOptions::fromArray(['sort' => 'TLD'], ['tld' => 't.tld']);

    expect($sort->expression)->toBe('t.tld');
});

test('supports custom parameter names', function (): void {
    $sort = FOSSBilling\SortOptions::fromArray(
        ['registrar_sort' => 'title', 'registrar_direction' => 'desc'],
        ['title' => 'tr.name'],
        'registrar_sort',
        'registrar_direction'
    );

    expect($sort->expression)->toBe('tr.name')
        ->and($sort->direction)->toBe('DESC')
        ->and($sort->sortParam)->toBe('registrar_sort')
        ->and($sort->directionParam)->toBe('registrar_direction');
});

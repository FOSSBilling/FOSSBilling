<?php

declare(strict_types=1);

use Box\Mod\Product\PromoCondition;

test('empty input means no condition', function (mixed $input): void {
    $condition = PromoCondition::fromArray($input);

    expect($condition->isEmpty())->toBeTrue();
    expect($condition->toStorage())->toBeNull();
    expect($condition->isMet([]))->toBeTrue();
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty list' => [[]],
    'only blanks from the admin form' => [['']],
]);

test('a plain id list keeps the original all-required behaviour and storage format', function (): void {
    $condition = PromoCondition::fromStorage('[5,"7",0,-2,5]');

    expect($condition->toArray())->toBe([5, 7]);
    expect($condition->toStorage())->toBe('[5,7]');
    expect($condition->isMet([5 => 1, 7 => 1]))->toBeTrue();
    expect($condition->findUnmet([5 => 1]))->toBe([['product' => 7]]);
});

test('a plain id list may not contain objects', function (): void {
    PromoCondition::fromArray([['product' => 5]]);
})->throws(FOSSBilling\InformationException::class);

test('hosting plus any one of the add-ons', function (): void {
    $condition = PromoCondition::fromArray(['all' => [
        ['product' => 12],
        ['pick' => 1, 'from' => [['product' => 14], ['product' => 15], ['product' => 16]]],
    ]]);

    expect($condition->isMet([12 => 1, 15 => 1]))->toBeTrue();
    expect($condition->isMet([12 => 1, 14 => 1, 16 => 1]))->toBeTrue();
    expect($condition->findUnmet([12 => 1]))->toBe([
        ['pick' => 1, 'from' => [['product' => 14], ['product' => 15], ['product' => 16]]],
    ]);
    expect($condition->findUnmet([15 => 1]))->toBe([['product' => 12]]);
    expect($condition->getProductIds())->toBe([12, 14, 15, 16]);
});

test('pick counts distinct products, not units', function (): void {
    $condition = PromoCondition::fromArray(['all' => [
        ['pick' => 2, 'from' => [['product' => 14], ['product' => 15], ['product' => 16]]],
    ]]);

    expect($condition->isMet([14 => 5]))->toBeFalse();
    expect($condition->isMet([14 => 1, 16 => 1]))->toBeTrue();
});

test('min_qty counts units of one product', function (): void {
    $condition = PromoCondition::fromArray(['all' => [['product' => 14, 'min_qty' => 3]]]);

    expect($condition->isMet([14 => 2]))->toBeFalse();
    expect($condition->isMet([14 => 3]))->toBeTrue();
    expect($condition->findUnmet([14 => 2]))->toBe([['product' => 14, 'min_qty' => 3]]);
});

test('products inside a group can carry their own min_qty', function (): void {
    $condition = PromoCondition::fromArray(['all' => [
        ['pick' => 1, 'from' => [['product' => 14, 'min_qty' => 2], ['product' => 15]]],
    ]]);

    expect($condition->isMet([14 => 1]))->toBeFalse();
    expect($condition->isMet([14 => 2]))->toBeTrue();
    expect($condition->isMet([15 => 1]))->toBeTrue();
});

test('rich conditions round-trip through storage unchanged', function (): void {
    $data = ['all' => [
        ['product' => 12, 'min_qty' => 2],
        ['pick' => 1, 'from' => [['product' => 14], ['product' => 15]]],
    ]];

    $stored = PromoCondition::fromArray($data)->toStorage();

    expect(PromoCondition::fromStorage($stored)->toArray())->toBe($data);
});

test('a rich condition that only lists products is stored as a plain list', function (): void {
    expect(PromoCondition::fromArray(['all' => [['product' => 12], ['product' => '14']]])->toStorage())->toBe('[12,14]');
});

test('invalid conditions are rejected', function (array $input): void {
    PromoCondition::fromArray($input);
})->throws(FOSSBilling\InformationException::class)->with([
    'unknown top-level key' => [['any' => [['product' => 1]]]],
    'all is not a list' => [['all' => ['product' => 1]]],
    'unknown product key' => [['all' => [['product' => 1, 'qty' => 2]]]],
    'zero product id' => [['all' => [['product' => 0]]]],
    'zero min_qty' => [['all' => [['product' => 1, 'min_qty' => 0]]]],
    'fractional min_qty' => [['all' => [['product' => 1, 'min_qty' => 1.5]]]],
    'pick of zero' => [['all' => [['pick' => 0, 'from' => [['product' => 1]]]]]],
    'pick larger than group' => [['all' => [['pick' => 3, 'from' => [['product' => 1], ['product' => 2]]]]]],
    'empty group' => [['all' => [['pick' => 1, 'from' => []]]]],
    'nested group' => [['all' => [['pick' => 1, 'from' => [['pick' => 1, 'from' => [['product' => 1]]]]]]]],
    'duplicate product in group' => [['all' => [['pick' => 1, 'from' => [['product' => 1], ['product' => 1]]]]]],
]);

<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/vendor',
        __DIR__ . '/data',
        __DIR__ . '/library',
        UnionTypesRector::class,
        MixedTypeRector::class,
    ]);

    $rectorConfig->sets([
        SetList::PHP_80,
    ]);
};

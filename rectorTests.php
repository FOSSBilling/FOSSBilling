<?php

declare(strict_types=1);

use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        JsonThrowOnErrorRector::class,
        LongArrayToShortArrayRector::class, // As much as I'd like to use this, Rector will destroy the formatting of large, multi-line arrays when it applies this & PHP-CS-Fixer doesn't fix it.
        NullToStrictStringFuncCallArgRector::class,
    ]);


    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        PHPUnitSetList::PHPUNIT_100,
    ]);

    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./cache/rector_tests');

    // Reduced number of processes from 16 to 8. Job size reduced from 15 to 10. (Hoping to resolve GHA errors)
    $rectorConfig->parallel(120, 8, 10);
};

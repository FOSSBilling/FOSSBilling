<?php

declare(strict_types=1);

use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/src/vendor',
        __DIR__ . '/src/data/cache',
        JsonThrowOnErrorRector::class,
        LongArrayToShortArrayRector::class, // As much as I'd like to use this, Rector will destroy the formatting of large, multi-line arrays when it applies this & PHP-CS-Fixer doesn't fix it.
    ]);

    
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
        PHPUnitSetList::PHPUNIT_100,
    ]);
};

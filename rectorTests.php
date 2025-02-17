<?php

declare(strict_types=1);

use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/tests', __DIR__ . '/tests-legacy'])
    ->withPhpSets()
    ->withTypeCoverageLevel(2)
    ->withDeadCodeLevel(25)
    ->withSets([
        PHPUnitSetList::PHPUNIT_110,
        SetList::INSTANCEOF,
        SetList::PHP_POLYFILLS
    ])
    ->withSkip([
        LongArrayToShortArrayRector::class,
        NullToStrictStringFuncCallArgRector::class
    ])
    ->withRules([ExplicitNullableParamTypeRector::class])
    ->withCache('./cache/rector_tests', FileCacheStorage::class)
    ->withParallel(120, 8, 10);

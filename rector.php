<?php

declare(strict_types=1);

use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withSkipPath(__DIR__ . '/src/vendor')
    ->withSkipPath(__DIR__ . '/src/data/cache')
    ->withPhpSets()
    ->withTypeCoverageLevel(2)
    ->withDeadCodeLevel(5)
    ->withSkip([JsonThrowOnErrorRector::class, LongArrayToShortArrayRector::class, NullToStrictStringFuncCallArgRector::class])
    ->withRules([ExplicitNullableParamTypeRector::class])
    ->withCache('./cache/rector', FileCacheStorage::class)
    ->withParallel(120, 8, 10);

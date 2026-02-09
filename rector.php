<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/vendor',
        __DIR__ . '/src/data/cache',
    ])
    ->withAttributesSets()
    ->withPhpSets()
    ->withComposerBased(doctrine: true, phpunit: true)
    ->withTypeCoverageLevel(30)
    ->withDeadCodeLevel(30)
    ->withCache('./cache/rector', FileCacheStorage::class)
    ->withParallel(120, 8, 10);

<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromCreateMockAssignRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/tests-legacy'
    ])
    ->withSkip([
        __DIR__ . '/src/vendor',
        __DIR__ . '/src/data/cache',
        TypedPropertyFromCreateMockAssignRector::class => [
            __DIR__ . "/tests-legacy/modules/Extension/ServiceTest.php"
        ],
    ])
    ->withAttributesSets()
    ->withPhpSets()
    ->withSets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_110,
    ])
    ->withTypeCoverageLevel(30)
    ->withDeadCodeLevel(30)
    ->withCache('./cache/rector', FileCacheStorage::class)
    ->withParallel(120, 8, 10);

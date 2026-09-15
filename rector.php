<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/vendor',
        __DIR__ . '/src/data/cache',
        // Both rules only misbehave against Pest's more dynamic patterns, which
        // exist in tests/ but not src/ — kept active there.
        //
        // Rector infers this from Pest's IDE-only @mixin stub (Pest\Mixins\Expectation),
        // which doesn't match the real runtime class (Pest\Expectation) — declaring it
        // throws a TypeError the moment the closure runs. Only unsafe for closures whose
        // own body resolves to an expect() chain.
        AddArrowFunctionReturnTypeRector::class => [
            __DIR__ . '/tests',
            __DIR__ . '/src/modules/*/tests/*',
        ],
        // Pest's ->with() dataset closures get Closure::bindTo() called on them for
        // lazy evaluation, which throws for a closure derived from a first-class
        // callable reference to a plain function (no scope to rebind).
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            __DIR__ . '/tests',
            __DIR__ . '/src/modules/*/tests/*',
        ],
    ])
    ->withAttributesSets()
    ->withPhpSets()
    ->withComposerBased(doctrine: true, phpunit: true)
    ->withTypeCoverageLevel(30)
    ->withDeadCodeLevel(30)
    ->withCache('./cache/rector', FileCacheStorage::class)
    ->withParallel(120, 8, 10);

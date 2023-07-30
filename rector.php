<?php

declare(strict_types=1);

use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        //__DIR__ . '/tests', TODO: Add this. Rector actually has rulesets for PHPUnit that we may find useful. https://github.com/rectorphp/rector-phpunit/blob/main/docs/rector_rules_overview.md
    ]);

    $rectorConfig->skip([
        __DIR__ . '/src/vendor',
        JsonThrowOnErrorRector::class,
        LongArrayToShortArrayRector::class, // As much as I'd like to use this, Rector will destroy the formatting of large, multi-line arrays when it applies this & PHP-CS-Fixer doesn't fix it.
    ]);

    
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
    ]);

    // define sets of rules
    //    $rectorConfig->sets([
    //        LevelSetList::UP_TO_PHP_80
    //    ]);
};

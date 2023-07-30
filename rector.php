<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        //__DIR__ . '/tests', TODO: Add this. Rector actually has rulesets for PHPUnit that we may find useful. https://github.com/rectorphp/rector-phpunit/blob/main/docs/rector_rules_overview.md
    ]);

    $rectorConfig->skip([
        __DIR__ . '/src/vendor',
    ]);

    
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
    ]);

    // define sets of rules
    //    $rectorConfig->sets([
    //        LevelSetList::UP_TO_PHP_80
    //    ]);
};

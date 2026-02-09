<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

if (!file_exists(__DIR__ . '/src')) {
    exit(0);
}

return (new PhpCsFixer\Config())
    ->setRules([
        '@auto' => true, // Applies newest PER-CS and optimizations for PHP, based on project's composer.json file.
        '@PHP8x5Migration' => true, // Rules to improve code for PHP 8.5 compatibility.
        '@Symfony' => true, // Rules that follow the official Symfony Coding Standards.
        'concat_space' => ['spacing' => 'one'],
        'protected_to_private' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false], // Enforce non-Yoda style.
        'blank_line_before_statement' => ['statements' => ['break', 'continue', 'return', 'throw', 'try']], // Removed 'declare' from the default list.
        'get_class_to_class_keyword' => true, // Risky if the get_class function is overridden. In our case, it's not.
        'dir_constant' => true, // Risky when the function dirname is overridden. In our case, it's not.
        'array_push' => true, // Risky when the function array_push is overridden. In our case, it's not.
        'no_useless_sprintf' => true, // Risky when the function sprintf is overridden. In our case, it's not.
        'no_homoglyph_names' => true, // Risky - https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/naming/no_homoglyph_names.rst.
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
            ->exclude([
                'data',
                'locale',
                'themes',
                'vendor',
            ])
            ->notPath(
                'config-sample.php'
            )
    )
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__.'/cache/.php-cs-fixer.cache');

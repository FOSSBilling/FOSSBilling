<?php
/**
 * Copyright 2022-2023 FOSSBilling
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
        '@PHP80Migration' => true,
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'protected_to_private' => false,
        'nullable_type_declaration_for_default_null_value' => ['use_nullable_type_declaration' => false],
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false], // Enforce non-Yoda style.
        'blank_line_before_statement' => ['statements' => ['break', 'continue', 'return', 'throw', 'try']], // Removed 'declare' from the default list.
        /* Risky */
        'get_class_to_class_keyword' => true, // Risky if the get_class function is overridden. In our case, it's not.
        'dir_constant' => true, // Risky when the function dirname is overridden. In our case, it's not.
        'array_push' => true, // Risky when the function array_push is overridden. In our case, it's not.
        'no_useless_sprintf' => true, // Risky when the function sprintf is overridden. In our case, it's not.
        'php_unit_construct' => true, // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/php_unit/php_unit_construct.rst
        'php_unit_mock_short_will_return' => true, // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/php_unit/php_unit_mock_short_will_return.rst
        'no_homoglyph_names' => true, // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/naming/no_homoglyph_names.rst
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__ . '/src')
            ->exclude([
                'data',
                'library',
                'locale',
                'themes',
                'vendor',
                'install',
                'modules/Wysiwyg',
                'modules/Spamchecker'
            ])
            ->notPath(
                'config-sample.php'
            )
    )
;

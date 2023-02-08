<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

if (!file_exists(__DIR__ . '/src')) {
    exit(0);
}

$fileHeaderComment = <<<'EOF'
    FOSSBilling.

    @copyright FOSSBilling (https://www.fossbilling.org)
    @license   Apache-2.0

    Copyright FOSSBilling 2022
    This software may contain code previously used in the BoxBilling project.
    Copyright BoxBilling, Inc 2011-2021
    
    This source file is subject to the Apache-2.0 License that is bundled
    with this source code in the file LICENSE
    EOF;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP74Migration' => true,
        '@Symfony' => true,
        'header_comment' => ['header' => $fileHeaderComment, 'comment_type' => 'PHPDoc'],
        'concat_space' => ['spacing' => 'one'],
        'protected_to_private' => false,
        'nullable_type_declaration_for_default_null_value' => ['use_nullable_type_declaration' => false],
    ])
    ->setRiskyAllowed(false)
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
                'modules/Servicecentovacast',
                'modules/Servicesolusvm',
                'modules/Spamchecker'
            ])
            ->notPath(
                'config-sample.php'
            )
    )
;

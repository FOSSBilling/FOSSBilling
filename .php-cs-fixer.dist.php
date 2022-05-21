<?php

/**
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

if (!file_exists(__DIR__ . '/src')) {
    exit(0);
}

$fileHeaderComment = <<<'EOF'
    BoxBilling.

    @copyright BoxBilling, Inc (https://www.boxbilling.org)
    @license   Apache-2.0

    Copyright BoxBilling, Inc
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
                'bb-data',
                'bb-library',
                'bb-locale',
                'bb-themes',
                'bb-vendor',
                'install',
                'bb-modules/Wysiwyg',
            ])
            ->notPath('rb.php')
    )
;

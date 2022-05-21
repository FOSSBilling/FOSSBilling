<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP71Migration' => true,
        '@Symfony' => true,
        'protected_to_private' => false,
        'nullable_type_declaration_for_default_null_value' => ['use_nullable_type_declaration' => false],
        'phpdoc_to_comment' => false,
    ])
    ->setRiskyAllowed(false)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__)
            ->exclude([
                'bb-data',
                'bb-library',
                'bb-locale',
                'bb-themes',
                'vendor',
                'install',
                'bb-modules/Wysiwyg'
            ])
            ->notPath('rb.php')
    )
;

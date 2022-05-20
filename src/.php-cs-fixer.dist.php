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
                'data',
                'library',
                'locale',
                'themes',
                'vendor',
                'install',
                'modules/Wysiwyg'
            ])
            ->notPath('rb.php')
    )
;

<?php

declare(strict_types=1);

use Box\Mod\Formbuilder\Entity\Form;
use Box\Mod\Formbuilder\Entity\FormField;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

test('maps the existing form tables without changing their columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(dirname(__DIR__, 3), 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $form = $entityManager->getClassMetadata(Form::class);
    $field = $entityManager->getClassMetadata(FormField::class);

    expect($form->getTableName())->toBe('form')
        ->and($form->getColumnNames())->toBe([
            'id', 'name', 'style', 'created_at', 'updated_at',
        ])
        ->and($field->getTableName())->toBe('form_field')
        ->and($field->getColumnNames())->toBe([
            'id', 'form_id', 'name', 'label', 'hide_label', 'description', 'type',
            'default_value', 'required', 'hidden', 'readonly', 'is_unique', 'prefix',
            'suffix', 'options', 'show_initial', 'show_middle', 'show_prefix',
            'show_suffix', 'text_size', 'created_at', 'updated_at',
        ]);
});

test('stores form values and preserves an existing creation timestamp', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $form = new Form();
    $form->setName('Hosting details');
    $form->setStyle('{"type":"horizontal"}');
    $form->setCreatedAt($createdAt);

    $form->onPrePersist();

    expect($form->getName())->toBe('Hosting details')
        ->and($form->getStyle())->toBe('{"type":"horizontal"}')
        ->and($form->getCreatedAt())->toBe($createdAt)
        ->and($form->getUpdatedAt())->toBeInstanceOf(DateTime::class);
});

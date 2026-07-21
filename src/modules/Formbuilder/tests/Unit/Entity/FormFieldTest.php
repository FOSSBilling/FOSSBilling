<?php

declare(strict_types=1);

use Box\Mod\Formbuilder\Entity\FormField;

test('stores all form field values', function (): void {
    $field = (new FormField())
        ->setFormId(7)
        ->setName('domain_name')
        ->setLabel('Domain name')
        ->setHideLabel(false)
        ->setDescription('The domain to provision')
        ->setType('text')
        ->setDefaultValue('example.test')
        ->setRequired(true)
        ->setHidden(false)
        ->setReadonly(false)
        ->setIsUnique(true)
        ->setPrefix('https://')
        ->setSuffix('.test')
        ->setOptions('{"width":300}')
        ->setShowInitial('1')
        ->setShowMiddle('0')
        ->setShowPrefix('1')
        ->setShowSuffix('1')
        ->setTextSize(40);

    expect($field->getFormId())->toBe(7)
        ->and($field->getName())->toBe('domain_name')
        ->and($field->getLabel())->toBe('Domain name')
        ->and($field->isHideLabel())->toBeFalse()
        ->and($field->getDescription())->toBe('The domain to provision')
        ->and($field->getType())->toBe('text')
        ->and($field->getDefaultValue())->toBe('example.test')
        ->and($field->isRequired())->toBeTrue()
        ->and($field->isHidden())->toBeFalse()
        ->and($field->isReadonly())->toBeFalse()
        ->and($field->isUnique())->toBeTrue()
        ->and($field->getPrefix())->toBe('https://')
        ->and($field->getSuffix())->toBe('.test')
        ->and($field->getOptions())->toBe('{"width":300}')
        ->and($field->getShowInitial())->toBe('1')
        ->and($field->getShowMiddle())->toBe('0')
        ->and($field->getShowPrefix())->toBe('1')
        ->and($field->getShowSuffix())->toBe('1')
        ->and($field->getTextSize())->toBe(40);
});

test('treats nullable boolean flags as false by default', function (): void {
    $field = new FormField();

    expect($field->isHideLabel())->toBeFalse()
        ->and($field->isRequired())->toBeFalse()
        ->and($field->isHidden())->toBeFalse()
        ->and($field->isReadonly())->toBeFalse()
        ->and($field->isUnique())->toBeFalse();
});

test('manages form field timestamps', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $field = new FormField();
    $field->setCreatedAt($createdAt);

    $field->onPrePersist();

    expect($field->getCreatedAt())->toBe($createdAt)
        ->and($field->getUpdatedAt())->toBeInstanceOf(DateTime::class);
});

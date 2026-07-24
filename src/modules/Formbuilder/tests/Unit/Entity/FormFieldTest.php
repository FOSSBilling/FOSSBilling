<?php

declare(strict_types=1);

use Box\Mod\Formbuilder\Entity\FormField;

test('stores all form field values', function (): void {
    $field = new FormField();
    $field->setFormId(7);
    $field->setName('domain_name');
    $field->setLabel('Domain name');
    $field->setHideLabel(false);
    $field->setDescription('The domain to provision');
    $field->setType('text');
    $field->setDefaultValue('example.test');
    $field->setRequired(true);
    $field->setHidden(false);
    $field->setReadonly(false);
    $field->setIsUnique(true);
    $field->setPrefix('https://');
    $field->setSuffix('.test');
    $field->setOptions('{"width":300}');
    $field->setShowInitial('1');
    $field->setShowMiddle('0');
    $field->setShowPrefix('1');
    $field->setShowSuffix('1');
    $field->setTextSize(40);

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

test('preserves an existing creation timestamp', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $field = new FormField();
    $field->setCreatedAt($createdAt);

    $field->onPrePersist();

    expect($field->getCreatedAt())->toBe($createdAt)
        ->and($field->getUpdatedAt())->toBeInstanceOf(DateTime::class);
});

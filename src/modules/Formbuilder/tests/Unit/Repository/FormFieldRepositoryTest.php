<?php

declare(strict_types=1);

use Box\Mod\Formbuilder\Entity\FormField;
use Box\Mod\Formbuilder\Repository\FormFieldRepository;

test('finds fields for a form in display order', function (): void {
    $fields = [new FormField(), new FormField()];
    $repository = Mockery::mock(FormFieldRepository::class)->makePartial();
    $repository->shouldReceive('findBy')
        ->once()
        ->with(['formId' => 42], ['id' => 'ASC'])
        ->andReturn($fields);

    expect($repository->findByFormId(42))->toBe($fields);
});

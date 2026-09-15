<?php

declare(strict_types=1);

use Box\Mod\Formbuilder\Entity\Form;
use Box\Mod\Formbuilder\Entity\FormField;
use Box\Mod\Formbuilder\Repository\FormFieldRepository;

test('finds fields for a form in display order', function (): void {
    $form = new Form();
    $fields = [new FormField(), new FormField()];
    $repository = Mockery::mock(FormFieldRepository::class)->makePartial();
    $repository->shouldReceive('findBy')
        ->once()
        ->with(['form' => $form], ['id' => 'ASC'])
        ->andReturn($fields);

    expect($repository->findByForm($form))->toBe($fields);
});

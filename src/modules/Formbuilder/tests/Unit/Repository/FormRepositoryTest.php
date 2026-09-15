<?php

declare(strict_types=1);

use Box\Mod\Formbuilder\Entity\Form;
use Box\Mod\Formbuilder\Repository\FormRepository;
use FOSSBilling\InformationException;

test('finds an existing form by ID', function (): void {
    $form = new Form();
    $repository = Mockery::mock(FormRepository::class)->makePartial();
    $repository->shouldReceive('find')->once()->with(42)->andReturn($form);

    expect($repository->findOneByIdOrFail(42))->toBe($form);
});

test('fails when a form does not exist', function (): void {
    $repository = Mockery::mock(FormRepository::class)->makePartial();
    $repository->shouldReceive('find')->once()->with(42)->andReturn(null);

    expect(fn (): Form => $repository->findOneByIdOrFail(42))
        ->toThrow(InformationException::class, 'Form was not found');
});

<?php

declare(strict_types=1);

use Box\Mod\System\Entity\Setting;
use Box\Mod\System\Repository\SettingRepository;

test('finds a setting by parameter', function (): void {
    $setting = new Setting();
    $repository = Mockery::mock(SettingRepository::class)->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'company_name'])
        ->andReturn($setting);

    expect($repository->findOneByParam('company_name'))->toBe($setting);
});

test('returns null when a setting parameter does not exist', function (): void {
    $repository = Mockery::mock(SettingRepository::class)->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'missing'])
        ->andReturn(null);

    expect($repository->findOneByParam('missing'))->toBeNull();
});

test('finds a public setting by parameter', function (): void {
    $setting = new Setting();
    $repository = Mockery::mock(SettingRepository::class)->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'company_name', 'public' => true])
        ->andReturn($setting);

    expect($repository->findOnePublicByParam('company_name'))->toBe($setting);
});

test('returns null when a setting is missing or not public', function (): void {
    $repository = Mockery::mock(SettingRepository::class)->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'hidden_param', 'public' => true])
        ->andReturn(null);

    expect($repository->findOnePublicByParam('hidden_param'))->toBeNull();
});

test('finds settings by a list of parameters', function (): void {
    $settings = [new Setting(), new Setting()];
    $repository = Mockery::mock(SettingRepository::class)->makePartial();
    $repository->shouldReceive('findBy')
        ->once()
        ->with(['param' => ['company_name', 'company_email']])
        ->andReturn($settings);

    expect($repository->findByParams(['company_name', 'company_email']))->toBe($settings);
});

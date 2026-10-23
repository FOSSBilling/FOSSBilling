<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\News\Api\Guest;
use Box\Mod\News\Entity\Post;
use Box\Mod\News\Repository\PostRepository;
use Box\Mod\News\Service;
use FOSSBilling\InformationException;

test('unavailable news returns not found', function (array $data, ?Post $post): void {
    $repository = Mockery::mock(PostRepository::class);
    if (isset($data['id'])) {
        $repository->shouldReceive('findOneActiveById')->with($data['id'])->once()->andReturn($post);
    } else {
        $repository->shouldReceive('findOneActiveBySlug')->with($data['slug'])->once()->andReturn($post);
    }
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('getPostRepository')->once()->andReturn($repository);
    $api = apiEndpoint(new Guest());
    $api->setService($service);

    expect(fn () => $api->get($data))->toThrow(new InformationException('News item not found.', [], 404));
})->with([
    'missing slug' => [['slug' => 'missing'], null],
    'missing id' => [['id' => 999], null],
    'draft' => [['slug' => 'draft'], (new Post('Draft', 'draft'))->setStatus(Post::STATUS_DRAFT)],
]);

<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Service;
use FOSSBilling\InformationException;

use function Tests\Helpers\container;

test('oversized icon URLs are rejected before product writes', function (string $operation, string $iconUrl): void {
    $service = new Service();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldNotReceive('persist');
    $em->shouldNotReceive('flush');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    $product = (new Product())->setTitle('Original')->setIconUrl('original.svg');
    $category = (new ProductCategory())->setTitle('Original')->setIconUrl('original.svg');

    expect(fn (): int|bool|null => match ($operation) {
        'addon' => $service->createAddon('Addon', iconUrl: $iconUrl),
        'product' => $service->updateProduct($product, ['title' => 'Changed', 'icon_url' => $iconUrl]),
        'category create' => $service->createCategory('Category', icon_url: $iconUrl),
        'category update' => $service->updateCategory($category, 'Changed', icon_url: $iconUrl),
    })->toThrow(InformationException::class, 'Icon URL must not exceed 255 characters.');

    expect($product->getTitle())->toBe('Original')
        ->and($product->getIconUrl())->toBe('original.svg')
        ->and($category->getTitle())->toBe('Original')
        ->and($category->getIconUrl())->toBe('original.svg');
})->with(['addon', 'product', 'category create', 'category update'])
    ->with(['ASCII' => str_repeat('a', 256), 'Unicode' => str_repeat('é', 256)]);

test('category icon URLs within the column limit are preserved', function (?string $iconUrl): void {
    $em = new class {
        public ?ProductCategory $category = null;

        public function persist(ProductCategory $category): void
        {
            $this->category = $category;
        }

        public function flush(): void
        {
        }
    };
    $logger = Mockery::mock(FOSSBilling\Logger::class);
    $logger->shouldReceive('info')->once();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = $logger;
    $service = new Service();
    $service->setDi($di);

    $service->createCategory('Category', icon_url: $iconUrl);

    expect($em->category?->getIconUrl())->toBe($iconUrl);
})->with([null, '', str_repeat('a', 255), str_repeat('é', 255)]);

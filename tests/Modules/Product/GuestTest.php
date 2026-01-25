<?php

declare(strict_types=1);

describe('Product Catalog', function () {
    it('returns the product list', function () {
        expect(api('guest/product/get_list'))
            ->toHaveResult()
            ->toBeArray();
    });

    it('returns product pairs for dropdowns', function () {
        expect(api('guest/product/get_pairs'))
            ->toHaveResult()
            ->toBeArray();
    });

    it('requires a product ID or slug to fetch details', function () {
        expect(api('guest/product/get'))
            ->toHaveErrorMessage('Product ID or slug is missing');
    });
});

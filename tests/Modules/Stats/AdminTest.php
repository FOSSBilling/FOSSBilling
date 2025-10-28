<?php

declare(strict_types=1);

describe('Statistics Dashboard', function () {
    it('provides general system summary', function () {
        expect(api('admin/stats/get_summary'))
            ->toHaveResult()
            ->toBeArray();
    });

    it('provides income summary statistics', function () {
        expect(api('admin/stats/get_summary_income'))
            ->toHaveResult()
            ->toBeArray();
    });

    it('provides order status statistics', function () {
        expect(api('admin/stats/get_orders_statuses'))
            ->toHaveResult()
            ->toBeArray();
    });

    it('provides product summary statistics', function () {
        expect(api('admin/stats/get_product_summary'))
            ->toHaveResult()
            ->toBeArray();
    });

    it('provides product sales statistics', function () {
        expect(api('admin/stats/get_product_sales'))
            ->toHaveResult()
            ->toBeArray();
    });
});

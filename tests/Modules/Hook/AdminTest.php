<?php

declare(strict_types=1);

describe('Hook Management', function () {
    it('can batch connect all hooks', function () {
        expect(api('admin/hook/batch_connect'))
            ->toHaveResult()
            ->toBeTrue();
    });

    it('returns list of registered hooks', function () {
        $hooks = api('admin/hook/get_list')->getResult();

        expect($hooks)
            ->toBeArray()
            ->not->toBeEmpty('Should have at least one registered hook');
    });

    it('can trigger hook events', function () {
        expect(api('admin/hook/call', ['event' => 'onBeforeAdminCronRun']))
            ->toBeSuccessfulResponse();
    });
});

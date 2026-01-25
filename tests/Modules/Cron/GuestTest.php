<?php

declare(strict_types=1);

describe('Guest Cron Permissions', function () {
    it('prevents guest cron execution when disabled', function () {
        // Disable guest cron access
        api('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => false]);

        // Attempt to run cron as guest (should fail)
        expect(api('guest/cron/run'))
            ->toBeFailedResponse();
    });

    it('allows guest cron execution when enabled', function () {
        // Enable guest cron access
        api('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => true]);

        // Set last execution time far in the past to avoid rate limiting
        api('admin/system/update_params', [
            'last_cron_exec' => date('Y-m-d H:i:s', time() - 6400),
        ]);

        // Run cron as guest (should succeed)
        expect(api('guest/cron/run'))
            ->toBeSuccessfulResponse();
    });
});

describe('Cron Rate Limiting', function () {
    it('enforces rate limit on consecutive executions', function () {
        // Enable guest cron
        api('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => true]);

        // Set last execution time far in the past
        api('admin/system/update_params', [
            'last_cron_exec' => date('Y-m-d H:i:s', time() - 6400),
        ]);

        // First execution should succeed
        $firstRun = api('guest/cron/run');
        expect($firstRun)->toBeSuccessfulResponse();

        // Immediate second execution should be rate-limited
        $secondRun = api('guest/cron/run');

        expect($secondRun)
            ->toBeSuccessfulResponse();

        expect($secondRun->getResult())
            ->toBeFalse('Second execution should return false due to rate limiting');
    });
});

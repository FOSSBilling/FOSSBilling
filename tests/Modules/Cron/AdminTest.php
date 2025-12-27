<?php

declare(strict_types=1);

describe('Cron Execution', function () {
    it('can execute cron jobs', function () {
        expect(api('admin/cron/run'))
            ->toBeSuccessfulResponse();
    });

    it('provides cron information', function () {
        $cronInfo = api('admin/cron/info')->getResult();

        expect($cronInfo)
            ->toBeArray()
            ->toHaveKeys(['cron_path', 'last_cron_exec']);
    });

    it('tracks last execution timestamp', function () {
        // Set cron to execute by moving last execution far into the past
        $pastTime = date('Y-m-d H:i:s', time() - 7200); // 2 hours ago
        api('admin/system/update_params', [
            'last_cron_exec' => $pastTime,
        ]);

        // Verify the timestamp was actually updated to the past time
        $preRunInfo = api('admin/cron/info')->getResult();
        expect($preRunInfo['last_cron_exec'])
            ->toBe($pastTime, 'Timestamp should be set to 2 hours ago before running cron');

        // Execute cron
        expect(api('admin/cron/run'))
            ->toBeSuccessfulResponse();

        // Allow time for database update
        sleep(3);

        // Verify the execution timestamp was updated
        $postRunInfo = api('admin/cron/info')->getResult();

        expect($postRunInfo['last_cron_exec'])
            ->not->toBeEmpty('Cron execution timestamp should be set')
            ->not->toBe($pastTime, 'Timestamp should have been updated after cron execution');
    });
});

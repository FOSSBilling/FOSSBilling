<?php

declare(strict_types=1);

describe('Cache Management', function () {
    it('can clear system cache', function () {
        expect(api('admin/system/clear_cache'))
            ->toHaveResult()
            ->toBeBool();
    });
});

describe('Error Reporting', function () {
    it('can toggle error reporting setting', function () {
        $initialState = api('admin/system/error_reporting_enabled')->getResult();

        expect($initialState)->toBeBool();

        // Toggle it
        expect(api('admin/system/toggle_error_reporting'))
            ->toBeSuccessfulResponse();

        $newState = api('admin/system/error_reporting_enabled')->getResult();

        expect($newState)
            ->toBeBool()
            ->not->toBe($initialState);

        // Restore original state if needed
        if ($newState) {
            api('admin/system/toggle_error_reporting');
        }
    });
});

describe('Network Interfaces', function () {
    it('returns list of valid network interfaces', function () {
        $interfaces = api('admin/system/get_interface_ips')->getResult();

        expect($interfaces)->toBeArray();

        foreach ($interfaces as $ip) {
            expect(filter_var($ip, FILTER_VALIDATE_IP))->not->toBeFalse();
        }
    });

    it('can set and verify interface configuration', function () {
        $interfaces = api('admin/system/get_interface_ips')->getResult();

        if (!isIpLookupWorking()) {
            test()->markTestSkipped('IP lookup services are not available');
        }

        foreach ($interfaces as $ip) {
            expect(api('admin/system/set_interface_ip', ['interface' => $ip]))
                ->toBeSuccessfulResponse();

            sleep(2);

            $publicIp = api('admin/system/env', ['ip' => true])->getResult();
            expect(filter_var($publicIp, FILTER_VALIDATE_IP))->not->toBeFalse();
        }

        // Restore default
        api('admin/system/set_interface_ip', ['interface' => '0']);
    });

    it('ignores invalid interface and continues working', function () {
        expect(api('admin/system/set_interface_ip', ['interface' => '12345']))
            ->toHaveResult()
            ->toBeTrue();

        if (isIpLookupWorking()) {
            sleep(2);
            $publicIp = api('admin/system/env', ['ip' => true])->getResult();
            expect(filter_var($publicIp, FILTER_VALIDATE_IP))->not->toBeFalse();
        }
    });

    it('accepts custom interface configuration', function () {
        expect(api('admin/system/set_interface_ip', ['custom_interface' => '12345']))
            ->toHaveResult()
            ->toBeTrue();

        if (isIpLookupWorking()) {
            sleep(2);
            expect(api('admin/system/env', ['ip' => true]))
                ->toHaveResult()
                ->toBeEmpty();
        }

        // Restore default
        expect(api('admin/system/set_interface_ip', ['custom_interface' => '', 'interface' => '0']))
            ->toHaveResult()
            ->toBeTrue();
    });
});

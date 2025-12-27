<?php

declare(strict_types=1);

describe('Theme Information', function () {
    it('returns theme metadata', function () {
        $theme = api('guest/extension/theme')->getResult();

        expect($theme)
            ->toBeArray()
            ->toHaveKeys(['name', 'version', 'author'])
            ->and($theme['author'])->toBe('FOSSBilling');
    });
});

describe('Extension Settings', function () {
    it('returns settings for a valid extension', function () {
        expect(api('guest/extension/settings', ['ext' => 'index']))
            ->toHaveResult()
            ->toBeArray();
    });

    it('rejects request without an extension parameter', function () {
        expect(api('guest/extension/settings', ['ext']))
            ->toHaveErrorMessage('Parameter ext is missing');
    });
});

describe('Extension Status', function () {
    it('detects active core extensions', function () {
        expect(api('guest/extension/is_on', ['mod' => 'index']))
            ->toHaveResult()
            ->toBeTrue();
    });

    it('detects inactive extensions', function () {
        expect(api('guest/extension/is_on', ['mod' => 'serviceapikey']))
            ->toHaveResult()
            ->toBeFalse();
    });
});

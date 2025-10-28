<?php

declare(strict_types=1);

describe('Extension Management', function () {
    it('can activate an extension', function () {
        expect(api('admin/extension/activate', ['type' => 'mod', 'id' => 'massmailer']))
            ->toBeSuccessfulResponse();
    });

    it('can deactivate an extension', function () {
        expect(api('admin/extension/deactivate', ['type' => 'mod', 'id' => 'massmailer']))
            ->toBeSuccessfulResponse();
    });

    it('can install an extension', function () {
        //
    })->todo();
});

describe('Language Management', function () {
    it('lists enabled languages on fresh install', function () {
        $languages = api('admin/extension/languages')->getResult();

        expect($languages)
            ->not->toBeEmpty('Fresh install should have at least one enabled language');
    });

    it('has no disabled languages on fresh install', function () {
        $disabledLanguages = api('admin/extension/languages', ['disabled' => true])->getResult();

        expect($disabledLanguages)
            ->toBeEmpty('Fresh install should have no disabled languages');
    });

    it('can toggle language status', function () {
        // Get initial state
        $enabledLanguages = api('admin/extension/languages', ['details' => false])->getResult();

        // Skip test if en_US is not available
        if (!in_array('en_US', $enabledLanguages)) {
            test()->markTestSkipped('en_US language is not available');
        }

        // Disable en_US
        expect(api('admin/extension/toggle_language', ['locale_id' => 'en_US']))
            ->toBeSuccessfulResponse();

        // Verify it's now disabled
        $disabledLanguages = api('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult();
        expect($disabledLanguages)->toContain('en_US');

        // Verify it's removed from enabled list
        $enabledLanguages = api('admin/extension/languages', ['details' => false])->getResult();
        expect($enabledLanguages)->not->toContain('en_US');

        // Re-enable it
        expect(api('admin/extension/toggle_language', ['locale_id' => 'en_US']))
            ->toBeSuccessfulResponse();
    });

    it('reports completion level for all languages', function () {
        // Enable all languages first
        $disabledLanguages = api('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult();

        foreach ($disabledLanguages as $locale) {
            api('admin/extension/toggle_language', ['locale_id' => $locale]);
        }

        // Verify all are now enabled
        expect(api('admin/extension/languages', ['disabled' => true, 'details' => false]))
            ->toHaveResult()
            ->toBeEmpty();

        // Check completion levels
        $allLanguages = api('admin/extension/languages', ['details' => false])->getResult();

        foreach ($allLanguages as $locale) {
            $completion = api('admin/extension/locale_completion', ['locale_id' => $locale])->getResult();

            if ($locale === 'en_US') {
                expect($completion)
                    ->toBe(100, "en_US should be 100% complete");
            } else {
                expect($completion)
                    ->toBeGreaterThanOrEqual(25, "Language {$locale} should be at least 25% complete");
            }
        }
    });
});

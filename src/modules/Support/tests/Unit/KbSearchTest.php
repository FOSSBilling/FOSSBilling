<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\KbSearch;

test('knowledge base search normalizes terms', function (): void {
    expect(KbSearch::terms("  First\tSECOND\nthird  "))
        ->toBe(['first', 'second', 'third']);
});

test('knowledge base search limits query length and term count', function (): void {
    $terms = KbSearch::terms(str_repeat('word ', KbSearch::MAX_TERMS + 1000));

    expect($terms)
        ->toHaveCount(KbSearch::MAX_TERMS)
        ->each->toBe('word');

    expect(KbSearch::terms(str_repeat('a', KbSearch::MAX_QUERY_LENGTH + 1000)))
        ->toBe([str_repeat('a', KbSearch::MAX_QUERY_LENGTH)]);
});

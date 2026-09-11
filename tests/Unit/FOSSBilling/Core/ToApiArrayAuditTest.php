<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

declare(strict_types=1);

use Tests\Support\ToApiArrayAuditor;

/*
 * Keep the intentionally conditional fields explicit. If a new conditional
 * field is added to a `toApiArray()` method, this contract test fails and
 * makes the API-shape decision visible in the diff.
 */
test('toApiArray conditional fields remain intentional', function (): void {
    $srcDir = dirname(__DIR__, 4) . '/src';
    $auditor = new ToApiArrayAuditor($srcDir);

    expect($auditor->audit())->toBe([
        'modules/Order/Service.php' => [
            'plugin' => ['if'],
            'product_suspension_grace_days' => ['if'],
        ],
        'modules/Servicedomain/Service.php' => [
            'transfer_code' => ['if'],
            'registrar' => ['if'],
        ],
        'modules/Support/Entity/KbArticle.php' => [
            'content' => ['if'],
            'kb_article_category_id' => ['if'],
        ],
        'modules/Support/Entity/SupportTicket.php' => [
            'hash' => ['if'],
        ],
        'modules/Support/Service.php' => [
            'messages' => ['loop'],
            'rel' => ['if'],
            'priority' => ['if'],
            'notes' => ['if', 'loop'],
        ],
    ]);
});

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

test('toApiArray audit walks try, catch and finally bodies', function (): void {
    $srcDir = sys_get_temp_dir() . '/api-array-audit-' . bin2hex(random_bytes(4));
    mkdir($srcDir . '/modules/Example', 0o755, true);
    file_put_contents($srcDir . '/modules/Example/Service.php', <<<'PHP'
        <?php
        class Service
        {
            public function toApiArray($model, $deep = false, $identity = null): array
            {
                $data = [];
                try {
                    $data['always'] = 1;
                    if ($deep) {
                        $data['in_try'] = 2;
                    }
                } catch (\Exception) {
                    if ($deep) {
                        $data['in_catch'] = 3;
                    }
                } finally {
                    if ($deep) {
                        $data['in_finally'] = 4;
                    }
                }

                return $data;
            }
        }
        PHP);

    try {
        expect((new ToApiArrayAuditor($srcDir))->audit())->toBe([
            'modules/Example/Service.php' => [
                'in_try' => ['if'],
                'in_catch' => ['if'],
                'in_finally' => ['if'],
            ],
        ]);
    } finally {
        unlink($srcDir . '/modules/Example/Service.php');
        rmdir($srcDir . '/modules/Example');
        rmdir($srcDir . '/modules');
        rmdir($srcDir);
    }
});

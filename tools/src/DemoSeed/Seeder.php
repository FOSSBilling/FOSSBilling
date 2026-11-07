<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\DemoSeed;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Additive demo/test-data seeder.
 *
 * Foundation/catalog records use a "Demo" prefix and are skipped when they
 * already exist (matched by email/title/code); orders, invoices and tickets
 * are appended on every run, so re-running grows those lists rather than
 * converging.
 *
 * Clients are created with send_welcome_email=0 and only offline-safe
 * gateways (Custom, ClientBalance) are enabled; other seed actions can
 * trigger the target's normal notifications, so seed instances with mail
 * delivery disabled or captured by a local sink.
 */
final class Seeder
{
    private array $manifest = [];

    public function __construct(
        private readonly ApiClient $api,
        private readonly string $baseUrl,
        private readonly bool $dryRun,
        private readonly int $limitClients,
        private readonly int $limitOrders,
        private readonly int $limitTickets,
        private readonly string $manifestPath,
        private readonly OutputInterface $output,
        private readonly ?string $demoFilePath = null,
    ) {
    }

    /**
     * Run the full seed and return the number of recorded per-record errors.
     *
     * @throws \RuntimeException when preflight against the target fails
     */
    public function run(): int
    {
        $this->manifest = [
            'base_url' => $this->baseUrl,
            'seeded_at' => date('c'),
            'dry_run' => $this->dryRun,
            'client_password' => 'DemoPass123!',
            'staff_password' => 'DemoPass123!',
            'currencies' => [],
            'taxes' => [],
            'client_groups' => [],
            'staff' => [],
            'gateways' => [],
            'product_categories' => [],
            'products' => [],
            'addons' => [],
            'promos' => [],
            'tlds' => [],
            'hosting_server' => null,
            'hosting_plans' => [],
            'clients' => [],
            'orders' => [],
            'invoices_manual' => [],
            'transactions' => [],
            'subscriptions' => [],
            'helpdesks' => [],
            'canned_categories' => [],
            'canned' => [],
            'kb_categories' => [],
            'kb_articles' => [],
            'tickets' => [],
            'news' => [],
            'errors' => [],
        ];

        // ---------------------------------------------------------------- preflight
        $this->note('== FOSSBilling demo seeder ==');
        $this->note("Target: {$this->baseUrl}" . ($this->dryRun ? ' [DRY RUN]' : ''));

        try {
            $behind = $this->api->call('admin/system/is_behind_on_patches');
            $this->note('Preflight OK (is_behind_on_patches=' . var_export($behind, true) . ')');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Preflight failed: ' . $e->getMessage(), 0, $e);
        }

        // ------------------------------------------------------- 1. currencies/taxes
        $this->note('-- foundation: currencies, taxes, groups, staff, gateways');

        $existingCurrencies = $this->fetchAll('admin/currency/get_list');
        foreach ([
            ['code' => 'USD', 'conversion_rate' => 1.27, 'is_rate_manual' => 1],
            ['code' => 'EUR', 'conversion_rate' => 1.17, 'is_rate_manual' => 1],
            ['code' => 'CAD', 'conversion_rate' => 1.74, 'is_rate_manual' => 1],
        ] as $c) {
            if (self::findBy($existingCurrencies, 'code', $c['code'])) {
                $this->note("  currency {$c['code']} exists, skip");
                $this->manifest['currencies'][] = $c['code'] . ' (existing)';

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create currency {$c['code']}");

                continue;
            }
            $r = $this->api->callSafe('admin/currency/create', $c);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('currency/create ' . $c['code'], $r['__error']);
            } else {
                $this->note("  created currency {$c['code']}");
                $this->manifest['currencies'][] = $c['code'];
            }
        }

        $existingTaxes = $this->fetchAll('admin/invoice/tax_get_list');
        $taxDefs = [
            ['name' => 'Demo VAT 20%', 'taxrate' => 20],
            ['name' => 'Demo US-CA Sales Tax 8.5%', 'taxrate' => 8.5, 'country' => 'US', 'state' => 'CA'],
            ['name' => 'Demo Reduced 5%', 'taxrate' => 5, 'country' => 'GB'],
        ];
        foreach ($taxDefs as $t) {
            if (self::findBy($existingTaxes, 'name', $t['name'])) {
                $this->note("  tax '{$t['name']}' exists, skip");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create tax {$t['name']}");

                continue;
            }
            $r = $this->api->callSafe('admin/invoice/tax_create', $t);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('tax/create ' . $t['name'], $r['__error']);
            } else {
                $this->note("  created tax {$t['name']} id={$r}");
                $this->manifest['taxes'][] = ['name' => $t['name'], 'id' => $r];
            }
        }

        $existingGroups = $this->api->callSafe('admin/client/group_get_pairs', []);
        if (!is_array($existingGroups) || isset($existingGroups['__error'])) {
            $existingGroups = [];
        }
        $groupTitles = ['Demo - Resellers', 'Demo - Late Payers', 'Demo - EU Customers'];
        $groupIds = [];
        foreach ($groupTitles as $title) {
            $foundId = array_search($title, $existingGroups, true);
            if ($foundId !== false) {
                $this->note("  client group '{$title}' exists id={$foundId}");
                $groupIds[$title] = (int) $foundId;
                $this->manifest['client_groups'][] = ['title' => $title, 'id' => (int) $foundId, 'existing' => true];

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create client group {$title}");

                continue;
            }
            $r = $this->api->callSafe('admin/client/group_create', ['title' => $title]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('client/group_create ' . $title, $r['__error']);
            } else {
                $this->note("  created client group {$title} id={$r}");
                $groupIds[$title] = (int) $r;
                $this->manifest['client_groups'][] = ['title' => $title, 'id' => (int) $r];
            }
        }

        // staff (support + billing)
        $existingStaff = $this->fetchAll('admin/staff/get_list');
        $staffDefs = [
            ['email' => 'demo.support@example.com', 'name' => 'Demo Support', 'group_id' => 3],
            ['email' => 'demo.billing@example.com', 'name' => 'Demo Billing', 'group_id' => 3],
        ];
        foreach ($staffDefs as $s) {
            if (self::findBy($existingStaff, 'email', $s['email'])) {
                $this->note("  staff {$s['email']} exists, skip");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create staff {$s['email']}");

                continue;
            }
            $r = $this->api->callSafe('admin/staff/create', $s + ['password' => 'DemoPass123!', 'status' => 'active']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('staff/create ' . $s['email'], $r['__error']);
            } else {
                $this->note("  created staff {$s['email']} id={$r}");
                $this->manifest['staff'][] = ['email' => $s['email'], 'id' => $r];
            }
        }

        // gateways: install ClientBalance (offline-safe), enable Custom + ClientBalance
        $available = $this->api->callSafe('admin/invoice/gateway_get_available', []);
        if (is_array($available) && in_array('ClientBalance', $available, true)) {
            if ($this->dryRun) {
                $this->note('  [dry] install gateway ClientBalance');
            } else {
                $r = $this->api->callSafe('admin/invoice/gateway_install', ['code' => 'ClientBalance']);
                if (is_array($r) && isset($r['__error']) && !str_contains($r['__error'], 'already')) {
                    $this->recordError('gateway/install ClientBalance', $r['__error']);
                } else {
                    $this->note('  ensured gateway ClientBalance installed');
                }
            }
        }
        $gateways = $this->fetchAll('admin/invoice/gateway_get_list');
        $gatewayIds = [];
        foreach ($gateways as $g) {
            $gatewayIds[$g['code'] ?? $g['title']] = $g['id'];
            if (in_array($g['code'] ?? '', ['Custom', 'ClientBalance'], true)) {
                if ($this->dryRun) {
                    $this->note("  [dry] enable gateway {$g['code']} id={$g['id']}");

                    continue;
                }
                $u = $this->api->callSafe('admin/invoice/gateway_update', [
                    'id' => $g['id'],
                    'enabled' => 1,
                    'test_mode' => 1,
                    // Add the demo currencies without dropping currencies the
                    // gateway was already configured to accept.
                    'accepted_currencies' => array_values(array_unique(array_merge($g['accepted_currencies'] ?? [], ['GBP', 'USD', 'EUR']))),
                ]);
                if (is_array($u) && isset($u['__error'])) {
                    $this->recordError('gateway/update ' . $g['code'], $u['__error']);
                } else {
                    $this->note("  enabled gateway {$g['code']} id={$g['id']}");
                }
            }
        }
        $this->manifest['gateways'] = $gatewayIds;
        $customGatewayId = $gatewayIds['Custom'] ?? reset($gatewayIds) ?: 1;

        // ------------------------------------------------------- 2. catalog
        $this->note('-- catalog: categories, TLDs, server/plans, products, addons, promos');

        $existingCats = $this->api->callSafe('admin/product/category_get_pairs', []);
        if (!is_array($existingCats) || isset($existingCats['__error'])) {
            $existingCats = [];
        }
        $catIds = [];
        foreach (['Demo Hosting', 'Demo Software', 'Demo Services', 'Demo Domains'] as $title) {
            $foundId = array_search($title, $existingCats, true);
            if ($foundId !== false) {
                $catIds[$title] = (int) $foundId;
                $this->note("  category '{$title}' exists id={$foundId}");
                $this->manifest['product_categories'][] = ['title' => $title, 'id' => (int) $foundId, 'existing' => true];

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create category {$title}");

                continue;
            }
            $r = $this->api->callSafe('admin/product/category_create', ['title' => $title, 'description' => "Demo category {$title}"]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('product/category_create ' . $title, $r['__error']);
            } else {
                $catIds[$title] = (int) $r;
                $this->note("  created category {$title} id={$r}");
                $this->manifest['product_categories'][] = ['title' => $title, 'id' => (int) $r];
                $existingCats[(int) $r] = $title;
            }
        }

        // TLDs (.com already exists on registrar 1)
        $existingTlds = $this->fetchAll('admin/servicedomain/tld_get_list');
        foreach ([
            ['tld' => '.io', 'price_registration' => 39.99, 'price_renew' => 39.99, 'price_transfer' => 39.99],
            ['tld' => '.dev', 'price_registration' => 14.99, 'price_renew' => 14.99, 'price_transfer' => 14.99],
            ['tld' => '.org', 'price_registration' => 12.49, 'price_renew' => 12.49, 'price_transfer' => 12.49],
        ] as $t) {
            if (self::findBy($existingTlds, 'tld', $t['tld'])) {
                $this->note("  TLD {$t['tld']} exists, skip");
                $this->manifest['tlds'][] = $t['tld'] . ' (existing)';

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create TLD {$t['tld']}");

                continue;
            }
            $r = $this->api->callSafe('admin/servicedomain/tld_create', $t + ['tld_registrar_id' => 1]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('tld/create ' . $t['tld'], $r['__error']);
            } else {
                $this->note("  created TLD {$t['tld']}");
                $this->manifest['tlds'][] = $t['tld'];
            }
        }

        // hosting server + plans (Custom manager needs no credentials)
        $servers = $this->fetchAll('admin/servicehosting/server_get_list');
        $serverId = null;
        foreach ($servers as $s) {
            if (($s['name'] ?? '') === 'Demo Server') {
                $serverId = (int) $s['id'];
            }
        }
        if ($serverId === null && !$this->dryRun) {
            $r = $this->api->callSafe('admin/servicehosting/server_create', [
                'name' => 'Demo Server', 'hostname' => 'demo.example.com',
                'ip' => '192.0.2.10', 'manager' => 'Custom', 'active' => 1,
            ]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('servicehosting/server_create', $r['__error']);
            } else {
                $serverId = (int) $r;
                $this->note("  created hosting server id={$serverId}");
            }
        } elseif ($serverId !== null) {
            $this->note("  hosting server exists id={$serverId}");
        }
        $this->manifest['hosting_server'] = $serverId;

        $plans = $this->fetchAll('admin/servicehosting/hp_get_list');
        $planIds = [];
        foreach (['Demo Shared Plan', 'Demo VPS Plan'] as $pname) {
            $found = self::findBy($plans, 'name', $pname);
            if ($found) {
                $planIds[$pname] = (int) $found['id'];
                $this->note("  hosting plan '{$pname}' exists");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create hosting plan {$pname}");

                continue;
            }
            $r = $this->api->callSafe('admin/servicehosting/hp_create', ['name' => $pname]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('hp/create ' . $pname, $r['__error']);
            } else {
                $planIds[$pname] = (int) $r;
                $this->note("  created hosting plan {$pname} id={$r}");
            }
        }
        $this->manifest['hosting_plans'] = $planIds;

        // products
        $existingProducts = $this->fetchAll('admin/product/get_list');
        $productIds = [];
        foreach ($existingProducts as $p) {
            $productIds[$p['title']] = (int) $p['id'];
        }
        $recurrent = static function (array $periods): array {
            $out = ['type' => 'recurrent', 'recurrent' => []];
            foreach ($periods as $code => [$price, $setup]) {
                $out['recurrent'][$code] = ['price' => $price, 'setup' => $setup, 'enabled' => 1];
            }

            return $out;
        };
        $once = static fn ($price, $setup = 0): array => ['type' => 'once', 'once' => ['price' => $price, 'setup' => $setup]];

        $productDefs = [
            ['title' => 'Demo Shared Hosting', 'type' => 'hosting', 'cat' => 'Demo Hosting', 'pricing' => $recurrent(['1M' => [4.99, 0], '1Y' => [49.99, 0]]), 'setup' => 'after_payment', 'config' => ['server_id' => 'SERVER', 'hosting_plan_id' => 'SHARED']],
            ['title' => 'Demo VPS Hosting', 'type' => 'hosting', 'cat' => 'Demo Hosting', 'pricing' => $recurrent(['1M' => [19.99, 5], '1Y' => [199.99, 0]]), 'setup' => 'after_payment', 'config' => ['server_id' => 'SERVER', 'hosting_plan_id' => 'VPS']],
            ['title' => 'Demo Ebook Download', 'type' => 'downloadable', 'cat' => 'Demo Software', 'pricing' => $once(9.99), 'setup' => 'after_payment'],
            ['title' => 'Demo Pro License', 'type' => 'license', 'cat' => 'Demo Software', 'pricing' => $recurrent(['1M' => [5.00, 0], '1Y' => [49.00, 0]]), 'setup' => 'after_payment'],
            ['title' => 'Demo One-time Setup', 'type' => 'custom', 'cat' => 'Demo Services', 'pricing' => $once(29.00), 'setup' => 'after_order'],
            ['title' => 'Demo Consultancy', 'type' => 'custom', 'cat' => 'Demo Services', 'pricing' => $recurrent(['1M' => [99.00, 0]]), 'setup' => 'after_payment'],
            ['title' => 'Demo API Credits', 'type' => 'apikey', 'cat' => 'Demo Software', 'pricing' => $once(15.00), 'setup' => 'after_payment'],
        ];
        foreach ($productDefs as $def) {
            if (isset($productIds[$def['title']])) {
                $this->note("  product '{$def['title']}' exists id={$productIds[$def['title']]}");
                $this->manifest['products'][] = ['title' => $def['title'], 'type' => $def['type'], 'id' => $productIds[$def['title']], 'existing' => true];

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create product {$def['title']}");

                continue;
            }
            $pid = $this->api->callSafe('admin/product/prepare', ['title' => $def['title'], 'type' => $def['type'], 'product_category_id' => $catIds[$def['cat']] ?? null]);
            if (is_array($pid) && isset($pid['__error'])) {
                $this->recordError('product/prepare ' . $def['title'], $pid['__error']);

                continue;
            }
            $pid = (int) $pid;
            $upd = ['id' => $pid, 'pricing' => $def['pricing'], 'status' => 'enabled', 'setup' => $def['setup'], 'description' => "Demo product {$def['title']} for testing."];
            if (isset($def['config'])) {
                $cfg = $def['config'];
                if (($cfg['server_id'] ?? null) === 'SERVER') {
                    $cfg['server_id'] = $serverId;
                }
                if (($cfg['hosting_plan_id'] ?? null) === 'SHARED') {
                    $cfg['hosting_plan_id'] = $planIds['Demo Shared Plan'] ?? null;
                }
                if (($cfg['hosting_plan_id'] ?? null) === 'VPS') {
                    $cfg['hosting_plan_id'] = $planIds['Demo VPS Plan'] ?? null;
                }
                $upd['config'] = $cfg;
            }
            $u = $this->api->callSafe('admin/product/update', $upd);
            if (is_array($u) && isset($u['__error'])) {
                $this->recordError('product/update ' . $def['title'], $u['__error']);

                continue;
            }
            $this->note("  created product {$def['title']} id={$pid}");
            $productIds[$def['title']] = $pid;
            $this->manifest['products'][] = ['title' => $def['title'], 'type' => $def['type'], 'id' => $pid];
            if ($def['title'] === 'Demo Ebook Download' && $this->demoFilePath !== null && is_file($this->demoFilePath)) {
                // The storefront product needs a downloadable file, or adding
                // it to the cart fails with "Product is not configured completely."
                try {
                    $this->api->upload('admin/servicedownloadable/upload', ['id' => (string) $pid], 'file_data', $this->demoFilePath);
                    $this->note("  attached demo file to {$def['title']}");
                } catch (Throwable $e) {
                    $this->recordError('servicedownloadable/upload ' . $def['title'], $e->getMessage());
                }
            }
        }
        // domain product already exists (only one allowed) — reuse it
        if (!isset($productIds['Domains registration and transfer'])) {
            foreach ($existingProducts as $p) {
                if (($p['type'] ?? '') === 'domain') {
                    $productIds[$p['title']] = (int) $p['id'];
                    $this->manifest['products'][] = ['title' => $p['title'], 'type' => 'domain', 'id' => (int) $p['id'], 'existing' => true];
                }
            }
        }

        // addons
        $existingAddons = $this->api->callSafe('admin/product/addon_get_pairs', []);
        if (!is_array($existingAddons) || isset($existingAddons['__error'])) {
            $existingAddons = [];
        }
        foreach ([
            ['title' => 'Demo Extra Storage', 'pricing' => $once(12.00)],
            ['title' => 'Demo Priority Support', 'pricing' => $recurrent(['1M' => [6.99, 0]])],
            ['title' => 'Demo SSL Install', 'pricing' => $once(19.00)],
        ] as $a) {
            $foundId = array_search($a['title'], $existingAddons, true);
            if ($foundId !== false) {
                $this->note("  addon '{$a['title']}' exists");
                $this->manifest['addons'][] = ['title' => $a['title'], 'id' => (int) $foundId, 'existing' => true];

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create addon {$a['title']}");

                continue;
            }
            $r = $this->api->callSafe('admin/product/addon_create', ['title' => $a['title'], 'status' => 'enabled', 'description' => 'Demo addon for testing']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('addon/create ' . $a['title'], $r['__error']);

                continue;
            }
            $u = $this->api->callSafe('admin/product/addon_update', ['id' => (int) $r, 'pricing' => $a['pricing'], 'status' => 'enabled']);
            if (is_array($u) && isset($u['__error'])) {
                $this->recordError('addon/update ' . $a['title'], $u['__error']);
            }
            $this->note("  created addon {$a['title']} id={$r}");
            $this->manifest['addons'][] = ['title' => $a['title'], 'id' => (int) $r];
        }

        // promos
        $existingPromos = $this->fetchAll('admin/product/promo_get_list');
        foreach ([
            ['code' => 'DEMO10', 'type' => 'percentage', 'value' => 10, 'recurring' => 1, 'active' => 1, 'maxuses' => 100],
            ['code' => 'WELCOME5', 'type' => 'absolute', 'value' => 5, 'once_per_client' => 1, 'active' => 1, 'maxuses' => 100],
        ] as $promo) {
            if (self::findBy($existingPromos, 'code', $promo['code'])) {
                $this->note("  promo {$promo['code']} exists, skip");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create promo {$promo['code']}");

                continue;
            }
            $r = $this->api->callSafe('admin/product/promo_create', $promo);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('promo/create ' . $promo['code'], $r['__error']);
            } else {
                $this->note("  created promo {$promo['code']} id={$r}");
                $this->manifest['promos'][] = ['code' => $promo['code'], 'id' => $r];
            }
        }

        // ------------------------------------------------------- 3. clients
        $this->note('-- clients (24 demo clients)');
        $firstNames = ['Ava', 'Liam', 'Mia', 'Noah', 'Emma', 'Oliver', 'Sophia', 'Ethan', 'Isabella', 'Mason', 'Lucas', 'Amelia', 'James', 'Harper', 'Benjamin', 'Evelyn', 'Alexander', 'Charlotte', 'Daniel', 'Grace', 'Henry', 'Chloe', 'Samuel', 'Lily'];
        $lastNames = ['Smith', 'Novak', 'Garcia', 'Muller', 'Kowalski', 'Dubois', 'Rossi', 'Silva', 'Tanaka', 'Murphy', 'Johansson', 'Costa', 'Weber', 'Fontaine', 'Khan', 'Petrov', 'Okafor', 'Larsen', 'Moreau', 'Bakker', 'Singh', 'Novakova', 'Brown', 'Davis'];
        $countries = ['GB', 'US', 'DE', 'FR', 'PL', 'IE', 'NL', 'ES', 'IT', 'CA', 'IN', 'BR'];
        $currCycle = ['GBP', 'USD', 'EUR'];
        $groupCycle = array_values($groupIds);
        if ($groupCycle === []) {
            $groupCycle = [null];
        }
        $clientCount = $this->limitClients > 0 ? min($this->limitClients, 24) : 24;
        $demoEmails = [];
        for ($i = 1; $i <= $clientCount; ++$i) {
            $demoEmails[sprintf('demo.client%02d@example.com', $i)] = true;
        }
        $existingClients = $this->fetchAll('admin/client/get_list');
        $clientIds = [];
        foreach ($existingClients as $c) {
            // Only demo identities: never attach orders, invoices or tickets to real clients.
            $email = (string) ($c['email'] ?? '');
            if (isset($demoEmails[$email])) {
                $clientIds[$email] = (int) $c['id'];
            }
        }
        for ($i = 0; $i < $clientCount; ++$i) {
            $n = $i + 1;
            $email = sprintf('demo.client%02d@example.com', $n);
            if (isset($clientIds[$email])) {
                $this->note("  client {$email} exists id={$clientIds[$email]}");
                $this->manifest['clients'][] = ['email' => $email, 'id' => $clientIds[$email], 'existing' => true];

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create client {$email}");
                // Keep planned clients in the plan so dependent orders, invoices
                // and tickets preview against them too. The id is a placeholder.
                $clientIds[$email] = $n;
                $this->manifest['clients'][] = ['email' => $email, 'id' => $n, 'planned' => true];

                continue;
            }
            $status = 'active';
            if ($n === 23) {
                $status = 'suspended';
            }
            if ($n === 24) {
                $status = 'canceled';
            }
            $payload = [
                'email' => $email,
                'password' => 'DemoPass123!',
                'first_name' => $firstNames[$i],
                'last_name' => $lastNames[$i],
                'country' => $countries[$i % count($countries)],
                'city' => 'Demo City ' . $n,
                'address_1' => $n . ' Example Street',
                'postcode' => 'DEMO' . $n,
                'phone_cc' => '44',
                'phone' => '7700900' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'company' => $n % 3 === 0 ? "Demo Company {$n} Ltd" : '',
                'type' => $n % 3 === 0 ? 'company' : 'individual',
                'currency' => $currCycle[$i % count($currCycle)],
                'status' => $status,
                'send_welcome_email' => 0,
                'notes' => "Demo seed client #{$n} for testing.",
            ];
            $gid = $groupCycle[$i % count($groupCycle)];
            if ($gid) {
                $payload['group_id'] = $gid;
            }
            $r = $this->api->callSafe('admin/client/create', $payload);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('client/create ' . $email, $r['__error']);

                continue;
            }
            $clientIds[$email] = (int) $r;
            $this->note("  created client {$email} id={$r}");
            $this->manifest['clients'][] = ['email' => $email, 'id' => (int) $r];
        }
        // fund a few balances for pay_with_credits coverage
        $funded = 0;
        foreach (array_slice($this->manifest['clients'], 0, 6) as $c) {
            if ($this->dryRun || !empty($c['existing'])) {
                continue;
            }
            $r = $this->api->callSafe('admin/client/balance_add_funds', ['id' => $c['id'], 'amount' => 40, 'description' => 'Demo seed credit']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('balance_add_funds client ' . $c['id'], $r['__error']);
            } else {
                ++$funded;
            }
        }
        $this->note("  funded {$funded} client balances");

        // ------------------------------------------------------- 4. orders
        $this->note('-- orders (mixed states)');
        $orderProductCycle = [];
        foreach ($existingProducts as $p) {
            if (in_array($p['type'] ?? '', ['domain', 'downloadable'], true)) {
                continue; // domain handled via dedicated $domainPid orders; downloadable needs files config, catalog-only
            }
            // Never activate demo orders against products the seeder didn't
            // create: a target's own hosting product can provision on a live
            // server, and gateway test mode does not sandbox that.
            if (!str_starts_with((string) ($p['title'] ?? ''), 'Demo')) {
                continue;
            }
            $orderProductCycle[] = (int) $p['id'];
        }
        foreach ($this->manifest['products'] as $p) {
            if (in_array($p['type'] ?? '', ['domain', 'downloadable'], true)) {
                continue;
            }
            if (!in_array((int) $p['id'], $orderProductCycle, true)) {
                $orderProductCycle[] = (int) $p['id'];
            }
        }
        $domainPid = null;
        foreach ($existingProducts as $p) {
            if (($p['type'] ?? '') === 'domain') {
                $domainPid = (int) $p['id'];
            }
        }
        if ($domainPid === null) {
            foreach ($this->manifest['products'] as $p) {
                if (($p['type'] ?? '') === 'domain') {
                    $domainPid = (int) $p['id'];
                }
            }
        }
        $periodCycle = ['1M', '1Y', '1M', '3M', '1Y', '1M'];
        // Activating a domain order triggers a live registrar call, which could
        // register a real domain and incur charges. Only seed domain orders
        // when the registrar behind .com is verifiably in test mode.
        $registrarsInTestMode = [];
        foreach ($this->fetchAll('admin/servicedomain/registrar_get_list') as $r) {
            $registrarsInTestMode[(int) $r['id']] = (bool) ($r['test_mode'] ?? false);
        }
        $comRegistrarId = null;
        foreach ($existingTlds as $t) {
            if (($t['tld'] ?? '') === '.com') {
                $comRegistrarId = (int) ($t['registrar']['id'] ?? 0);
            }
        }
        $domainsSafe = $comRegistrarId !== null && ($registrarsInTestMode[$comRegistrarId] ?? false);
        if (!$domainsSafe) {
            $this->note('  domain orders skipped: the registrar for .com is not in test mode');
        }
        $clientIdList = array_values($clientIds);
        $orderTarget = $this->limitOrders > 0 ? $this->limitOrders : 36;
        $ordersCreated = [];
        $orderIndex = 0;
        foreach ($clientIdList as $ci => $cid) {
            if (count($ordersCreated) >= $orderTarget) {
                break;
            }
            if ($orderProductCycle === []) {
                $this->recordError('orders', 'no orderable products available');

                break;
            }
            $rounds = $ci < 12 ? 2 : 1; // first 12 clients get 2 orders => ~36 total
            for ($k = 0; $k < $rounds && count($ordersCreated) < $orderTarget; ++$k) {
                ++$orderIndex;
                $pid = $orderProductCycle[($orderIndex - 1) % count($orderProductCycle)];
                $period = $periodCycle[($orderIndex - 1) % count($periodCycle)];
                $title = "Demo order #{$orderIndex}";
                // domain orders need special config; force every 6th order to be a domain
                $isDomain = $domainsSafe && $domainPid !== null && ($orderIndex % 6 === 0);
                if ($isDomain) {
                    $pid = $domainPid;
                }
                if ($this->dryRun) {
                    $this->note("  [dry] create order client={$cid} product={$pid}");

                    continue;
                }

                // probe type + pricing first so period/config are always valid for this product
                try {
                    $probe = $this->api->call('admin/product/get', ['id' => $pid]);
                    $productType = $probe['type'] ?? '';
                    $productPricing = $probe['pricing'] ?? [];
                } catch (\Throwable) {
                    $productType = '';
                    $productPricing = [];
                }
                // The cycle holds seeder-created non-domain products only, so a
                // domain result here means the catalog changed mid-run. Never
                // let it become a domain order on a live registrar.
                if (!$domainsSafe && $productType === 'domain') {
                    $this->recordError("order/create client={$cid}", 'skipping domain product: the registrar for .com is not in test mode');

                    continue;
                }
                // pick a billing period the product actually offers (once/free products take no period)
                $effPeriod = null;
                if (($productPricing['type'] ?? '') === 'recurrent' && is_array($productPricing['recurrent'] ?? null)) {
                    $enabledPeriods = array_keys(array_filter($productPricing['recurrent'], static fn ($r): bool => !empty($r['enabled'])));
                    if (in_array($period, $enabledPeriods, true)) {
                        $effPeriod = $period;
                    } elseif ($enabledPeriods !== []) {
                        $effPeriod = $enabledPeriods[($orderIndex - 1) % count($enabledPeriods)];
                    }
                }
                // idempotency: match by title via get_list search is unreliable; use manifest only per-run.
                // Across runs duplicates are acceptable but bounded by the per-run target; titles stay unique per run index.
                $payload = [
                    'client_id' => $cid,
                    'product_id' => $pid,
                    'invoice_option' => 'issue-invoice',
                    'title' => $title . ' (client ' . $cid . ')',
                    'notes' => 'Demo seed order.',
                ];
                if ($effPeriod !== null) {
                    $payload['period'] = $effPeriod;
                }
                if ($orderIndex % 7 === 0) {
                    $payload['promo'] = 'DEMO10';
                }
                if ($isDomain || $productType === 'domain') {
                    $payload['config'] = [
                        'action' => 'register',
                        'register_sld' => 'demoseed' . $orderIndex . $ci,
                        'register_tld' => '.com',
                        'register_years' => 1,
                    ];
                    $payload['period'] = '1Y';
                } elseif ($productType === 'hosting') {
                    // hosting products need domain.action owndomain
                    $payload['config'] = [
                        'domain' => [
                            'action' => 'owndomain',
                            'owndomain_sld' => 'demoseed' . $orderIndex,
                            'owndomain_tld' => '.example.com',
                        ],
                    ];
                }
                // hosting config without server would fail; skip_validation keeps seeding moving
                if ($productType === 'hosting' && $serverId === null) {
                    $payload['skip_validation'] = 1;
                }

                try {
                    $oid = $this->api->call('admin/order/create', $payload);
                    $oid = (int) $oid;
                    $this->note("  created order id={$oid} client={$cid} product={$pid}" . ($isDomain ? ' (domain)' : ''));
                    $ordersCreated[] = $oid;
                    $this->manifest['orders'][] = ['id' => $oid, 'client_id' => $cid, 'product_id' => $pid];
                } catch (\Throwable $e) {
                    $this->recordError("order/create client={$cid} product={$pid}", $e->getMessage());
                }
            }
        }

        // distribute order states (tolerant: check status first, best-effort transitions)
        foreach ($ordersCreated as $idx => $oid) {
            if ($this->dryRun) {
                continue;
            }
            $mod = $idx % 10;

            try {
                $orderInfo = $this->api->call('admin/order/get', ['id' => $oid]);
                $orderStatus = $orderInfo['status'] ?? '';
            } catch (\Throwable) {
                $orderStatus = '';
            }
            $canActivate = in_array($orderStatus, ['pending_setup', 'failed_setup'], true);
            $isActive = $orderStatus === 'active';

            try {
                if ($mod <= 3 || $mod === 7 || $mod === 8) {
                    if ($canActivate) {
                        $this->api->call('admin/order/activate', ['id' => $oid, 'force' => 0]);
                        $isActive = true;
                    }
                    if ($mod === 8 && $isActive) {
                        // leave one activated then suspended+unsuspended to exercise both
                        $this->api->call('admin/order/suspend', ['id' => $oid, 'reason' => 'Demo seed suspension test']);
                        $this->api->call('admin/order/unsuspend', ['id' => $oid]);
                    }
                } elseif ($mod === 5) {
                    if ($canActivate) {
                        $this->api->call('admin/order/activate', ['id' => $oid]);
                        $isActive = true;
                    }
                    if ($isActive) {
                        $this->api->call('admin/order/suspend', ['id' => $oid, 'reason' => 'Demo seed: overdue suspension']);
                    }
                } elseif ($mod === 6) {
                    if ($orderStatus !== 'canceled') {
                        if ($canActivate) {
                            try {
                                $this->api->call('admin/order/activate', ['id' => $oid]);
                            } catch (\Throwable) {
                                // fall through to cancel attempt
                            }
                        }

                        try {
                            $this->api->call('admin/order/cancel', ['id' => $oid, 'reason' => 'Demo seed: client request']);
                        } catch (\Throwable $e) {
                            // pending_setup orders cannot be canceled; leave as-is for pending coverage
                            $this->recordError("order/cancel id={$oid} (status={$orderStatus})", $e->getMessage());
                        }
                    }
                }
                // 4, 9: leave as-is (pending coverage)
            } catch (\Throwable $e) {
                $this->recordError("order/state id={$oid} (status={$orderStatus})", $e->getMessage());
            }
        }
        $this->note('  order states distributed (active/pending/suspended/canceled)');

        // ------------------------------------------------------- 5. invoices
        $this->note('-- invoices: manual + lifecycle (paid/credit/refund/subscription)');
        $manualInvoiceIds = [];
        $manualClients = array_slice(array_values($clientIds), 0, 4);
        foreach ($manualClients as $mi => $cid) {
            if ($this->dryRun) {
                $this->note("  [dry] manual invoice for client {$cid}");

                continue;
            }

            try {
                $iid = $this->api->call('admin/invoice/prepare', [
                    'client_id' => $cid,
                    'gateway_id' => $customGatewayId,
                    'items' => [
                        ['title' => 'Demo manual line - setup', 'price' => 25 + $mi, 'quantity' => 1],
                        ['title' => 'Demo manual line - service', 'price' => 10, 'quantity' => 2],
                    ],
                ]);
                $this->api->call('admin/invoice/issue', ['id' => (int) $iid]);
                $this->note("  manual invoice id={$iid} client={$cid}");
                $manualInvoiceIds[] = (int) $iid;
                $this->manifest['invoices_manual'][] = ['id' => (int) $iid, 'client_id' => $cid];
            } catch (\Throwable $e) {
                $this->recordError('invoice/manual client=' . $cid, $e->getMessage());
            }
        }

        // collect order invoices
        $invoiceIds = [];
        foreach ($ordersCreated as $oid) {
            try {
                $o = $this->api->call('admin/order/get', ['id' => $oid]);
                foreach (['invoice_id', 'unpaid_invoice_id'] as $k) {
                    if (!empty($o[$k]) && !in_array((int) $o[$k], $invoiceIds, true)) {
                        $invoiceIds[] = (int) $o[$k];
                    }
                }
            } catch (\Throwable $e) {
                $this->recordError('order/get invoice id=' . $oid, $e->getMessage());
            }
        }
        $allInvoices = array_values(array_unique(array_merge($invoiceIds, $manualInvoiceIds)));
        $this->note('  found ' . count($allInvoices) . ' invoices from orders+manual');

        // mark ~half paid, create transactions, refund one, credits for two
        // Custom gateway requires transactionId on mark_as_paid; prefer ClientBalance (no txn id needed).
        $payGatewayId = $gatewayIds['ClientBalance'] ?? $customGatewayId;
        $payNeedsTxnId = !isset($gatewayIds['ClientBalance']);
        $paid = 0;
        $paidInvoiceIds = [];
        foreach ($allInvoices as $ii => $iid) {
            if ($this->dryRun) {
                continue;
            }
            $slot = $ii % 10;

            try {
                if ($slot <= 4) {
                    $markPayload = ['id' => $iid, 'gateway_id' => $payGatewayId, 'execute' => 1];
                    if ($payNeedsTxnId) {
                        $markPayload['transactionId'] = 'DEMO-TXN-' . $iid;
                    }
                    $this->api->call('admin/invoice/mark_as_paid', $markPayload);
                    ++$paid;
                    $paidInvoiceIds[] = $iid;
                    // NOTE: pass unique get/post/server so the IPN-hash dedupe treats each as distinct.
                    $txn = $this->api->callSafe('admin/invoice/transaction_create', [
                        'invoice_id' => $iid,
                        'gateway_id' => $customGatewayId,
                        'txn_id' => 'DEMO-TXN-' . $iid . '-' . time(),
                        'get' => ['demo_invoice' => $iid],
                        'post' => ['txn_id' => 'DEMO-TXN-' . $iid, 'demo' => $iid],
                        'server' => ['DEMO_SEED' => 'invoice-' . $iid],
                        'amount' => 1,
                        'currency' => 'GBP',
                        'status' => 'complete',
                        'type' => 'payment',
                    ]);
                    if (is_array($txn) && isset($txn['__error'])) {
                        $this->recordError('transaction/create invoice=' . $iid, $txn['__error']);
                    } else {
                        $this->manifest['transactions'][] = ['id' => $txn, 'invoice_id' => $iid];
                    }
                } elseif ($slot === 5) {
                    // leave unpaid (overdue coverage)
                } elseif ($slot === 6) {
                    $r = $this->api->callSafe('admin/invoice/pay_with_credits', ['id' => $iid]);
                    if (is_array($r) && isset($r['__error'])) {
                        $this->recordError('invoice/pay_with_credits id=' . $iid, $r['__error']);
                    }
                } elseif ($slot === 7) {
                    $t = $this->api->callSafe('admin/invoice/transaction_create', [
                        'invoice_id' => $iid,
                        'gateway_id' => $customGatewayId,
                        'txn_id' => 'DEMO-TXN-PENDING-' . $iid . '-' . time(),
                        'get' => ['demo_invoice' => $iid, 'demo_status' => 'pending'],
                        'post' => ['txn_id' => 'DEMO-TXN-PENDING-' . $iid, 'demo' => $iid],
                        'server' => ['DEMO_SEED' => 'pending-' . $iid],
                        'amount' => 1,
                        'currency' => 'GBP',
                        'status' => 'pending',
                        'type' => 'payment',
                    ]);
                    if (is_array($t) && isset($t['__error'])) {
                        $this->recordError('transaction/create (pending) invoice=' . $iid, $t['__error']);
                    } else {
                        $this->manifest['transactions'][] = ['id' => $t, 'invoice_id' => $iid, 'status' => 'pending'];
                    }
                }
            } catch (\Throwable $e) {
                $this->recordError('invoice/lifecycle id=' . $iid, $e->getMessage());
            }
        }
        $this->note("  marked ~{$paid} invoices paid + transactions");
        // one refund on the first invoice that was actually marked paid
        $firstPaid = $paidInvoiceIds[0] ?? null;
        if ($firstPaid !== null && !$this->dryRun) {
            $r = $this->api->callSafe('admin/invoice/refund', ['id' => $firstPaid, 'note' => 'Demo seed refund test']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('invoice/refund id=' . $firstPaid, $r['__error']);
            } else {
                $this->note("  refunded invoice {$firstPaid}");
            }
        }
        // one subscription (skip if DEMO-SUB-001 already exists)
        $subClient = array_values($clientIds)[0] ?? null;
        if ($subClient !== null && !$this->dryRun) {
            $existingSubs = $this->fetchAll('admin/invoice/subscription_get_list');
            if (self::findBy($existingSubs, 'sid', 'DEMO-SUB-001')) {
                $this->note('  subscription DEMO-SUB-001 exists, skip');
            } else {
                try {
                    $clientInfo = $this->api->call('admin/client/get', ['id' => $subClient]);
                    $cur = $clientInfo['currency'] ?? 'GBP';
                } catch (\Throwable) {
                    $cur = 'GBP';
                }
                $s = $this->api->callSafe('admin/invoice/subscription_create', [
                    'client_id' => $subClient,
                    'gateway_id' => $customGatewayId,
                    'currency' => $cur,
                    'amount' => 9.99,
                    'period' => '1M',
                    'status' => 'active',
                    'sid' => 'DEMO-SUB-001',
                ]);
                if (is_array($s) && isset($s['__error'])) {
                    $this->recordError('subscription/create', $s['__error']);
                } else {
                    $this->note("  created subscription id={$s}");
                    $this->manifest['subscriptions'][] = ['id' => $s, 'client_id' => $subClient];
                }
            }
        }

        // ------------------------------------------------------- 6. support
        $this->note('-- support: helpdesks, canned, KB, tickets');
        $helpdesks = $this->fetchAll('admin/support/helpdesk_get_list');
        $helpdeskIds = [];
        foreach ($helpdesks as $h) {
            $helpdeskIds[$h['name']] = (int) $h['id'];
        }
        foreach (['Demo Billing' => 'billing@example.com', 'Demo Technical' => 'tech@example.com'] as $hname => $helpdeskEmail) {
            if (isset($helpdeskIds[$hname])) {
                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] create helpdesk {$hname}");

                continue;
            }
            $r = $this->api->callSafe('admin/support/helpdesk_create', ['name' => $hname, 'email' => $helpdeskEmail, 'close_after' => 72, 'signature' => 'Demo support team']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('helpdesk/create ' . $hname, $r['__error']);
            } else {
                $helpdeskIds[$hname] = (int) $r;
                $this->note("  created helpdesk {$hname} id={$r}");
            }
        }
        $this->manifest['helpdesks'] = $helpdeskIds;
        // canned categories + responses
        $cannedPairs = $this->api->callSafe('admin/support/canned_category_pairs', []);
        if (!is_array($cannedPairs) || isset($cannedPairs['__error'])) {
            $cannedPairs = [];
        }
        $cannedCatIds = [];
        foreach (['Demo General Replies', 'Demo Billing Replies'] as $ct) {
            $fid = array_search($ct, $cannedPairs, true);
            if ($fid !== false) {
                $cannedCatIds[$ct] = (int) $fid;

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] canned category {$ct}");

                continue;
            }
            $r = $this->api->callSafe('admin/support/canned_category_create', ['title' => $ct]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('canned_category/create ' . $ct, $r['__error']);
            } else {
                $cannedCatIds[$ct] = (int) $r;
                $this->note("  created canned category {$ct}");
            }
        }
        $this->manifest['canned_categories'] = $cannedCatIds;
        $existingCanned = $this->fetchAll('admin/support/canned_get_list');
        $cannedDefs = [
            ['title' => 'Demo: welcome reply', 'category' => 'Demo General Replies', 'content' => 'Thanks for contacting demo support. We are looking into this.'],
            ['title' => 'Demo: billing reply', 'category' => 'Demo Billing Replies', 'content' => 'Your demo invoice has been reviewed. Let us know if anything looks off.'],
            ['title' => 'Demo: escalation reply', 'category' => 'Demo General Replies', 'content' => 'Escalating this demo ticket to a senior agent.'],
        ];
        foreach ($cannedDefs as $cd) {
            if (self::findBy($existingCanned, 'title', $cd['title'])) {
                $this->note("  canned '{$cd['title']}' exists, skip");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] canned {$cd['title']}");

                continue;
            }
            $r = $this->api->callSafe('admin/support/canned_create', ['title' => $cd['title'], 'category_id' => $cannedCatIds[$cd['category']] ?? reset($cannedCatIds), 'content' => $cd['content']]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('canned/create ' . $cd['title'], $r['__error']);
            } else {
                $this->manifest['canned'][] = ['title' => $cd['title'], 'id' => $r];
            }
        }

        // KB categories + articles
        $kbCats = $this->api->callSafe('admin/support/kb_category_get_pairs', []);
        if (!is_array($kbCats) || isset($kbCats['__error'])) {
            $kbCats = [];
        }
        $kbCatIds = [];
        foreach (['Demo Getting Started' => 'Demo onboarding guides.', 'Demo Billing FAQ' => 'Demo billing answers.'] as $kt => $kd) {
            $fid = array_search($kt, $kbCats, true);
            if ($fid !== false) {
                $kbCatIds[$kt] = (int) $fid;

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] KB category {$kt}");

                continue;
            }
            $r = $this->api->callSafe('admin/support/kb_category_create', ['title' => $kt, 'description' => $kd]);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('kb_category/create ' . $kt, $r['__error']);
            } else {
                $kbCatIds[$kt] = (int) $r;
                $this->note("  created KB category {$kt}");
            }
        }
        $this->manifest['kb_categories'] = $kbCatIds;
        $existingKb = $this->fetchAll('admin/support/kb_article_get_list');
        $kbArticles = [
            ['title' => 'Demo: How to pay your first invoice', 'cat' => 'Demo Billing FAQ', 'content' => 'Open **Invoices**, pick the unpaid demo invoice, and use the Custom gateway.'],
            ['title' => 'Demo: How to open a ticket', 'cat' => 'Demo Getting Started', 'content' => 'Go to **Support**, choose a helpdesk, and describe the issue.'],
            ['title' => 'Demo: Understanding order statuses', 'cat' => 'Demo Getting Started', 'content' => 'Orders move from pending to active, and can be suspended or canceled.'],
            ['title' => 'Demo: Using promo code DEMO10', 'cat' => 'Demo Billing FAQ', 'content' => 'Apply `DEMO10` at checkout for 10% off demo products.'],
            ['title' => 'Demo: Managing your profile', 'cat' => 'Demo Getting Started', 'content' => 'Update company, VAT, and notification email from the client profile.'],
        ];
        foreach ($kbArticles as $kb) {
            if (self::findBy($existingKb, 'title', $kb['title'])) {
                $this->note("  KB '{$kb['title']}' exists, skip");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] KB article {$kb['title']}");

                continue;
            }
            $r = $this->api->callSafe('admin/support/kb_article_create', ['kb_article_category_id' => $kbCatIds[$kb['cat']] ?? reset($kbCatIds), 'title' => $kb['title'], 'content' => $kb['content'], 'status' => 'active']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('kb_article/create ' . $kb['title'], $r['__error']);
            } else {
                $this->manifest['kb_articles'][] = ['title' => $kb['title'], 'id' => $r];
                $this->note("  created KB article {$kb['title']}");
            }
        }

        // tickets
        $subjects = [
            'Demo: invoice question', 'Demo: cannot access service', 'Demo: upgrade request',
            'Demo: domain transfer help', 'Demo: refund request', 'Demo: VAT on invoice looks wrong',
            'Demo: welcome — getting started', 'Demo: SSL needed', 'Demo: payment failed, advice?',
            'Demo: cancel one service',
        ];
        $bodies = [
            'Hi team, this is demo ticket content. Please advise on next steps.',
            'Hello, testing the demo flow. The demo invoice total needs a review.',
            'Hi, demo client here. Service shows pending — can you activate it?',
            'Hello, please help migrate demo data. Nothing urgent, testing only.',
        ];
        $ticketTarget = $this->limitTickets > 0 ? $this->limitTickets : 20;
        // Route demo tickets only to the seeded demo helpdesks so they never
        // land in the target's operational support queues.
        $helpdeskKeys = array_values(array_filter(
            $helpdeskIds,
            static fn (int $id, string $name): bool => str_starts_with($name, 'Demo'),
            ARRAY_FILTER_USE_BOTH,
        ));
        for ($t = 0; $t < $ticketTarget; ++$t) {
            if ($clientIdList === []) {
                $this->recordError('tickets', 'no demo clients available');

                break;
            }
            if ($helpdeskKeys === []) {
                $this->recordError('tickets', 'no demo helpdesks available');

                break;
            }
            $subj = 'Demo ticket #' . ($t + 1) . ' - ' . $subjects[$t % count($subjects)];
            if ($this->dryRun) {
                $this->note("  [dry] ticket {$subj}");

                continue;
            }
            $cid = $clientIdList[$t % count($clientIdList)];

            try {
                $tid = $this->api->call('admin/support/ticket_create', [
                    'client_id' => $cid,
                    'support_helpdesk_id' => $helpdeskKeys[$t % count($helpdeskKeys)],
                    'subject' => $subj,
                    'content' => $bodies[$t % count($bodies)],
                ]);
                $tid = (int) $tid;
                $this->manifest['tickets'][] = ['id' => $tid, 'subject' => $subj, 'client_id' => $cid];
                // admin reply on most, second reply on some, close ~1/3
                if ($t % 4 !== 3) {
                    $this->api->call('admin/support/ticket_reply', ['id' => $tid, 'content' => 'Demo admin reply: thanks, we are on it.']);
                }
                if ($t % 5 === 0) {
                    $this->api->call('admin/support/ticket_reply', ['id' => $tid, 'content' => 'Demo follow-up: anything else we can test for you?']);
                }
                if ($t % 2 === 0) {
                    $n = $this->api->callSafe('admin/support/note_create', ['ticket_id' => $tid, 'note' => 'Demo internal note.']);
                    if (is_array($n) && isset($n['__error'])) {
                        $this->recordError('note/create ticket=' . $tid, $n['__error']);
                    }
                }
                if ($t % 3 === 0) {
                    $this->api->call('admin/support/ticket_close', ['id' => $tid]);
                }
                if ($t === 1) {
                    $c = $this->api->callSafe('admin/support/task_complete', ['id' => $tid]);
                    if (is_array($c) && isset($c['__error'])) {
                        $this->recordError('task/complete ticket=' . $tid, $c['__error']);
                    }
                }
                $this->note("  ticket id={$tid} {$subj}");
            } catch (\Throwable $e) {
                $this->recordError('ticket/create ' . $subj, $e->getMessage());
            }
        }

        // ------------------------------------------------------- 7. news
        $this->note('-- news');
        $existingNews = $this->fetchAll('admin/news/get_list');
        foreach ([
            ['title' => 'Demo: scheduled maintenance window', 'content' => 'Demo maintenance notice for testing the news block. No action needed.'],
            ['title' => 'Demo: new hosting plans available', 'content' => 'Try the demo shared and VPS plans seeded with this dataset.'],
            ['title' => 'Demo: promo code DEMO10', 'content' => 'Use DEMO10 on demo orders while testing checkout.'],
        ] as $nw) {
            if (self::findBy($existingNews, 'title', $nw['title'])) {
                $this->note("  news '{$nw['title']}' exists, skip");

                continue;
            }
            if ($this->dryRun) {
                $this->note("  [dry] news {$nw['title']}");

                continue;
            }
            $r = $this->api->callSafe('admin/news/create', $nw + ['status' => 'active']);
            if (is_array($r) && isset($r['__error'])) {
                $this->recordError('news/create ' . $nw['title'], $r['__error']);
            } else {
                $this->manifest['news'][] = ['title' => $nw['title'], 'id' => $r];
                $this->note("  news {$nw['title']} id={$r}");
            }
        }

        // ------------------------------------------------------- manifest
        (new Filesystem())->mkdir(dirname($this->manifestPath));
        // Error text can carry invalid UTF-8 from remote responses; substitute
        // it so encoding never fails and the previous manifest is not lost.
        $encoded = json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false || file_put_contents($this->manifestPath, $encoded) === false) {
            throw new RuntimeException("Failed to write manifest: {$this->manifestPath}");
        }
        $this->note('Wrote manifest: ' . $this->manifestPath);
        $this->note('Done. Errors: ' . count($this->manifest['errors']));

        return count($this->manifest['errors']);
    }

    private function note(string $message): void
    {
        $this->output->writeln($message);
    }

    private function recordError(string $where, string $message): void
    {
        $this->manifest['errors'][] = "{$where}: {$message}";
        $this->note("  [WARN] {$where}: {$message}");
    }

    /**
     * Walk every page of a paginated list endpoint: existence checks must see
     * records beyond page 1, otherwise a rerun recreates records it failed to find.
     */
    private function fetchAll(string $endpoint, string $listKey = 'list'): array
    {
        $records = [];
        $page = 1;
        $pages = 1;
        while ($page <= $pages) {
            try {
                $res = $this->api->call($endpoint, ['per_page' => 100, 'page' => $page]);
            } catch (\Throwable $e) {
                $this->recordError($endpoint . ' page ' . $page, $e->getMessage());

                return $records;
            }
            if (is_array($res) && isset($res[$listKey]) && is_array($res[$listKey])) {
                array_push($records, ...array_values($res[$listKey]));
                $pages = max(1, (int) ($res['pages'] ?? 1));
            } elseif (is_array($res) && array_is_list($res)) {
                array_push($records, ...$res);

                break;
            } else {
                break;
            }
            ++$page;
        }

        return $records;
    }

    private static function findBy(array $list, string $field, mixed $value): ?array
    {
        foreach ($list as $row) {
            if (is_array($row) && ($row[$field] ?? null) == $value) {
                return $row;
            }
        }

        return null;
    }
}

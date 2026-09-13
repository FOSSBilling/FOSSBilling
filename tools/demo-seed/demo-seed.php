#!/usr/bin/env php
<?php

/**
 * FOSSBilling demo/test-data seeder.
 *
 * Additive test-data seeder. Foundation/catalog records use a "Demo" prefix
 * and are skipped when they already exist (matched by email/title/code);
 * orders, invoices and tickets are appended on every run, so re-running
 * grows those lists rather than converging.
 *
 * Usage:
 *   APP_URL=https://fossbilling.ddev.site TEST_API_KEY=... php tools/demo-seed/demo-seed.php
 *   php tools/demo-seed/demo-seed.php --url=https://fossbilling.ddev.site --key=... [--dry-run] [--limit-clients=5]
 *
 * Output: tools/demo-seed/manifest.json
 *
 * Requires: PHP 8.3+, curl extension, an admin API key.
 * Sends no real email (clients created with send_welcome_email=0) and only
 * enables offline-safe gateways (Custom, ClientBalance).
 */

declare(strict_types=1);

$options = getopt('', ['url:', 'key:', 'dry-run', 'limit-clients::', 'limit-orders::', 'limit-tickets::', 'help']);
if (isset($options['help'])) {
    echo "Usage: demo-seed.php [--url=...] [--key=...] [--dry-run] [--limit-clients=N] [--limit-orders=N] [--limit-tickets=N]\n";
    echo "Env: APP_URL, TEST_API_KEY\n";
    exit(0);
}

$baseUrl = rtrim((string) ($options['url'] ?? getenv('APP_URL') ?: 'https://fossbilling.ddev.site'), '/');
$apiKey = (string) ($options['key'] ?? getenv('TEST_API_KEY') ?: '');
$dryRun = isset($options['dry-run']);
$limitClients = isset($options['limit-clients']) ? (int) $options['limit-clients'] : 0;
$limitOrders = isset($options['limit-orders']) ? (int) $options['limit-orders'] : 0;
$limitTickets = isset($options['limit-tickets']) ? (int) $options['limit-tickets'] : 0;

if ($apiKey === '') {
    fwrite(STDERR, "Missing API key. Set TEST_API_KEY or pass --key=...\n");
    exit(2);
}

$manifest = [
    'base_url' => $baseUrl,
    'seeded_at' => date('c'),
    'dry_run' => $dryRun,
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

function api(string $endpoint, array $params = []): mixed
{
    global $baseUrl, $apiKey;

    $url = $baseUrl . '/api/' . ltrim($endpoint, '/');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "admin:{$apiKey}",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
    ]);
    $out = curl_exec($ch);
    if ($out === false) {
        $err = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException("cURL failed for {$endpoint}: {$err}");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $out, true);
    if (!is_array($decoded) || !array_key_exists('result', $decoded) || !array_key_exists('error', $decoded)) {
        throw new RuntimeException("Invalid JSON from {$endpoint} (HTTP {$code}): " . substr((string) $out, 0, 300));
    }
    if (!empty($decoded['error'])) {
        $msg = $decoded['error']['message'] ?? 'Unknown API error';
        $errorCode = $decoded['error']['code'] ?? 0;

        throw new RuntimeException("API {$endpoint} failed: {$msg} (code {$errorCode})");
    }

    return $decoded['result'];
}

function apiSafe(string $endpoint, array $params = []): mixed
{
    try {
        return api($endpoint, $params);
    } catch (Throwable $e) {
        return ['__error' => $e->getMessage()];
    }
}

function note(string $msg): void
{
    echo $msg . "\n";
}

function recordError(string $where, string $msg): void
{
    global $manifest;
    $manifest['errors'][] = "{$where}: {$msg}";
    note("  [WARN] {$where}: {$msg}");
}

/** @return array<int, array> */
function fetchAll(string $endpoint, string $listKey = 'list'): array
{
    try {
        $res = api($endpoint, ['per_page' => 100, 'page' => 1]);
    } catch (Throwable $e) {
        recordError($endpoint, $e->getMessage());

        return [];
    }
    if (is_array($res) && isset($res[$listKey]) && is_array($res[$listKey])) {
        return array_values($res[$listKey]);
    }
    if (is_array($res) && array_is_list($res)) {
        return $res;
    }

    return [];
}

function findBy(array $list, string $field, mixed $value): ?array
{
    foreach ($list as $row) {
        if (is_array($row) && ($row[$field] ?? null) == $value) {
            return $row;
        }
    }

    return null;
}

// ---------------------------------------------------------------- preflight
note('== FOSSBilling demo seeder ==');
note("Target: {$baseUrl}" . ($dryRun ? ' [DRY RUN]' : ''));

try {
    $behind = api('admin/system/is_behind_on_patches');
    note('Preflight OK (is_behind_on_patches=' . var_export($behind, true) . ')');
} catch (Throwable $e) {
    fwrite(STDERR, 'Preflight failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// ------------------------------------------------------- 1. currencies/taxes
note('-- foundation: currencies, taxes, groups, staff, gateways');

$existingCurrencies = fetchAll('admin/currency/get_list');
foreach ([
    ['code' => 'USD', 'conversion_rate' => 1.27, 'is_rate_manual' => 1],
    ['code' => 'EUR', 'conversion_rate' => 1.17, 'is_rate_manual' => 1],
    ['code' => 'CAD', 'conversion_rate' => 1.74, 'is_rate_manual' => 1],
] as $c) {
    if (findBy($existingCurrencies, 'code', $c['code'])) {
        note("  currency {$c['code']} exists, skip");
        $manifest['currencies'][] = $c['code'] . ' (existing)';

        continue;
    }
    if ($dryRun) {
        note("  [dry] create currency {$c['code']}");

        continue;
    }
    $r = apiSafe('admin/currency/create', $c);
    if (is_array($r) && isset($r['__error'])) {
        recordError('currency/create ' . $c['code'], $r['__error']);
    } else {
        note("  created currency {$c['code']}");
        $manifest['currencies'][] = $c['code'];
    }
}

$existingTaxes = fetchAll('admin/invoice/tax_get_list');
$taxDefs = [
    ['name' => 'Demo VAT 20%', 'taxrate' => 20],
    ['name' => 'Demo US-CA Sales Tax 8.5%', 'taxrate' => 8.5, 'country' => 'US', 'state' => 'CA'],
    ['name' => 'Demo Reduced 5%', 'taxrate' => 5, 'country' => 'GB'],
];
foreach ($taxDefs as $t) {
    if (findBy($existingTaxes, 'name', $t['name'])) {
        note("  tax '{$t['name']}' exists, skip");

        continue;
    }
    if ($dryRun) {
        note("  [dry] create tax {$t['name']}");

        continue;
    }
    $r = apiSafe('admin/invoice/tax_create', $t);
    if (is_array($r) && isset($r['__error'])) {
        recordError('tax/create ' . $t['name'], $r['__error']);
    } else {
        note("  created tax {$t['name']} id={$r}");
        $manifest['taxes'][] = ['name' => $t['name'], 'id' => $r];
    }
}

$existingGroups = apiSafe('admin/client/group_get_pairs', []);
if (!is_array($existingGroups) || isset($existingGroups['__error'])) {
    $existingGroups = [];
}
$groupTitles = ['Demo - Resellers', 'Demo - Late Payers', 'Demo - EU Customers'];
$groupIds = [];
foreach ($groupTitles as $title) {
    $foundId = array_search($title, $existingGroups, true);
    if ($foundId !== false) {
        note("  client group '{$title}' exists id={$foundId}");
        $groupIds[$title] = (int) $foundId;
        $manifest['client_groups'][] = ['title' => $title, 'id' => (int) $foundId, 'existing' => true];

        continue;
    }
    if ($dryRun) {
        note("  [dry] create client group {$title}");

        continue;
    }
    $r = apiSafe('admin/client/group_create', ['title' => $title]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('client/group_create ' . $title, $r['__error']);
    } else {
        note("  created client group {$title} id={$r}");
        $groupIds[$title] = (int) $r;
        $manifest['client_groups'][] = ['title' => $title, 'id' => (int) $r];
    }
}

// staff (support + billing)
$existingStaff = fetchAll('admin/staff/get_list');
$staffDefs = [
    ['email' => 'demo.support@example.com', 'name' => 'Demo Support', 'group_id' => 3],
    ['email' => 'demo.billing@example.com', 'name' => 'Demo Billing', 'group_id' => 3],
];
foreach ($staffDefs as $s) {
    if (findBy($existingStaff, 'email', $s['email'])) {
        note("  staff {$s['email']} exists, skip");

        continue;
    }
    if ($dryRun) {
        note("  [dry] create staff {$s['email']}");

        continue;
    }
    $r = apiSafe('admin/staff/create', $s + ['password' => 'DemoPass123!', 'status' => 'active']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('staff/create ' . $s['email'], $r['__error']);
    } else {
        note("  created staff {$s['email']} id={$r}");
        $manifest['staff'][] = ['email' => $s['email'], 'id' => $r];
    }
}

// gateways: install ClientBalance (offline-safe), enable Custom + ClientBalance
$available = apiSafe('admin/invoice/gateway_get_available', []);
if (is_array($available) && in_array('ClientBalance', $available, true)) {
    if ($dryRun) {
        note('  [dry] install gateway ClientBalance');
    } else {
        $r = apiSafe('admin/invoice/gateway_install', ['code' => 'ClientBalance']);
        if (is_array($r) && isset($r['__error']) && !str_contains($r['__error'], 'already')) {
            recordError('gateway/install ClientBalance', $r['__error']);
        } else {
            note('  ensured gateway ClientBalance installed');
        }
    }
}
$gateways = fetchAll('admin/invoice/gateway_get_list');
$gatewayIds = [];
foreach ($gateways as $g) {
    $gatewayIds[$g['code'] ?? $g['title']] = $g['id'];
    if (in_array($g['code'] ?? '', ['Custom', 'ClientBalance'], true)) {
        if ($dryRun) {
            note("  [dry] enable gateway {$g['code']} id={$g['id']}");

            continue;
        }
        $u = apiSafe('admin/invoice/gateway_update', [
            'id' => $g['id'],
            'enabled' => 1,
            'test_mode' => 1,
            'accepted_currencies' => ['GBP', 'USD', 'EUR'],
        ]);
        if (is_array($u) && isset($u['__error'])) {
            recordError('gateway/update ' . $g['code'], $u['__error']);
        } else {
            note("  enabled gateway {$g['code']} id={$g['id']}");
        }
    }
}
$manifest['gateways'] = $gatewayIds;
$customGatewayId = $gatewayIds['Custom'] ?? reset($gatewayIds) ?: 1;

// ------------------------------------------------------- 2. catalog
note('-- catalog: categories, TLDs, server/plans, products, addons, promos');

$existingCats = apiSafe('admin/product/category_get_pairs', []);
if (!is_array($existingCats) || isset($existingCats['__error'])) {
    $existingCats = [];
}
$catIds = [];
foreach (['Demo Hosting', 'Demo Software', 'Demo Services', 'Demo Domains'] as $title) {
    $foundId = array_search($title, $existingCats, true);
    if ($foundId !== false) {
        $catIds[$title] = (int) $foundId;
        note("  category '{$title}' exists id={$foundId}");
        $manifest['product_categories'][] = ['title' => $title, 'id' => (int) $foundId, 'existing' => true];

        continue;
    }
    if ($dryRun) {
        note("  [dry] create category {$title}");

        continue;
    }
    $r = apiSafe('admin/product/category_create', ['title' => $title, 'description' => "Demo category {$title}"]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('product/category_create ' . $title, $r['__error']);
    } else {
        $catIds[$title] = (int) $r;
        note("  created category {$title} id={$r}");
        $manifest['product_categories'][] = ['title' => $title, 'id' => (int) $r];
        $existingCats[(int) $r] = $title;
    }
}

// TLDs (.com already exists on registrar 1)
$existingTlds = fetchAll('admin/servicedomain/tld_get_list');
foreach ([
    ['tld' => '.io', 'price_registration' => 39.99, 'price_renew' => 39.99, 'price_transfer' => 39.99],
    ['tld' => '.dev', 'price_registration' => 14.99, 'price_renew' => 14.99, 'price_transfer' => 14.99],
    ['tld' => '.org', 'price_registration' => 12.49, 'price_renew' => 12.49, 'price_transfer' => 12.49],
] as $t) {
    if (findBy($existingTlds, 'tld', $t['tld'])) {
        note("  TLD {$t['tld']} exists, skip");
        $manifest['tlds'][] = $t['tld'] . ' (existing)';

        continue;
    }
    if ($dryRun) {
        note("  [dry] create TLD {$t['tld']}");

        continue;
    }
    $r = apiSafe('admin/servicedomain/tld_create', $t + ['tld_registrar_id' => 1]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('tld/create ' . $t['tld'], $r['__error']);
    } else {
        note("  created TLD {$t['tld']}");
        $manifest['tlds'][] = $t['tld'];
    }
}

// hosting server + plans (Custom manager needs no credentials)
$servers = fetchAll('admin/servicehosting/server_get_list');
$serverId = null;
foreach ($servers as $s) {
    if (($s['name'] ?? '') === 'Demo Server') {
        $serverId = (int) $s['id'];
    }
}
if ($serverId === null && !$dryRun) {
    $r = apiSafe('admin/servicehosting/server_create', [
        'name' => 'Demo Server', 'hostname' => 'demo.example.com',
        'ip' => '192.0.2.10', 'manager' => 'Custom', 'active' => 1,
    ]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('servicehosting/server_create', $r['__error']);
    } else {
        $serverId = (int) $r;
        note("  created hosting server id={$serverId}");
    }
} elseif ($serverId !== null) {
    note("  hosting server exists id={$serverId}");
}
$manifest['hosting_server'] = $serverId;

$plans = fetchAll('admin/servicehosting/hp_get_list');
$planIds = [];
foreach (['Demo Shared Plan', 'Demo VPS Plan'] as $pname) {
    $found = findBy($plans, 'name', $pname);
    if ($found) {
        $planIds[$pname] = (int) $found['id'];
        note("  hosting plan '{$pname}' exists");

        continue;
    }
    if ($dryRun) {
        note("  [dry] create hosting plan {$pname}");

        continue;
    }
    $r = apiSafe('admin/servicehosting/hp_create', ['name' => $pname]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('hp/create ' . $pname, $r['__error']);
    } else {
        $planIds[$pname] = (int) $r;
        note("  created hosting plan {$pname} id={$r}");
    }
}
$manifest['hosting_plans'] = $planIds;

// products
$existingProducts = fetchAll('admin/product/get_list');
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
        note("  product '{$def['title']}' exists id={$productIds[$def['title']]}");
        $manifest['products'][] = ['title' => $def['title'], 'id' => $productIds[$def['title']], 'existing' => true];

        continue;
    }
    if ($dryRun) {
        note("  [dry] create product {$def['title']}");

        continue;
    }
    $pid = apiSafe('admin/product/prepare', ['title' => $def['title'], 'type' => $def['type'], 'product_category_id' => $catIds[$def['cat']] ?? null]);
    if (is_array($pid) && isset($pid['__error'])) {
        recordError('product/prepare ' . $def['title'], $pid['__error']);

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
    $u = apiSafe('admin/product/update', $upd);
    if (is_array($u) && isset($u['__error'])) {
        recordError('product/update ' . $def['title'], $u['__error']);

        continue;
    }
    note("  created product {$def['title']} id={$pid}");
    $productIds[$def['title']] = $pid;
    $manifest['products'][] = ['title' => $def['title'], 'type' => $def['type'], 'id' => $pid];
}
// domain product already exists (only one allowed) — reuse it
if (!isset($productIds['Domains registration and transfer'])) {
    foreach ($existingProducts as $p) {
        if (($p['type'] ?? '') === 'domain') {
            $productIds[$p['title']] = (int) $p['id'];
            $manifest['products'][] = ['title' => $p['title'], 'type' => 'domain', 'id' => (int) $p['id'], 'existing' => true];
        }
    }
}

// addons
$existingAddons = apiSafe('admin/product/addon_get_pairs', []);
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
        note("  addon '{$a['title']}' exists");
        $manifest['addons'][] = ['title' => $a['title'], 'id' => (int) $foundId, 'existing' => true];

        continue;
    }
    if ($dryRun) {
        note("  [dry] create addon {$a['title']}");

        continue;
    }
    $r = apiSafe('admin/product/addon_create', ['title' => $a['title'], 'status' => 'enabled', 'description' => 'Demo addon for testing']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('addon/create ' . $a['title'], $r['__error']);

        continue;
    }
    $u = apiSafe('admin/product/addon_update', ['id' => (int) $r, 'pricing' => $a['pricing'], 'status' => 'enabled']);
    if (is_array($u) && isset($u['__error'])) {
        recordError('addon/update ' . $a['title'], $u['__error']);
    }
    note("  created addon {$a['title']} id={$r}");
    $manifest['addons'][] = ['title' => $a['title'], 'id' => (int) $r];
}

// promos
$existingPromos = fetchAll('admin/product/promo_get_list');
foreach ([
    ['code' => 'DEMO10', 'type' => 'percentage', 'value' => 10, 'recurring' => 1, 'active' => 1, 'maxuses' => 100],
    ['code' => 'WELCOME5', 'type' => 'absolute', 'value' => 5, 'once_per_client' => 1, 'active' => 1, 'maxuses' => 100],
] as $promo) {
    if (findBy($existingPromos, 'code', $promo['code'])) {
        note("  promo {$promo['code']} exists, skip");

        continue;
    }
    if ($dryRun) {
        note("  [dry] create promo {$promo['code']}");

        continue;
    }
    $r = apiSafe('admin/product/promo_create', $promo);
    if (is_array($r) && isset($r['__error'])) {
        recordError('promo/create ' . $promo['code'], $r['__error']);
    } else {
        note("  created promo {$promo['code']} id={$r}");
        $manifest['promos'][] = ['code' => $promo['code'], 'id' => $r];
    }
}

// ------------------------------------------------------- 3. clients
note('-- clients (24 demo clients)');
$firstNames = ['Ava', 'Liam', 'Mia', 'Noah', 'Emma', 'Oliver', 'Sophia', 'Ethan', 'Isabella', 'Mason', 'Lucas', 'Amelia', 'James', 'Harper', 'Benjamin', 'Evelyn', 'Alexander', 'Charlotte', 'Daniel', 'Grace', 'Henry', 'Chloe', 'Samuel', 'Lily'];
$lastNames = ['Smith', 'Novak', 'Garcia', 'Muller', 'Kowalski', 'Dubois', 'Rossi', 'Silva', 'Tanaka', 'Murphy', 'Johansson', 'Costa', 'Weber', 'Fontaine', 'Khan', 'Petrov', 'Okafor', 'Larsen', 'Moreau', 'Bakker', 'Singh', 'Novakova', 'Brown', 'Davis'];
$countries = ['GB', 'US', 'DE', 'FR', 'PL', 'IE', 'NL', 'ES', 'IT', 'CA', 'IN', 'BR'];
$currCycle = ['GBP', 'USD', 'EUR'];
$groupCycle = array_values($groupIds);
if ($groupCycle === []) {
    $groupCycle = [null];
}
$existingClients = fetchAll('admin/client/get_list');
$clientIds = [];
foreach ($existingClients as $c) {
    $clientIds[$c['email']] = (int) $c['id'];
}
$clientCount = $limitClients > 0 ? min($limitClients, 24) : 24;
for ($i = 0; $i < $clientCount; ++$i) {
    $n = $i + 1;
    $email = sprintf('demo.client%02d@example.com', $n);
    if (isset($clientIds[$email])) {
        note("  client {$email} exists id={$clientIds[$email]}");
        $manifest['clients'][] = ['email' => $email, 'id' => $clientIds[$email], 'existing' => true];

        continue;
    }
    if ($dryRun) {
        note("  [dry] create client {$email}");

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
    $r = apiSafe('admin/client/create', $payload);
    if (is_array($r) && isset($r['__error'])) {
        recordError('client/create ' . $email, $r['__error']);

        continue;
    }
    $clientIds[$email] = (int) $r;
    note("  created client {$email} id={$r}");
    $manifest['clients'][] = ['email' => $email, 'id' => (int) $r];
}
// fund a few balances for pay_with_credits coverage
$funded = 0;
foreach (array_slice($manifest['clients'], 0, 6) as $c) {
    if ($dryRun || !empty($c['existing'])) {
        continue;
    }
    $r = apiSafe('admin/client/balance_add_funds', ['id' => $c['id'], 'amount' => 40, 'description' => 'Demo seed credit']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('balance_add_funds client ' . $c['id'], $r['__error']);
    } else {
        ++$funded;
    }
}
note("  funded {$funded} client balances");

// ------------------------------------------------------- 4. orders
note('-- orders (mixed states)');
$orderProductCycle = [];
foreach ($existingProducts as $p) {
    if (in_array($p['type'] ?? '', ['domain', 'downloadable'], true)) {
        continue; // domain handled via dedicated $domainPid orders; downloadable needs files config, catalog-only
    }
    $orderProductCycle[] = (int) $p['id'];
}
foreach ($manifest['products'] as $p) {
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
    foreach ($manifest['products'] as $p) {
        if (($p['type'] ?? '') === 'domain') {
            $domainPid = (int) $p['id'];
        }
    }
}
$periodCycle = ['1M', '1Y', '1M', '3M', '1Y', '1M'];
$clientIdList = array_values($clientIds);
$orderTarget = $limitOrders > 0 ? $limitOrders : 36;
$ordersCreated = [];
$orderIndex = 0;
foreach ($clientIdList as $ci => $cid) {
    if (count($ordersCreated) >= $orderTarget) {
        break;
    }
    $rounds = $ci < 12 ? 2 : 1; // first 12 clients get 2 orders => ~36 total
    for ($k = 0; $k < $rounds && count($ordersCreated) < $orderTarget; ++$k) {
        ++$orderIndex;
        $pid = $orderProductCycle[($orderIndex - 1) % max(1, count($orderProductCycle))];
        $period = $periodCycle[($orderIndex - 1) % count($periodCycle)];
        $title = "Demo order #{$orderIndex}";
        // domain orders need special config; force every 6th order to be a domain
        $isDomain = $domainPid !== null && ($orderIndex % 6 === 0);
        if ($isDomain) {
            $pid = $domainPid;
        }
        if ($dryRun) {
            note("  [dry] create order client={$cid} product={$pid}");

            continue;
        }

        // probe type + pricing first so period/config are always valid for this product
        try {
            $probe = api('admin/product/get', ['id' => $pid]);
            $productType = $probe['type'] ?? '';
            $productPricing = $probe['pricing'] ?? [];
        } catch (Throwable) {
            $productType = '';
            $productPricing = [];
        }
        if ($productType === 'domain') {
            // generic cycle should never contain domains, but coerce just in case
            $isDomain = true;
            $pid = $domainPid ?? $pid;
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
            $oid = api('admin/order/create', $payload);
            $oid = (int) $oid;
            note("  created order id={$oid} client={$cid} product={$pid}" . ($isDomain ? ' (domain)' : ''));
            $ordersCreated[] = $oid;
            $manifest['orders'][] = ['id' => $oid, 'client_id' => $cid, 'product_id' => $pid];
        } catch (Throwable $e) {
            recordError("order/create client={$cid} product={$pid}", $e->getMessage());
        }
    }
}

// distribute order states (tolerant: check status first, best-effort transitions)
foreach ($ordersCreated as $idx => $oid) {
    if ($dryRun) {
        continue;
    }
    $mod = $idx % 10;

    try {
        $orderInfo = api('admin/order/get', ['id' => $oid]);
        $orderStatus = $orderInfo['status'] ?? '';
    } catch (Throwable) {
        $orderStatus = '';
    }
    $canActivate = in_array($orderStatus, ['pending_setup', 'failed_setup'], true);
    $isActive = $orderStatus === 'active';

    try {
        if ($mod <= 3 || $mod === 7 || $mod === 8) {
            if ($canActivate) {
                api('admin/order/activate', ['id' => $oid, 'force' => 0]);
                $isActive = true;
            }
            if ($mod === 8 && $isActive) {
                // leave one activated then suspended+unsuspended to exercise both
                api('admin/order/suspend', ['id' => $oid, 'reason' => 'Demo seed suspension test']);
                api('admin/order/unsuspend', ['id' => $oid]);
            }
        } elseif ($mod === 5) {
            if ($canActivate) {
                api('admin/order/activate', ['id' => $oid]);
                $isActive = true;
            }
            if ($isActive) {
                api('admin/order/suspend', ['id' => $oid, 'reason' => 'Demo seed: overdue suspension']);
            }
        } elseif ($mod === 6) {
            if ($orderStatus !== 'canceled') {
                if ($canActivate) {
                    try {
                        api('admin/order/activate', ['id' => $oid]);
                    } catch (Throwable) {
                        // fall through to cancel attempt
                    }
                }

                try {
                    api('admin/order/cancel', ['id' => $oid, 'reason' => 'Demo seed: client request']);
                } catch (Throwable $e) {
                    // pending_setup orders cannot be canceled; leave as-is for pending coverage
                    recordError("order/cancel id={$oid} (status={$orderStatus})", $e->getMessage());
                }
            }
        }
        // 4, 9: leave as-is (pending coverage)
    } catch (Throwable $e) {
        recordError("order/state id={$oid} (status={$orderStatus})", $e->getMessage());
    }
}
note('  order states distributed (active/pending/suspended/canceled)');

// ------------------------------------------------------- 5. invoices
note('-- invoices: manual + lifecycle (paid/credit/refund/subscription)');
$manualInvoiceIds = [];
$manualClients = array_slice(array_values($clientIds), 0, 4);
foreach ($manualClients as $mi => $cid) {
    if ($dryRun) {
        note("  [dry] manual invoice for client {$cid}");

        continue;
    }

    try {
        $iid = api('admin/invoice/prepare', [
            'client_id' => $cid,
            'gateway_id' => $customGatewayId,
            'items' => [
                ['title' => 'Demo manual line - setup', 'price' => 25 + $mi, 'quantity' => 1],
                ['title' => 'Demo manual line - service', 'price' => 10, 'quantity' => 2],
            ],
        ]);
        api('admin/invoice/approve', ['id' => (int) $iid]);
        note("  manual invoice id={$iid} client={$cid}");
        $manualInvoiceIds[] = (int) $iid;
        $manifest['invoices_manual'][] = ['id' => (int) $iid, 'client_id' => $cid];
    } catch (Throwable $e) {
        recordError('invoice/manual client=' . $cid, $e->getMessage());
    }
}

// collect order invoices
$invoiceIds = [];
foreach ($ordersCreated as $oid) {
    try {
        $o = api('admin/order/get', ['id' => $oid]);
        foreach (['invoice_id', 'unpaid_invoice_id'] as $k) {
            if (!empty($o[$k]) && !in_array((int) $o[$k], $invoiceIds, true)) {
                $invoiceIds[] = (int) $o[$k];
            }
        }
    } catch (Throwable $e) {
        recordError('order/get invoice id=' . $oid, $e->getMessage());
    }
}
$allInvoices = array_values(array_unique(array_merge($invoiceIds, $manualInvoiceIds)));
note('  found ' . count($allInvoices) . ' invoices from orders+manual');

// mark ~half paid, create transactions, refund one, credits for two
// Custom gateway requires transactionId on mark_as_paid; prefer ClientBalance (no txn id needed).
$payGatewayId = $gatewayIds['ClientBalance'] ?? $customGatewayId;
$payNeedsTxnId = !isset($gatewayIds['ClientBalance']);
$paid = 0;
foreach ($allInvoices as $ii => $iid) {
    if ($dryRun) {
        continue;
    }
    $slot = $ii % 10;

    try {
        if ($slot <= 4) {
            $markPayload = ['id' => $iid, 'gateway_id' => $payGatewayId, 'execute' => 1];
            if ($payNeedsTxnId) {
                $markPayload['transactionId'] = 'DEMO-TXN-' . $iid;
            }
            api('admin/invoice/mark_as_paid', $markPayload);
            ++$paid;
            // NOTE: pass unique get/post/server so the IPN-hash dedupe treats each as distinct.
            $txn = apiSafe('admin/invoice/transaction_create', [
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
                recordError('transaction/create invoice=' . $iid, $txn['__error']);
            } else {
                $manifest['transactions'][] = ['id' => $txn, 'invoice_id' => $iid];
            }
        } elseif ($slot === 5) {
            // leave unpaid (overdue coverage)
        } elseif ($slot === 6) {
            apiSafe('admin/invoice/pay_with_credits', ['id' => $iid]);
        } elseif ($slot === 7) {
            $t = apiSafe('admin/invoice/transaction_create', [
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
            if (!(is_array($t) && isset($t['__error']))) {
                $manifest['transactions'][] = ['id' => $t, 'invoice_id' => $iid, 'status' => 'pending'];
            }
        }
    } catch (Throwable $e) {
        recordError('invoice/lifecycle id=' . $iid, $e->getMessage());
    }
}
note("  marked ~{$paid} invoices paid + transactions");
// one refund on first paid invoice
$firstPaid = $manifest['transactions'][0]['invoice_id'] ?? null;
if ($firstPaid !== null && !$dryRun) {
    $r = apiSafe('admin/invoice/refund', ['id' => $firstPaid, 'note' => 'Demo seed refund test']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('invoice/refund id=' . $firstPaid, $r['__error']);
    } else {
        note("  refunded invoice {$firstPaid}");
    }
}
// one subscription (skip if DEMO-SUB-001 already exists)
$subClient = array_values($clientIds)[0] ?? null;
if ($subClient !== null && !$dryRun) {
    $existingSubs = fetchAll('admin/invoice/subscription_get_list');
    if (findBy($existingSubs, 'sid', 'DEMO-SUB-001')) {
        note('  subscription DEMO-SUB-001 exists, skip');
    } else {
        try {
            $clientInfo = api('admin/client/get', ['id' => $subClient]);
            $cur = $clientInfo['currency'] ?? 'GBP';
        } catch (Throwable) {
            $cur = 'GBP';
        }
        $s = apiSafe('admin/invoice/subscription_create', [
            'client_id' => $subClient,
            'gateway_id' => $customGatewayId,
            'currency' => $cur,
            'amount' => 9.99,
            'period' => '1M',
            'status' => 'active',
            'sid' => 'DEMO-SUB-001',
        ]);
        if (is_array($s) && isset($s['__error'])) {
            recordError('subscription/create', $s['__error']);
        } else {
            note("  created subscription id={$s}");
            $manifest['subscriptions'][] = ['id' => $s, 'client_id' => $subClient];
        }
    }
}

// ------------------------------------------------------- 6. support
note('-- support: helpdesks, canned, KB, tickets');
$helpdesks = fetchAll('admin/support/helpdesk_get_list');
$helpdeskIds = [];
foreach ($helpdesks as $h) {
    $helpdeskIds[$h['name']] = (int) $h['id'];
}
foreach (['Demo Billing' => 'billing@example.com', 'Demo Technical' => 'tech@example.com'] as $hname => $helpdeskEmail) {
    if (isset($helpdeskIds[$hname])) {
        continue;
    }
    if ($dryRun) {
        note("  [dry] create helpdesk {$hname}");

        continue;
    }
    $r = apiSafe('admin/support/helpdesk_create', ['name' => $hname, 'email' => $helpdeskEmail, 'close_after' => 72, 'signature' => 'Demo support team']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('helpdesk/create ' . $hname, $r['__error']);
    } else {
        $helpdeskIds[$hname] = (int) $r;
        note("  created helpdesk {$hname} id={$r}");
    }
}
$manifest['helpdesks'] = $helpdeskIds;
$defaultHelpdeskId = reset($helpdeskIds) ?: 1;
// canned categories + responses
$cannedPairs = apiSafe('admin/support/canned_category_pairs', []);
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
    if ($dryRun) {
        note("  [dry] canned category {$ct}");

        continue;
    }
    $r = apiSafe('admin/support/canned_category_create', ['title' => $ct]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('canned_category/create ' . $ct, $r['__error']);
    } else {
        $cannedCatIds[$ct] = (int) $r;
        note("  created canned category {$ct}");
    }
}
$manifest['canned_categories'] = $cannedCatIds;
$existingCanned = fetchAll('admin/support/canned_get_list');
$cannedDefs = [
    ['title' => 'Demo: welcome reply', 'category' => 'Demo General Replies', 'content' => 'Thanks for contacting demo support. We are looking into this.'],
    ['title' => 'Demo: billing reply', 'category' => 'Demo Billing Replies', 'content' => 'Your demo invoice has been reviewed. Let us know if anything looks off.'],
    ['title' => 'Demo: escalation reply', 'category' => 'Demo General Replies', 'content' => 'Escalating this demo ticket to a senior agent.'],
];
foreach ($cannedDefs as $cd) {
    if (findBy($existingCanned, 'title', $cd['title'])) {
        note("  canned '{$cd['title']}' exists, skip");

        continue;
    }
    if ($dryRun) {
        note("  [dry] canned {$cd['title']}");

        continue;
    }
    $r = apiSafe('admin/support/canned_create', ['title' => $cd['title'], 'category_id' => $cannedCatIds[$cd['category']] ?? reset($cannedCatIds), 'content' => $cd['content']]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('canned/create ' . $cd['title'], $r['__error']);
    } else {
        $manifest['canned'][] = ['title' => $cd['title'], 'id' => $r];
    }
}

// KB categories + articles
$kbCats = apiSafe('admin/support/kb_category_get_pairs', []);
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
    if ($dryRun) {
        note("  [dry] KB category {$kt}");

        continue;
    }
    $r = apiSafe('admin/support/kb_category_create', ['title' => $kt, 'description' => $kd]);
    if (is_array($r) && isset($r['__error'])) {
        recordError('kb_category/create ' . $kt, $r['__error']);
    } else {
        $kbCatIds[$kt] = (int) $r;
        note("  created KB category {$kt}");
    }
}
$manifest['kb_categories'] = $kbCatIds;
$existingKb = fetchAll('admin/support/kb_article_get_list');
$kbArticles = [
    ['title' => 'Demo: How to pay your first invoice', 'cat' => 'Demo Billing FAQ', 'content' => 'Open **Invoices**, pick the unpaid demo invoice, and use the Custom gateway.'],
    ['title' => 'Demo: How to open a ticket', 'cat' => 'Demo Getting Started', 'content' => 'Go to **Support**, choose a helpdesk, and describe the issue.'],
    ['title' => 'Demo: Understanding order statuses', 'cat' => 'Demo Getting Started', 'content' => 'Orders move from pending to active, and can be suspended or canceled.'],
    ['title' => 'Demo: Using promo code DEMO10', 'cat' => 'Demo Billing FAQ', 'content' => 'Apply `DEMO10` at checkout for 10% off demo products.'],
    ['title' => 'Demo: Managing your profile', 'cat' => 'Demo Getting Started', 'content' => 'Update company, VAT, and notification email from the client profile.'],
];
foreach ($kbArticles as $kb) {
    if (findBy($existingKb, 'title', $kb['title'])) {
        note("  KB '{$kb['title']}' exists, skip");

        continue;
    }
    if ($dryRun) {
        note("  [dry] KB article {$kb['title']}");

        continue;
    }
    $r = apiSafe('admin/support/kb_article_create', ['kb_article_category_id' => $kbCatIds[$kb['cat']] ?? reset($kbCatIds), 'title' => $kb['title'], 'content' => $kb['content'], 'status' => 'active']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('kb_article/create ' . $kb['title'], $r['__error']);
    } else {
        $manifest['kb_articles'][] = ['title' => $kb['title'], 'id' => $r];
        note("  created KB article {$kb['title']}");
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
$ticketTarget = $limitTickets > 0 ? $limitTickets : 20;
$helpdeskKeys = array_values($helpdeskIds);
if ($helpdeskKeys === []) {
    $helpdeskKeys = [$defaultHelpdeskId];
}
for ($t = 0; $t < $ticketTarget; ++$t) {
    $subj = 'Demo ticket #' . ($t + 1) . ' - ' . $subjects[$t % count($subjects)];
    if ($dryRun) {
        note("  [dry] ticket {$subj}");

        continue;
    }
    $cid = $clientIdList[$t % max(1, count($clientIdList))];

    try {
        $tid = api('admin/support/ticket_create', [
            'client_id' => $cid,
            'support_helpdesk_id' => $helpdeskKeys[$t % max(1, count($helpdeskKeys))],
            'subject' => $subj,
            'content' => $bodies[$t % count($bodies)],
        ]);
        $tid = (int) $tid;
        $manifest['tickets'][] = ['id' => $tid, 'subject' => $subj, 'client_id' => $cid];
        // admin reply on most, second reply on some, close ~1/3
        if ($t % 4 !== 3) {
            api('admin/support/ticket_reply', ['id' => $tid, 'content' => 'Demo admin reply: thanks, we are on it.']);
        }
        if ($t % 5 === 0) {
            api('admin/support/ticket_reply', ['id' => $tid, 'content' => 'Demo follow-up: anything else we can test for you?']);
        }
        if ($t % 2 === 0) {
            apiSafe('admin/support/note_create', ['ticket_id' => $tid, 'note' => 'Demo internal note.']);
        }
        if ($t % 3 === 0) {
            api('admin/support/ticket_close', ['id' => $tid]);
        }
        if ($t === 1) {
            apiSafe('admin/support/task_complete', ['id' => $tid]);
        }
        note("  ticket id={$tid} {$subj}");
    } catch (Throwable $e) {
        recordError('ticket/create ' . $subj, $e->getMessage());
    }
}

// ------------------------------------------------------- 7. news
note('-- news');
$existingNews = fetchAll('admin/news/get_list');
foreach ([
    ['title' => 'Demo: scheduled maintenance window', 'content' => 'Demo maintenance notice for testing the news block. No action needed.'],
    ['title' => 'Demo: new hosting plans available', 'content' => 'Try the demo shared and VPS plans seeded with this dataset.'],
    ['title' => 'Demo: promo code DEMO10', 'content' => 'Use DEMO10 on demo orders while testing checkout.'],
] as $nw) {
    if (findBy($existingNews, 'title', $nw['title'])) {
        note("  news '{$nw['title']}' exists, skip");

        continue;
    }
    if ($dryRun) {
        note("  [dry] news {$nw['title']}");

        continue;
    }
    $r = apiSafe('admin/news/create', $nw + ['status' => 'active']);
    if (is_array($r) && isset($r['__error'])) {
        recordError('news/create ' . $nw['title'], $r['__error']);
    } else {
        $manifest['news'][] = ['title' => $nw['title'], 'id' => $r];
        note("  news {$nw['title']} id={$r}");
    }
}

// ------------------------------------------------------- manifest
$manifestPath = __DIR__ . ($dryRun ? '/manifest.dry.json' : '/manifest.json');
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
note('Wrote manifest: ' . $manifestPath);
note('Done. Errors: ' . count($manifest['errors']));
exit(count($manifest['errors']) > 20 ? 1 : 0);

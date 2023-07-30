<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';
$di['translate']();

$bb_invoice_id = $_POST['bb_invoice_id'] ?? $_GET['bb_invoice_id'] ?? null;

$bb_gateway_id = $_POST['bb_gateway_id'] ?? $_GET['bb_gateway_id'] ?? null;

$ipn = [
    'skip_validation' => true,
    'bb_invoice_id' => $bb_invoice_id,
    'bb_gateway_id' => $bb_gateway_id,
    'get' => $_GET,
    'post' => $_POST,
    'server' => $_SERVER,
    'http_raw_post_data' => file_get_contents('php://input'),
];

try {
    $service = $di['mod_service']('invoice', 'transaction');
    $output = $service->createAndProcess($ipn);
    $res = ['result' => $output, 'error' => null];
} catch (Exception $e) {
    $res = ['result' => null, 'error' => ['message' => $e->getMessage()]];
    $output = false;
}

// redirect to invoice if gateways requires
if (isset($_GET['bb_redirect'], $_GET['bb_invoice_hash'])) {
    $url = $di['url']->link('invoice/' . $_GET['bb_invoice_hash']);
    header("Location: $url");
    exit;
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');
echo json_encode($res);
exit;

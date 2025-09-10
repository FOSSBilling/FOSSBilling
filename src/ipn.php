<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'load.php';

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

$di = include Path::join(PATH_ROOT, 'di.php');
$di['translate']();

$filesystem = new Filesystem();

$invoiceID = $_POST['invoice_id'] ?? $_GET['invoice_id'] ?? $_POST['bb_invoice_id'] ?? $_GET['bb_invoice_id'] ?? null;

$gatewayID = $_POST['gateway_id'] ?? $_GET['gateway_id'] ?? $_POST['bb_gateway_id'] ?? $_GET['bb_gateway_id'] ?? null;

$_GET['bb_invoice_id'] = $invoiceID;
$_GET['bb_gateway_id'] = $gatewayID;

$ipn = [
    'skip_validation' => true,
    'invoice_id' => $invoiceID,
    'gateway_id' => $gatewayID,
    'get' => $_GET,
    'post' => $_POST,
    'server' => $_SERVER,
    'http_raw_post_data' => $filesystem->readFile('php://input'),
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
if (isset($_GET['redirect'], $_GET['invoice_hash']) || isset($_GET['bb_redirect'], $_GET['bb_invoice_hash'])) {
    $hash = $_GET['invoice_hash'] ?? $_GET['bb_invoice_hash'];
    $url = $di['url']->link('invoice/' . $hash);
    header("Location: $url");
    exit;
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');
echo json_encode($res);
exit;

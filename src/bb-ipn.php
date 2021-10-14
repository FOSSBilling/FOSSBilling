<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

require_once dirname(__FILE__) . '/bb-load.php';
$di = include dirname(__FILE__) . '/bb-di.php';
$di['translate']();

$bb_invoice_id = null;
if(isset($_GET['bb_invoice_id'])) {
    $bb_invoice_id = $_GET['bb_invoice_id'];
}
if(isset($_POST['bb_invoice_id'])) {
    $bb_invoice_id = $_POST['bb_invoice_id'];
}

$bb_gateway_id = null;
if(isset($_GET['bb_gateway_id'])) {
    $bb_gateway_id = $_GET['bb_gateway_id'];
}
if(isset($_POST['bb_gateway_id'])) {
    $bb_gateway_id = $_POST['bb_gateway_id'];
}

$ipn = array(
    'skip_validation'       =>  true,
    'bb_invoice_id'         =>  $bb_invoice_id,
    'bb_gateway_id'         =>  $bb_gateway_id,
    'get'                   =>  $_GET,
    'post'                  =>  $_POST,
    'server'                =>  $_SERVER,
    'http_raw_post_data'    =>  file_get_contents("php://input"),
);

try {
    $service = $di['mod_service']('invoice', 'transaction');
    $output = $service->createAndProcess($ipn);
    $res = array('result'=>$output, 'error'=>null);
} catch(Exception $e) {
    $res = array('result'=>null, 'error'=> array('message' => $e->getMessage()));
    $output = false;
}

// redirect to invoice if gateways requires
if(isset($_GET['bb_redirect']) && isset($_GET['bb_invoice_hash'])) {
    $url = $di['url']->link('invoice/'.$_GET['bb_invoice_hash']);
    header("Location: $url");
    exit;
} else {
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Content-type: application/json; charset=utf-8');
    print json_encode($res);
    exit;
}
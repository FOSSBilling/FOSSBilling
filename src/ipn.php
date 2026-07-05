<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'load.php';

use FOSSBilling\Http\ApiResponseFactory;

use FOSSBilling\Http\ResponseFactory;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;

/* @var Symfony\Component\HttpFoundation\Request $request */
global $request;

$di = include Path::join(PATH_ROOT, 'di.php');
$di['translate']();
$apiResponseFactory = new ApiResponseFactory();

$invoiceID = $request->get('invoice_id');
if ($invoiceID !== null) {
    $invoiceID = filter_var($invoiceID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($invoiceID === false) {
        emitResponse(new JsonResponse(['result' => null, 'error' => ['message' => 'Invalid invoice ID']], 400));
    }
}

$gatewayID = $request->get('gateway_id');

if ($gatewayID !== null) {
    $gatewayID = filter_var($gatewayID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($gatewayID === false) {
        emitResponse(new JsonResponse(['result' => null, 'error' => ['message' => 'Invalid gateway ID']], 400));
    }
}

$rawBody = $request->getContent();

$ipn = [
    'invoice_id' => $invoiceID,
    'gateway_id' => $gatewayID,
    'source' => 'ipn',
    'get' => $request->query->all(),
    'post' => $request->request->all(),
    'server' => $request->server->all(),
    'http_raw_post_data' => $rawBody,
];

$contentType = $request->headers->get('Content-Type', '');
$isJsonWebhook = str_contains((string) $contentType, 'application/json') && !empty($rawBody);
if ($isJsonWebhook) {
    $ipn['skip_validation'] = true;
}

try {
    $service = $di['mod_service']('invoice', 'transaction');

    // JSON webhooks (Stripe, etc.) require fast 2xx acknowledgment.
    // When running under FastCGI, decouple the HTTP response from processing:
    // create the transaction, send 200, then finish in the background via
    // fastcgi_finish_request().
    if ($isJsonWebhook && function_exists('fastcgi_finish_request')) {
        $transactionId = $service->create($ipn);
        $response = $apiResponseFactory->create($transactionId);
        $response->prepare($request)->send();
        fastcgi_finish_request();

        // Process in the background — errors are logged on the transaction.
        $service->processAndCatchErrors((int) $transactionId);
        exit;
    }

    $output = $service->createAndProcess($ipn);
    $response = $apiResponseFactory->create($output);
} catch (Exception $e) {
    $response = $apiResponseFactory->create(null, $e);
}

// redirect to invoice if gateways requires
if ($request->query->has('redirect') && $request->query->has('invoice_hash')) {
    $invoiceHash = $request->query->get('invoice_hash');
    $hash = preg_replace('/[^a-zA-Z0-9]/', '', is_string($invoiceHash) ? $invoiceHash : '');
    $url = $di['url']->link('invoice/' . $hash);
    emitResponse((new ResponseFactory())->redirect($url));
}

emitResponse($response);

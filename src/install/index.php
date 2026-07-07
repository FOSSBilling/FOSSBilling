<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error: PHP version 8.3.0 or higher is required. You have version ' . PHP_VERSION;
    exit;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$request = Request::createFromGlobals();
$installPath = rtrim(dirname((string) $request->server->get('PHP_SELF', '')), '/');

$response = new RedirectResponse($installPath . '/install.php');
$response->prepare($request)->send();
exit;

<?php

declare(strict_types=1);

use FOSSBilling\Http\RequestFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    (new Response('Error: PHP version 8.3.0 or higher is required. You have version ' . PHP_VERSION, 500))->send();
    exit;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$request = RequestFactory::createFromGlobals();
$installPath = rtrim(dirname((string) $request->server->get('PHP_SELF', '')), '/');

(new RedirectResponse($installPath . '/install.php'))->send();
exit;

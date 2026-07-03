<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Http;

use FOSSBilling\Environment;
use FOSSBilling\ErrorPage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

final readonly class ExceptionResponseFactory
{
    public function create(\Throwable $exception): Response
    {
        $message = htmlspecialchars($exception->getMessage());

        if (Environment::isTesting()) {
            return new Response($this->formatTestingMessage($exception), $this->getStatusCode($exception), [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        if (defined('API_MODE')) {
            $code = $exception->getCode() ?: 9998;

            return new JsonResponse(['result' => null, 'error' => ['message' => $message, 'code' => $code]]);
        }

        if (defined('DEBUG') && DEBUG && (new Filesystem())->exists(PATH_VENDOR)) {
            return new Response($this->renderWhoops($exception), $this->getStatusCode($exception));
        }

        return new Response((new ErrorPage())->renderPage($exception->getCode(), $message), $this->getStatusCode($exception));
    }

    public function formatTestingMessage(\Throwable $exception): string
    {
        return $exception::class . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL;
    }

    private function renderWhoops(\Throwable $exception): string
    {
        $whoops = new Run();
        $prettyPage = new PrettyPageHandler();
        $prettyPage->setPageTitle('An error occurred');
        $prettyPage->addDataTable('FOSSBilling environment', [
            'PHP Version' => PHP_VERSION,
            'Error code' => $exception->getCode(),
            'Instance ID' => defined('INSTANCE_ID') ? INSTANCE_ID : 'Unknown',
        ]);
        $whoops->pushHandler($prettyPage);
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);

        return $whoops->handleException($exception);
    }

    private function getStatusCode(\Throwable $exception): int
    {
        $code = $exception->getCode();

        return $code >= 400 && $code <= 599 ? $code : Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}

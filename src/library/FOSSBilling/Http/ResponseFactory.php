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

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final readonly class ResponseFactory
{
    public function normalize(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        return new Response((string) ($result ?? ''));
    }

    public function html(string $content, int $statusCode = Response::HTTP_OK, array $headers = []): Response
    {
        $response = new Response($content, $statusCode);
        $response->headers->add($headers);

        return $response;
    }

    public function error(string $content, \Exception $exception, ?int $statusCode = null, array $headers = []): Response
    {
        $exceptionCode = $exception->getCode();
        $statusCode ??= $exceptionCode >= 100 && $exceptionCode < 600 ? $exceptionCode : Response::HTTP_INTERNAL_SERVER_ERROR;

        return $this->html($content, $statusCode, $headers);
    }

    public function redirect(string $location, int $statusCode = Response::HTTP_FOUND): RedirectResponse
    {
        return new RedirectResponse($location, $statusCode);
    }

    public function file(string $filename, string $contentType, mixed $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $contentType);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

        return $response;
    }

    public function download(string $filename, mixed $path): BinaryFileResponse
    {
        $response = $this->file($filename, 'application/octet-stream', $path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Description', 'File Transfer');

        return $response;
    }
}

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

use FOSSBilling\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentReturn
{
    public static function createResponse(Request $request, Url $url): ?Response
    {
        if (RequestFactory::getRoutePath($request) !== '/invoice/payment-return') {
            return null;
        }

        $params = $request->query->all();
        $status = $params['status'] ?? 'ok';
        $hash = $params['hash'] ?? null;
        if (
            !in_array($status, ['ok', 'cancel', 'thankyou'], true)
            || ($hash !== null && (!is_string($hash) || preg_match('/^[a-z0-9]+$/D', $hash) !== 1))
        ) {
            return new Response('Invalid payment return.', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
        }

        $path = '/invoice';
        if ($hash !== null) {
            $path = $status === 'thankyou' ? "/invoice/thank-you/$hash" : "/invoice/$hash";
        }
        $destination = $url->link($path, $status === 'thankyou' ? [] : ['status' => $status]);
        $href = htmlspecialchars($destination, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $scriptUrl = json_encode($destination, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $nonce = base64_encode(random_bytes(18));

        // Do not use Twig or resolve authentication here: both can start a session.
        // This document establishes a same-site context; the destination enforces
        // normal invoice authorization using only the browser's session cookie.
        return new Response(
            '<!doctype html><html><head><meta charset="utf-8"><title>Return to invoice</title></head><body>'
            . '<p><a href="' . $href . '">Continue to your invoice</a></p>'
            . '<script nonce="' . $nonce . '">window.location.replace(' . $scriptUrl . ');</script></body></html>',
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'Referrer-Policy' => 'no-referrer',
                'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-$nonce'; base-uri 'none'; frame-ancestors 'none'",
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}

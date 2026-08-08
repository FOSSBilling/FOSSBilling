<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Gateway\Custom;

use FOSSBilling\Extension\Contract\Payment\Checkout;
use FOSSBilling\Extension\Contract\Payment\CheckoutRequest;
use FOSSBilling\Extension\Contract\Payment\Context;
use FOSSBilling\Extension\Contract\Payment\ContextAware;
use FOSSBilling\Extension\Contract\Payment\Gateway;
use FOSSBilling\Extension\Contract\Payment\SupportsSubscriptions;

/**
 * Renders merchant-authored payment instructions instead of integrating a
 * real provider. There is nothing to talk to, so it needs no settings beyond
 * the two instruction templates and never touches the container: per-payment
 * data arrives via CheckoutRequest, and rendering the merchant's template
 * goes through the narrow Context every gateway gets.
 */
final class Custom implements Gateway, SupportsSubscriptions, ContextAware
{
    private ?Context $context = null;

    /**
     * @param array{single?: string, recurrent?: string} $settings
     */
    public function __construct(private readonly array $settings = [])
    {
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function checkout(CheckoutRequest $request): Checkout
    {
        $template = $request->subscription
            ? ($this->settings['recurrent'] ?? null)
            : ($this->settings['single'] ?? null);

        if ($template === null || $template === '') {
            return Checkout::html('"Custom" payment adapter is not fully configured.');
        }

        $vars = [
            'invoice' => [
                'id' => $request->invoice->id,
                'hash' => $request->invoice->hash,
                'currency' => $request->invoice->currency,
                'serie' => $request->invoice->serie,
                'nr' => $request->invoice->nr,
                'total' => $request->invoice->total,
                'subtotal' => $request->invoice->subtotal,
                'tax' => $request->invoice->tax,
                'lines' => $request->invoice->lines,
                'buyer_email' => $request->invoice->buyerEmail,
                'buyer_first_name' => $request->invoice->buyerFirstName,
                'buyer_last_name' => $request->invoice->buyerLastName,
            ],
        ];

        $html = $this->context?->renderTemplate($template, $vars) ?? $template;

        return Checkout::html($html);
    }
}

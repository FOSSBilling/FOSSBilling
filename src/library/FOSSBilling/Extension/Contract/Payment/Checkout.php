<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment;

use FOSSBilling\Extension\Contract\Payment\Checkout\Form;
use FOSSBilling\Extension\Contract\Payment\Checkout\Html;
use FOSSBilling\Extension\Contract\Payment\Checkout\Redirect;

/**
 * What a gateway hands back from checkout().
 *
 * A closed set: `Html`, `Redirect` and `Form` are the only subclasses, so
 * core matches on the concrete type instead of guessing at an untyped
 * return value. This replaces the `TYPE_HTML`/`TYPE_FORM`/`TYPE_API`
 * constants, `getType()`, and the `(array)` cast that used to work around
 * boxbilling#108.
 */
abstract readonly class Checkout
{
    /**
     * Render this HTML directly on the invoice payment page.
     */
    public static function html(string $html): Html
    {
        return new Html($html);
    }

    /**
     * Send the browser to an external URL to complete payment.
     */
    public static function redirect(string $url): Redirect
    {
        return new Redirect($url);
    }

    /**
     * Render an auto-submitting form that posts payment fields to the gateway.
     *
     * @param array<string, string> $fields
     */
    public static function form(string $action, array $fields, string $method = 'POST'): Form
    {
        return new Form($action, $fields, $method);
    }
}

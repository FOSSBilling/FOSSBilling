<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

use Twig\Sandbox\SecurityPolicy;

/**
 * Factory for creating sandbox security policy for email templates.
 * Restricts allowed tags, filters, functions, and methods to prevent attacks.
 */
final class EmailPolicy
{
    /**
     * Create a security policy configured for email template rendering.
     */
    public static function create(): SecurityPolicy
    {
        $tags = ['if', 'for', 'block', 'apply'];

        $filters = [
            // Twig Core - Security
            'escape', 'e', 'raw',
            // Twig Core - Utility
            'default', 'title', 'length', 'date',
            // IntlExtension
            'format_currency', 'format_date',
            // FOSSBillingExtension
            'url', 'daysleft', 'trans',
            // LegacyExtension
            'period_title',
            // MarkdownExtension
            'markdown_to_html',
        ];

        $functions = [];

        $methods = [];

        $properties = [];

        return new SecurityPolicy($tags, $filters, $methods, $properties, $functions);
    }
}

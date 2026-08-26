<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class Sanitizer
{
    public static function sanitizeContent(string $content = '', bool $allowHtml = true): string
    {
        if (empty($content)) {
            return '';
        }

        $content = str_replace("\0", '', $content);

        if (!$allowHtml) {
            return htmlspecialchars(self::sanitizePlainText($content), ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('a', ['href', 'title'])
            ->allowElement('code')
            ->allowElement('pre')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel']);

        $sanitizer = new HtmlSanitizer($config);

        return trim($sanitizer->sanitize($content));
    }

    public static function sanitizePlainText(string $content = ''): string
    {
        return trim(strip_tags(str_replace("\0", '', $content)));
    }

    public static function sanitizeMarkdownContent(string $content = ''): string
    {
        if (empty($content)) {
            return '';
        }

        return trim(str_replace("\0", '', $content));
    }
}

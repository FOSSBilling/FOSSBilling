<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
if (!function_exists('__trans')) {
    /**
     * Translate a message with optional value substitution.
     */
    function __trans(string $msgid, ?array $values = null): string
    {
        $translated = _gettext($msgid);

        return is_array($values) ? strtr($translated, $values) : $translated;
    }
}

if (!function_exists('__pluralTrans')) {
    /**
     * Translate a plural message with optional value substitution.
     */
    function __pluralTrans(string $msgid, string $msgidPlural, int $number, ?array $values = null): string
    {
        $translated = _ngettext($msgid, $msgidPlural, $number);

        return is_array($values) ? strtr($translated, $values) : $translated;
    }
}

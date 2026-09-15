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

final class CookieNames
{
    public const string SESSION = 'fossbilling_session';
    public const string CSRF = 'fossbilling_csrf';
    public const string LOCALE = 'fossbilling_locale';
    public const string TIMEZONE = 'fossbilling_timezone';

    public const string LEGACY_CSRF = 'csrf_token';
    public const string LEGACY_LOCALE = 'fb_locale';
    public const string LEGACY_BOX_LOCALE = 'BBLANG';
    public const string LEGACY_TIMEZONE = 'fb_timezone';

    private function __construct()
    {
    }
}

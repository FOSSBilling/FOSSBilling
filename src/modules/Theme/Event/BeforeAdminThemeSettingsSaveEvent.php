<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Theme\Event;

use FOSSBilling\Events\Event;

/** Dispatched before theme settings are validated and saved. */
final class BeforeAdminThemeSettingsSaveEvent extends Event
{
    /**
     * @param list<string> $settingNames
     */
    public function __construct(
        public readonly string $themeName,
        public readonly array $settingNames,
    ) {
    }
}

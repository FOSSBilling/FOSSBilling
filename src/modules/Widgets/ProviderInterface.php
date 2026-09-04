<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Widgets;

interface ProviderInterface
{
    /**
     * Get the widget definitions provided by this module.
     *
     * Each widget definition should contain:
     * - 'slot': The target slot name (e.g., 'client.theme.footer.end')
     * - 'template': The template name (without path/extension)
     * - 'priority': (optional) Render order, lower numbers render first (default: 10)
     *
     * @return array<int, array{slot: string, template: string, priority?: int}>
     */
    public function getWidgets(): array;
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig\Extension;

use Twig\Attribute\AsTwigFilter;

class LegacyExtension
{
    public function __construct(private ?\Pimple\Container $di)
    {
    }

    #[AsTwigFilter('ip_country_name')]
    public function ipCountryName(?string $ip): string
    {
        if ($ip === null) {
            return '';
        }

        try {
            $record = $this->di['geoip']->country($ip);

            return $record->name;
        } catch (\Exception $e) {
            return '';
        }
    }

    #[AsTwigFilter('library_url', isSafe: ['html'])]
    public function twig_library_url($path): string
    {
        return SYSTEM_URL . 'library/' . $path;
    }

    #[AsTwigFilter('mod_asset_url')]
    public function modAssetUrl(string $asset, string $module): string
    {
        return SYSTEM_URL . 'modules/' . ucfirst($module) . "/assets/{$asset}";
    }

    #[AsTwigFilter('period_title', isSafe: ['html'])]
    public function periodTitle(?string $period): string
    {
        return $this->di['api_guest']->system_period_title(['code' => $period]);
    }
}

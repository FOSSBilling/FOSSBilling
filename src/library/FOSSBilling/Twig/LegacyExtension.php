<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

use Twig\Attribute\AsTwigFilter;
use Twig\Environment;

class LegacyExtension
{
    protected ?\Pimple\Container $di = null;

    /**
     * @param \Pimple\Container|null $di
     */
    public function __construct(\Pimple\Container $di)
    {
        $this->di = $di;
    }

    /**
     * Get the country name for a given IP address.
     *
     * @param string $ip IP address.
     *
     * @throws \Exception If the IP address is invalid or if the GeoIP service fails.
     *
     * @return string Country name.
     */
    #[AsTwigFilter('ip_country_name')]
    public function ipCountryName(string $ip): string
    {
        $ip ??= '';

        try {
            $record = $this->di['geoip']->country($ip);

            return $record->name;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get module asset URL.
     *
     * @param string $asset Asset file name.
     * @param string $module Module name.
     *
     * @return string
     */
    #[AsTwigFilter('mod_asset_url')]
    public function modAssetUrl(string $asset, string $module): string
    {
        return SYSTEM_URL . 'modules/' . ucfirst($module) . "/assets/{$asset}";
    }

    /**
     * Get period title.
     *
     * @param \Twig\Environment $env Twig environment.
     * @param string|null $period Period code.
     *
     * @return string
     */
    #[AsTwigFilter('period_title', isSafe: ['html'], needsEnvironment: true)]
    public function periodTitle(Environment $env, ?string $period): string
    {
        $globals = $env->getGlobals();
        $apiGuest = $globals['guest'];

        return $apiGuest->system_period_title(['code' => $period]);
    }
}

<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Checks;

use FOSSBilling\Enums\SecurityCheckResultEnum;
use FOSSBilling\SecurityCheckResult;

class generalCheck implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    private int $okay = 0;
    private int $warn = 1;
    private int $fail = 2;

    public function getName(): string
    {
        return __trans('General Check');
    }

    public function getDescription(): string
    {
        return __trans('Checks general information such as the FOSSBilling configuration.');
    }

    public function performCheck(): SecurityCheckResult
    {
        $status = $this->okay;
        $message = '';

        $config = \FOSSBilling\Config::getConfig();

        /*
         * Security settings
         */
        if (!$config['security']['perform_session_fingerprinting']) {
            $message .= '- ' . __trans('Warning: Prevention against session hijacking is disabled.') . "\n";
            $status = $status <= $this->warn ? $this->warn : $status;
        }
        if (!$config['security']['force_https']) {
            $message .= '- ' . __trans('Fail: HTTPS is not enforced.') . "\n";
            $status = $status <= $this->fail ? $this->fail : $status;
        }

        /*
         * API settings
         */
        if (!$config['api']['CSRFPrevention']) {
            $message .= '- ' . __trans('Warning: CSRF prevention is not enabled for the API.') . "\n";
            $status = $status <= $this->warn ? $this->warn : $status;
        }

        /*
         * Debug mode toggles
         */
        if ($config['twig']['debug']) {
            $message .= '- ' . __trans('Warning: Debug mode is enabled for the Twig templating engine.') . "\n";
            $status = $status <= $this->warn ? $this->warn : $status;
        }
        if ($config['debug_and_monitoring']['debug']) {
            $message .= '- ' . __trans('Warning: Debug mode is enabled for your FOSSBilling installation.') . "\n";
            $status = $status <= $this->warn ? $this->warn : $status;
        }

        /*
         * Misc checks
         */
        if ($config['update_branch'] !== 'release') {
            $message .= '- ' . __trans('Warning: FOSSBilling is configured to update to non-release versions of FOSSBilling.') . "\n";
            $status = $status <= $this->warn ? $this->warn : $status;
        }
        if (\FOSSBilling\Version::isPreviewVersion()) {
            $message .= '- ' . __trans('Warning: You appear to be using a non-release version of FOSSBilling.') . "\n";
            $status = $status <= $this->warn ? $this->warn : $status;
        }

        if ($message === '') {
            $message = __trans('No detected issues.');
        }

        $result = match ($status) {
            0 => SecurityCheckResultEnum::PASS,
            1 => SecurityCheckResultEnum::WARN,
            2 => SecurityCheckResultEnum::FAIL,
        };

        return new SecurityCheckResult($result, $message);
    }
}

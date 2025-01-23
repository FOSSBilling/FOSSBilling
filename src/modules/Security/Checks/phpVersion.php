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
use Symfony\Component\HttpClient\HttpClient;

class phpVersion implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    public function getName(): string
    {
        return __trans('PHP Version Check');
    }

    public function getDescription(): string
    {
        return __trans('Checks if the PHP version FOSSBilling is running on is still receiving security support.');
    }

    public function performCheck(): SecurityCheckResult
    {
        $phpVersionString = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        try {
            $client = HttpClient::create();
            $response = $client->request('GET', 'https://php.watch/api/v1/versions');
            $data = $response->toArray();

            foreach ($data['data'] as $version) {
                if ($phpVersionString == $version['name']) {
                    if ($version['isLatestVersion']) {
                        return new SecurityCheckResult(SecurityCheckResultEnum::PASS, __trans('PHP :version: is the latest version of PHP.', [':version:' => $phpVersionString]));
                    } elseif ($version['isSecureVersion']) {
                        return new SecurityCheckResult(SecurityCheckResultEnum::PASS, __trans("PHP :version: isn't the latest, but is still supported.", [':version:' => $phpVersionString]));
                    } else {
                        return new SecurityCheckResult(SecurityCheckResultEnum::FAIL, __trans('PHP :version: is out of date and does not get security patches.', [':version:' => $phpVersionString]));
                    }
                }
            }
        } catch (\Exception) {
        }

        return new SecurityCheckResult(SecurityCheckResultEnum::FAIL, __trans('Failed to lookup PHP version status.'));
    }
}

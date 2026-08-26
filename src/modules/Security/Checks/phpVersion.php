<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Checks;

use FOSSBilling\Security\CheckResult;
use FOSSBilling\Security\CheckResultStatus;
use Pimple\Container;

class phpVersion implements \FOSSBilling\Security\CheckInterface
{
    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function getName(): string
    {
        return __trans('PHP Version Check');
    }

    public function getDescription(): string
    {
        return __trans('Checks if the PHP version FOSSBilling is running on is still receiving security support.');
    }

    public function performCheck(): CheckResult
    {
        $phpVersionString = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        try {
            $httpClient = $this->di['http_client'];
            $response = $httpClient->request('GET', 'https://php.watch/api/v1/versions');
            $data = $response->toArray();

            foreach ($data['data'] as $version) {
                if ($phpVersionString == $version['name']) {
                    if ($version['isLatestVersion']) {
                        return new CheckResult(CheckResultStatus::PASS, __trans('PHP :version: is the latest version of PHP.', [':version:' => $phpVersionString]));
                    } elseif ($version['isSecureVersion']) {
                        return new CheckResult(CheckResultStatus::PASS, __trans("PHP :version: isn't the latest, but is still supported.", [':version:' => $phpVersionString]));
                    }

                    return new CheckResult(CheckResultStatus::FAIL, __trans('PHP :version: is out of date and does not get security patches.', [':version:' => $phpVersionString]));
                }
            }
        } catch (\Exception) {
        }

        return new CheckResult(CheckResultStatus::FAIL, __trans('Failed to lookup PHP version status.'));
    }
}

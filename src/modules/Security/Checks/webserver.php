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

class webserver implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    // A list of URIs that should not respond with HTTP 200
    private array $testUris = [
        'config.php',
        'data/cache/classMap.php',
        'data/log/php_error.log',
        'vendor/autoload.php',
    ];

    public function getName(): string
    {
        return __trans('Webserver Check');
    }

    public function getDescription(): string
    {
        return __trans('Performs simple checks to validate your if webserver blocks access to sensitive files.');
    }

    public function performCheck(): SecurityCheckResult
    {
        $isOkay = true;
        $result = '';

        $client = HttpClient::create();
        foreach ($this->testUris as $uri) {
            $url = SYSTEM_URL . $uri;
            $response = $client->request('GET', $url);
            if ($response->getStatusCode() === 200) {
                $isOkay = false;
                $result .= __trans(":url: returned HTTP 200 when it shouldn't have.", [':url:' => $url]) . "\n";
            }
        }

        if ($result === '') {
            $result = __trans('All tested URLs were inaccessible.');
        }

        return new SecurityCheckResult($isOkay ? SecurityCheckResultEnum::PASS : SecurityCheckResultEnum::FAIL, $result);
    }
}

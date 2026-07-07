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

use FOSSBilling\Enums\SecurityCheckResultEnum;
use FOSSBilling\SecurityCheckResult;
use Pimple\Container;

class webserver implements \FOSSBilling\Interfaces\SecurityCheckInterface
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

    // A list of URIs that should not respond with HTTP 200
    private array $testUris = [
        'config.php',
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

        $httpClient = $this->di['http_client'];
        foreach ($this->testUris as $uri) {
            $url = SYSTEM_URL . $uri;
            $response = $httpClient->request('GET', $url);
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

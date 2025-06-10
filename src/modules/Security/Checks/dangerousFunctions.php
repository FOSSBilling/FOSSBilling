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

class dangerousFunctions implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    private array $functions = [
        'exec' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        'passthru' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        'system' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        'shell_exec' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        '``' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        'popen' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        'proc_open' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
        'pcntl_exec' => [
            'type' => SecurityCheckResultEnum::WARN,
        ],
    ];

    public function getName(): string
    {
        return __trans('Dangerous PHP functions');
    }

    public function getDescription(): string
    {
        return __trans('Checks to see if potentially dangerous PHP functions are enabled.');
    }

    public function performCheck(): SecurityCheckResult
    {
        $functionsFound = [];
        $state = SecurityCheckResultEnum::PASS;
        $result = '';

        foreach ($this->functions as $function => $properties) {
            if (function_exists($function)) {
                $functionsFound[$function] = $properties;
            }
        }

        if (count($functionsFound) === 1) {
            $result = __trans(':function: is enabled, potentially being a security concern.', [':function:' => key($functionsFound)]) . "\n";
            $state = reset($functionsFound)['type'];
        } else {
            $result = __trans("The following PHP functions are enabled, potentially being a security concern:\n");
            foreach ($functionsFound as $function => $properties) {
                if (function_exists($function)) {
                    $result .= '- ' . $function . "\n";
                    $state = $properties['type']; // Since we only have pass / warn, no additional logic is needed.
                }
            }
        }

        if ($state === SecurityCheckResultEnum::PASS) {
            return new SecurityCheckResult(SecurityCheckResultEnum::PASS, __trans('No potentially dangerous PHP functions were detected as enabled'));
        } else {
            return new SecurityCheckResult(SecurityCheckResultEnum::WARN, $result);
        }
    }
}

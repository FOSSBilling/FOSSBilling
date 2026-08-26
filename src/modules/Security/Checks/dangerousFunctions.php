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

class dangerousFunctions implements \FOSSBilling\Security\CheckInterface
{
    private array $functions = [
        'exec' => [
            'type' => CheckResultStatus::WARN,
        ],
        'passthru' => [
            'type' => CheckResultStatus::WARN,
        ],
        'system' => [
            'type' => CheckResultStatus::WARN,
        ],
        'shell_exec' => [
            'type' => CheckResultStatus::WARN,
        ],
        '``' => [
            'type' => CheckResultStatus::WARN,
        ],
        'popen' => [
            'type' => CheckResultStatus::WARN,
        ],
        'proc_open' => [
            'type' => CheckResultStatus::WARN,
        ],
        'pcntl_exec' => [
            'type' => CheckResultStatus::WARN,
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

    public function performCheck(): CheckResult
    {
        $functionsFound = [];
        $state = CheckResultStatus::PASS;
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
                $result .= '- ' . $function . "\n";
                $state = $properties['type']; // Since we only have pass / warn, no additional logic is needed.
            }
        }

        if ($state === CheckResultStatus::PASS) {
            return new CheckResult(CheckResultStatus::PASS, __trans('No potentially dangerous PHP functions were detected as enabled'));
        }

        return new CheckResult(CheckResultStatus::WARN, $result);
    }
}

<?php
/**
 * Copyright 2022-2024 FOSSBilling
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

class dangerousFunctions implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    private array $functions = [
        'exec' => [
            'type' => 'warn',
            'msg' => 'exec is enabled and can be used to execute commands on the host server.'
        ],
        'passthru' => [
            'type' => 'warn',
            'msg' => 'passthru is enabled and can be used to execute commands on the host server.'
        ],
        'system' => [
            'type' => 'warn',
            'msg' => 'system is enabled and can be used to execute commands on the host server.'
        ],
        'shell_exec' => [
            'type' => 'warn',
            'msg' => 'shell_exec is enabled and can be used to execute commands on the host server.'
        ],
        '``' => [
            'type' => 'warn',
            'msg' => '`` is enabled and can be used to execute commands on the host server.'
        ],
        'popen' => [
            'type' => 'warn',
            'msg' => 'popen is enabled and can be used to execute commands on the host server.'
        ],
        'proc_open' => [
            'type' => 'warn',
            'msg' => 'proc_open is enabled and can be used to execute commands on the host server.'
        ],
        'pcntl_exec' => [
            'type' => 'warn',
            'msg' => 'pcntl_exec is enabled and can be used to execute commands on the host server.'
        ]
    ];

    public function getName(): string
    {
        return 'Dangerous PHP functions';
    }

    public function getDescription(): string
    {
        return 'Checks to see if potentially dangerous PHP functions are enabled or not.';
    }

    public function performCheck(): SecurityCheckResult
    {
        $state = 'pass';
        $result = '';
        foreach ($this->functions as $function => $properties){
            if(function_exists($function)){
                $result .= $properties['msg'];
                $state = $properties['type']; // Since we only have pass / warn, no additional logic is needed.
            }
        }

        if($state === 'pass'){
            return new SecurityCheckResult(SecurityCheckResultEnum::PASS, 'No potentially dangerous PHP functions were detected as enabled');
        } else {
            return new SecurityCheckResult(SecurityCheckResultEnum::WARN, $result);
        }
    }
}
 
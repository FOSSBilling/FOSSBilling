<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Validation;

use FOSSBilling\Core\Exception\InformationException;

final class RequiredValidator
{
    public static function checkRequiredParamsForArray(array $required, array $data, ?array $variables = null, int $code = 0): void
    {
        foreach ($required as $key => $msg) {
            if (!isset($data[$key])) {
                throw new InformationException($msg, $variables, $code);
            }

            if (is_string($data[$key]) && strlen(trim($data[$key])) === 0) {
                throw new InformationException($msg, $variables, $code);
            }

            if (!is_numeric($data[$key]) && empty($data[$key])) {
                throw new InformationException($msg, $variables, $code);
            }
        }
    }
}

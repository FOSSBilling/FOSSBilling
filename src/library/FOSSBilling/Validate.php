<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Validate
{
    /**
     * Check if second level domain (SLD) is valid.
     */
    public function isSldValid(string $sld) : bool
    {
        // allow punnycode
        if(str_starts_with($sld, 'xn--')) {
            return true;
        }

        if(preg_match('/^[a-z0-9]+[a-z0-9\-]*[a-z0-9]+$/i', $sld) && strlen($sld) < 64 && substr($sld, 2, 2) != '--') {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     *
     */
    public function isPasswordStrong($pwd) : bool
    {
        if( strlen($pwd) < 8 ) {
            throw new InformationException("Minimum password length is 8 characters.");
        }

        if( strlen($pwd) > 256 ) {
            throw new InformationException("Maximum password length is 256 characters.");
        }

        if( !preg_match("#[0-9]+#", $pwd) ) {
            throw new InformationException("Password must include at least one number.");
        }

        if( !preg_match("#[a-z]+#", $pwd) ) {
            throw new InformationException("Password must include at least one lowercase letter.");
        }

        if( !preg_match("#[A-Z]+#", $pwd) ) {
            throw new InformationException("Password must include at least one uppercase letter.");
        }

        /*
        if( !preg_match("#\W+#", $pwd) ) {
            $msg = "Password must include at least one symbol!";
        }
        */
        return true;
    }

    /**
     * @param array $required - Array with required keys and messages to show if the key is not found
     * @param array $data - Array to search for keys
     * @param array $variables - Array of variables for message placeholders (:placeholder)
     * @param integer $code - Exception code
     *
     * @throws InformationException
     */
    public function checkRequiredParamsForArray(array $required, array $data, array $variables = NULL, $code = 0)
    {
        foreach ($required as $key => $msg) {

            if(!isset($data[$key])){
                throw new InformationException($msg, $variables, $code);
            }

            if (is_string($data[$key]) && strlen(trim($data[$key])) === 0){
                throw new InformationException($msg, $variables, $code);
            }

            if (!is_numeric($data[$key]) && empty($data[$key])){
                throw new InformationException($msg, $variables, $code);
            }
        }
    }

    public function isBirthdayValid($birthday = '')
    {
        if (strlen(trim($birthday)) > 0 && strtotime($birthday) === false) {
            $friendlyName = ucfirst(__trans('Birthdate'));
            throw new Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
        }
        return true;
    }
}

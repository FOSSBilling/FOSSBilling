<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Box_Password {

    private string $algo = PASSWORD_DEFAULT;
    private array $options = array();

    public function setAlgo ($algo)
    {
        $this->algo = $algo;
    }

    public function getAlgo()
    {
        return $this->algo;
    }

    public function setOptions($options = array())
    {
        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function hashIt($password)
    {
        return password_hash($password, $this->algo, $this->options);
    }

    public function verify ($password, $hash)
    {
        return password_verify((string) $password, $hash);
    }

    public function needsRehash($hash)
    {
        return password_needs_rehash($hash, $this->algo, $this->options);
    }

    /**
     * Generates random password
     * @param int $length
     * @param int $strength
     * @return string
     */
    public function generate($length = 8, $strength = 3)
    {
        $upper = 0;
        $lower = 0;
        $numeric = 0;
        $other = 0;

        $upper_letters = 'QWERTYUIOPASDFGHJKLZXCVBNM';
        $lower_letters = 'qwertyuiopasdfghjklzxccvbnm';
        $numbers = '1234567890';
        $symbols = '!@#$%&?()+-_';

        switch ($strength) {
                //lowercase + uppercase + numeric
            case 3:
                $lower = random_int(1, $length - 2);
                $upper = random_int(1, $length - $lower - 1);
                $numeric = $length - $lower - $upper;
                break;
                //lowercase + uppercase + numeric + symbols
            case 4:
            default:
                $lower = random_int(1, $length - 3);
                $upper = random_int(1, $length - $lower - 2);
                $numeric = random_int(1, $length - $lower - $upper - 1);
                $other = $length - $lower - $upper - $numeric;
                break;
        }

        $passOrder = array();

        for ($i = 0; $i < $upper; $i++) {
            $passOrder[] = $upper_letters[random_int(0, mt_getrandmax()) % strlen($upper_letters)];
        }
        for ($i = 0; $i < $lower; $i++) {
            $passOrder[] = $lower_letters[random_int(0, mt_getrandmax()) % strlen($lower_letters)];
        }
        for ($i = 0; $i < $numeric; $i++) {
            $passOrder[] = $numbers[random_int(0, mt_getrandmax()) % strlen($numbers)];
        }
        for ($i = 0; $i < $other; $i++) {
            $passOrder[] = $symbols[random_int(0, mt_getrandmax()) % strlen($symbols)];
        }

        shuffle($passOrder);
        return implode('', $passOrder);
    }
}

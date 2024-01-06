<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Password
{
    private string $algo = PASSWORD_DEFAULT;
    private array $options = [];

    public function setAlgo($algo)
    {
        $this->algo = $algo;
    }

    public function getAlgo()
    {
        return $this->algo;
    }

    public function setOptions($options = [])
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

    public function verify($password, $hash)
    {
        return password_verify((string) $password, $hash);
    }

    public function needsRehash($hash)
    {
        return password_needs_rehash($hash, $this->algo, $this->options);
    }
}

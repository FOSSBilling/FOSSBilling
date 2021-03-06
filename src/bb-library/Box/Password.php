<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Box_Password {

    private $algo = PASSWORD_DEFAULT;
    private $options = array();

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
}
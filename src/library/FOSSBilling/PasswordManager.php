<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class PasswordManager
{
    private string $algo;
    private array $options;

    public function __construct(string $algo = PASSWORD_DEFAULT, array $options = [])
    {
        $this->setAlgo($algo);
        $this->setOptions($options);
    }

    /**
     * Sets the password hashing algorithm.
     *
     * @return object an instance of the current class, for use with chaining
     *
     * @throws \Exception if the provided algorithm is not listed as a valid option in PHP
     */
    public function setAlgo(string $algo): object
    {
        if (!in_array($algo, password_algos())) {
            throw new \Exception('Invalid password hash provided');
        }

        $this->algo = $algo;

        return $this;
    }

    public function getAlgo(): string
    {
        return $this->algo;
    }

    /**
     * @return object an instance of the current class, for use with chaining
     */
    public function setOptions($options = []): object
    {
        $this->options = array_merge(['cost' => 12], $options);

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Creates a hash of the provided password.
     *
     * @throws \Exception if there was an error when hashing the password
     */
    public function hashIt(string $password): string
    {
        $hash = password_hash($password, $this->algo, $this->options);
        if (!is_string($hash)) {
            throw new \Exception("Password hashing failed with $this->algo and the following options: " . print_r($this->options, true));
        } else {
            return $hash;
        }
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algo, $this->options);
    }
}

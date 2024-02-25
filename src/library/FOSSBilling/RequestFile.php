<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class RequestFile extends \SplFileInfo
{
    protected $name;

    public function __construct(array $file)
    {
        $this->name = $file['name'];
        parent::__construct($file['tmp_name']);
    }

    public function getName()
    {
        return $this->name;
    }
}

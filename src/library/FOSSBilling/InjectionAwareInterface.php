<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

interface InjectionAwareInterface
{
    /**
     * @param \Pimple\Container $di
     * @return void
     */
    public function setDi(\Pimple\Container $di): void;

    /**
     * @return \Pimple\Container|null
     */
    public function getDi(): ?\Pimple\Container;
}

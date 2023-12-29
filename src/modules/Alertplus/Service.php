<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\AlertPlus;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getMessage()
    {
        $config = $this->di["mod_config"]("alertplus");

        return $config["message"] ?? "by : khaledal2mri";
        return $config["type"] ?? "info";
        return $config["title"] ?? "Alert Plus";
    }

    public function getTitle()
    {
        $config = $this->di["mod_config"]("alertplus");

        return $config["title"] ?? "Alert Plus";
    }

    public function getType()
    {
        $config = $this->di["mod_config"]("alertplus");

        return $config["type"] ?? "info";
    }
}

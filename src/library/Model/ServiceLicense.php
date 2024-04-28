<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ServiceLicense extends RedBeanPHP\SimpleModel
{
    public function getAllowedIps(): array
    {
        if (isset($this->ips) && json_validate($this->ips)) {
            return json_decode($this->ips, true);
        }

        return [];
    }

    public function getAllowedVersions(): array
    {
        if (isset($this->versions) && json_validate($this->versions)) {
            return json_decode($this->versions, true);
        }

        return [];
    }

    public function getAllowedHosts(): array
    {
        if (isset($this->hosts) && json_validate($this->hosts)) {
            return json_decode($this->hosts, true);
        }

        return [];
    }

    public function getAllowedPaths(): array
    {
        if (isset($this->paths) && json_validate($this->paths)) {
            return json_decode($this->paths, true);
        }

        return [];
    }
}

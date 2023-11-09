<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_ServiceLicense extends \RedBeanPHP\SimpleModel
{
    public function getAllowedIps()
    {
        $allowedIps = json_decode($this->ips, true);

        return is_array($allowedIps) ? $allowedIps : [];
    }

    public function getAllowedVersions()
    {
        $allowedVersions = json_decode($this->versions, true);

        return is_array($allowedVersions) ? $allowedVersions : [];
    }

    public function getAllowedHosts()
    {
        $allowedHosts = json_decode($this->hosts, true);

        return is_array($allowedHosts) ? $allowedHosts : [];
    }

    public function getAllowedPaths()
    {
        $allowedPaths = json_decode($this->paths, true);

        return is_array($allowedPaths) ? $allowedPaths : [];
    }

}

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
    private function _decodeJson($j)
    {
        if (isset($j)) {
            $config = json_decode($j, true);

            return is_array($config) ? $config : [];
        } else {
            return [];
        }
    }

    public function getAllowedIps()
    {
        return $this->_decodeJson($this->ips);
    }

    public function getAllowedVersions()
    {
        return $this->_decodeJson($this->versions);
    }

    public function getAllowedHosts()
    {
        return $this->_decodeJson($this->hosts);
    }

    public function getAllowedPaths()
    {
        return $this->_decodeJson($this->paths);
    }
}

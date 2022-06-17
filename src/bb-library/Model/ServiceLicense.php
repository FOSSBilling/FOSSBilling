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


class Model_ServiceLicense extends \RedBean_SimpleModel
{
    private function _decodeJson($j)
    {
        $config = json_decode($j, true);
        return is_array($config) ? $config : array();
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
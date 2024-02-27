<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey\Api;

class Guest extends \Api_Abstract
{
    /**
     * Checks if an API key is valid or not.
     *
     * @param array $data
     *                    - 'key' What API key to check
     */
    public function check($data)
    {
        return $this->getService()->isValid($data);
    }

    /**
     * Gets the information tied to an API key such as its validity and any custom parameters tied to it.
     *
     * @param array $data
     *                    - 'key' What API key to check & get custom parameters for
     */
    public function get_info($data)
    {
        return $this->getService()->getInfo($data);
    }
}

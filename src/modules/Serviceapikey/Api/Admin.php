<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey\Api;

class Admin extends \Api_Abstract
{
    /**
     * Update an API key. Can be used to change it's validity and config, but not to reset / regenerate the key itself.
     * 
     * @param array $data - An associative array
     *               - int 'id' (required) The ID of the API key to reset. This should match it's ID in the database.
     *               - bool 'valid' (optional) Used to set if an API key is valid or not.
     *               - array 'config' (optional) The new configuration for the API key.
     */
    public function update($data): bool
    {
        return $this->getService()->updateApiKey($data);
    }

    /**
     * Used to reset / regenerate an API key. Useful in the event one is accidentally leaked.
     * 
     * @param array $data - An associative array containing either the key or ID of whatever API key you want to reset.
     *               - string 'key' The API key to reset.
     *               - int 'id' The ID of the API key to reset.
     */
    public function reset($data): bool
    {
        return $this->getService()->resetApiKey($data);
    }
}

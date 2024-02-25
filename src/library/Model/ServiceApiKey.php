<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ServiceApiKey extends RedBeanPHP\SimpleModel
{
    public int $id;
    public int $client_id;
    public string $api_key;
    public string $config;
    public string $created_at;
    public string $updated_at;
}

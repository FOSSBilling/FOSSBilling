<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense\Plugin;

class Simple
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

    /**
     * License generation script.
     *
     * @return string
     */
    public function generate(\Model_ServiceLicense $service, \Model_ClientOrder $order, array $config)
    {
        // Optional: to get customer data
        // $client = $this->di['db']->load('Client', $order->client_id);

        $length = $config['length'] ?? 25;
        $prefix = $config['prefix'] ?? null;

        $character_array = [...range('A', 'Z'), ...range(1, 9)];
        $size = count($character_array) - 1;
        $string = '';
        for ($i = 1; $i < $length; ++$i) {
            $string .= ($i % 5 == 0) ? '-' : $character_array[random_int(0, $size)];
        }

        return $prefix . $string;
    }

    /*
     * This method is optional.
     * Additional validation rules can be applied to license validation logic.
     * Method is called after "expiration, ip, version, path, hostname validations are passed".
     *
     * Should throw "LogicException" if validation fails
     *
     * @param Model_ServiceLicense $service
     * @param array                $data
     *
     * @return array - list of params to be attached to response message
     *
     * @throws LogicException
     */
    /*
    public function validate(Model_ServiceLicense $service, array $data)
    {
        if(!$validation_rule) {
            throw new LogicException('Some validation rule did not pass', 1020);
        }

        return array(
            'key'   =>  'value',
            'key2'  =>  'value2'
        );
    }
     */
}

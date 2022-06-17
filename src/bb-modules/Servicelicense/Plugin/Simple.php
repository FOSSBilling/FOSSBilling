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

namespace Box\Mod\Servicelicense\Plugin;

class Simple
{
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
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

        $character_array = array_merge(range('A', 'Z'), range(1, 9));
        $size = count($character_array) - 1;
        $string = '';
        for ($i = 1; $i < $length; ++$i) {
            $string .= (0 == $i % 5) ? '-' : $character_array[rand(0, $size)];
        }

        return $prefix.$string;
    }

    /**
     * This method is optional.
     * Aditional validation rules can be applied to license validation logic.
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

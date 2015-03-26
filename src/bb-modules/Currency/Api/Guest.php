<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 *Currency management 
 */

namespace Box\Mod\Currency\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get list of available currencies
     * 
     * @return array
     */
    public function get_pairs($data)
    {
        $service = $this->getService();
        return $service->getPairs();
    }

    /**
     * Get currency by code
     * 
     * @param string $code - currency code, ie: USD
     * @return array
     */
    public function get($data)
    {
        $service = $this->getService();
        if(isset($data['code']) && !empty($data['code'])) {
            $model = $service->getByCode($data['code']);
        } else {
            $model = $service->getDefault();
        }
        
        if(!$model instanceof \Model_Currency) {
            throw new \Box_Exception('Currency not found');
        }
        return $service->toApiArray($model);
    }
    
    /**
     * Format price by currency settings
     * 
     * @optional bool $convert - covert to default currency rate. Default - true; 
     * @optional bool $without_currency - Show only number. No symbols are attached Default - false;
     * @optional float $price - Price to be formated. Default 0
     * @optional string $code - currency code, ie: USD. Default - default currency
     * 
     * @return string - formated string
     */
    public function format($data = array())
    {
        $c = $this->get($data);

        $price = $this->di['array_get']($data, 'price', 0);
        $convert = $this->di['array_get']($data, 'convert', true);
        $without_currency = (bool) $this->di['array_get']($data, 'without_currency', false);

        $p = $price;
        if($convert) {
            $p = $price * $c['conversion_rate'];
        }

        switch ($c['price_format']) {
            case 2:
                $p = number_format($p, 2, '.', ',');
                break;
            
            case 3:
                $p = number_format($p, 2, ',', '.');
                break;
            
            case 4:
                $p = number_format($p, 0, '', ',');
                break;
            
            case 5:
                $p = number_format($p, 0, '', '');
                break;

            case 1:
            default:
                $p = number_format($p, 2, '.', '');    
                break;
        }

        if($without_currency) {
            return $p;
        }

        return str_replace('{{price}}', $p, $c['format']);
    }
}
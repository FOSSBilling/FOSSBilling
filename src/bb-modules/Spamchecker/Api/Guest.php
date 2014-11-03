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
 * Spam cheking module management 
 */
namespace Box\Mod\Spamchecker\Api;
class Guest extends \Api_Abstract
{
    /**
     * Returns recaptcha public key
     * 
     * @return string
     */
    public function recaptcha($data)
    {
        $api = $this->getApiAdmin();
        $config      = $api->extension_config_get(array("ext"=>"mod_spamchecker"));
        $result = array(
            'publickey' =>  isset($config['captcha_recaptcha_publickey']) ? $config['captcha_recaptcha_publickey'] : null,
            'enabled'   =>  isset($config['captcha_enabled']) ? $config['captcha_enabled'] : false,
        );
        return $result;
    }
}
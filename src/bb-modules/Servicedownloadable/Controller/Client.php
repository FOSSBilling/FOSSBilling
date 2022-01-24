<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicedownloadable\Controller;

class Client implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/servicedownloadable/get-file/:id', 'get_download', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_download(\Box_App $app, $id)
    {
        $api = $this->di['api_client'];
        $data = array(
            'order_id'  =>  $id,
        );
        $api->servicedownloadable_send_file($data);
    }
}
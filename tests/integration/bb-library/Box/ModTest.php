<?php

/**
 * @group Core
 */
class Box_ModIntegrationTest extends BBDbApiTestCase
{
    public function testModel()
    {
        $conf = array(
            'last_name'
        );

        $this->api_admin->extension_config_save(array(
            'ext'      => 'mod_client',
            'required' => $conf
        ));

        $config = $this->di['mod_config']('client');
        $this->assertEquals($conf, $config);
    }
}
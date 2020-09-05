<?php

/**
 * @group Core
 */
class ModTest extends BBDbApiTestCase
{
    public function testConfig()
    {
        $conf = array(
            'ext'      => 'mod_client',
            'required' => array(
                'last-name'
            )
        );

        $this->api_admin->extension_config_save($conf);

        $config = $this->di['mod_config']('client');
        $this->assertEquals($conf, $config);
    }
}
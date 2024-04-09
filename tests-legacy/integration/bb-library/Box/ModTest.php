<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class ModTest extends BBDbApiTestCase
{
    public function testConfig(): void
    {
        $conf = [
            'ext' => 'mod_client',
            'required' => [
                'last-name',
            ],
        ];

        $this->api_admin->extension_config_save($conf);

        $config = $this->di['mod_config']('client');
        $this->assertEquals($conf, $config);
    }
}

<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Payment_Adapter_CustomTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'gateway_Custom.xml';

    public function testCustom(): void
    {
        $config = [
            'single' => 'test',
            'recurrent' => 'test',
        ];
        $adapter = new Payment_Adapter_Custom($config);
        $adapter->setDi($this->di);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1, true);

        $tx = $this->di['db']->load('Transaction', 1);
        $adapter->process($tx);
    }
}

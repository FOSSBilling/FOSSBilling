<?php

class DiTest extends PHPUnit\Framework\TestCase
{
    public function setup(): void
    {
        global $di;
        $this->di = clone $di;
    }

    public function testInjector(): void
    {
        $di = $this->di;
        $this->assertInstanceOf('Box_Mod', $di['mod']('admin'));
        $this->assertInstanceOf('Box_Log', $di['logger']);
        $this->assertInstanceOf('Box_Crypt', $di['crypt']);
        $this->assertTrue(isset($di['pdo']));
        $this->assertTrue(isset($di['db']));

        $this->assertInstanceOf('Box_Pagination', $di['pager']);
        $this->assertInstanceOf('Box_Url', $di['url']);
        $this->assertInstanceOf('Box_EventManager', $di['events_manager']);

        $this->assertInstanceOf('\\' . FOSSBilling\Session::class, $di['session']);
        $this->assertInstanceOf('Box_Authorization', $di['auth']);
        $this->assertInstanceOf(Twig\Environment::class, $di['twig']);
        $this->assertInstanceOf('\\' . FOSSBilling\Tools::class, $di['tools']);
        $this->assertInstanceOf('\\' . FOSSBilling\Validate::class, $di['validator']);

        $this->assertTrue(isset($di['mod']));
        $this->assertTrue(isset($di['mod_config']));
        $this->assertInstanceOf(Box\Mod\Cron\Service::class, $di['mod_service']('cron'));
        $this->assertInstanceOf('\\' . FOSSBilling\ExtensionManager::class, $di['extension_manager']);
        $this->assertInstanceOf('\Box_Update', $di['updater']);
        $this->assertInstanceOf('\Server_Package', $di['server_package']);
        $this->assertInstanceOf('\Server_Client', $di['server_client']);
        $this->assertInstanceOf('\Server_Account', $di['server_account']);
        $this->assertTrue(isset($di['server_manager']));
        $this->assertInstanceOf('\\' . FOSSBilling\Requirements::class, $di['requirements']);
        $this->assertInstanceOf('\\' . Box\Mod\Theme\Model\Theme::class, $di['theme']);
        $this->assertInstanceOf('\Model_Cart', $di['cart']);
        $this->assertInstanceOf('\\' . GeoIp2\Database\Reader::class, $di['geoip']);
        $this->assertInstanceOf('\FOSSBilling\PasswordManager', $di['password']);
        $this->assertInstanceOf('\Box_Translate', $di['translate']());
    }
}

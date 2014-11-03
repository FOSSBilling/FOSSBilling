<?php
/**
 * @group Core
 */
class Box_Mod_Branding_ServiceTest extends ApiTestCase
{
    private $configLicense = null;

    public function tearDown()
    {
        if ($this->configLicense){
            $this->di['config']['license'] = $this->configLicense;
        }
    }

    public function testEvents()
    {
        $service = $this->di['mod_service']('branding');
        $bool = $service->uninstall();
        $this->assertTrue($bool);
    }

    public function testUninstallNotPro()
    {
        $this->configLicense = $this->di['config']['license'];
        $this->setExpectedException('Exception');
        $service = $this->di['mod_service']('branding');
        $this->di['config']['license'] = 'invalidLicense';
        $service->uninstall();
    }
}
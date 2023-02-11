<?php
/**
 * @group Core
 */
class ServiceTest extends ApiTestCase
{
    private $configLicense = null;

    public function tearDown() : void
    {
        if ($this->configLicense){
            $this->di['config']['license'] = $this->configLicense;
        }
    }

    public function testUninstallNotPro()
    {
        $this->configLicense = $this->di['config']['license'];
        $this->expectException(Exception::class);
        $service = $this->di['mod_service']('branding');
        $this->di['config']['license'] = 'invalidLicense';
        $service->uninstall();
    }
}
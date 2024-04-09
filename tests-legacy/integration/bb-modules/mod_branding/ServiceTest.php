<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class ServiceTest extends ApiTestCase
{
    private $configLicense;

    public function tearDown(): void
    {
        if ($this->configLicense) {
            $this->di['config']['license'] = $this->configLicense;
        }
    }

    public function testUninstallNotPro(): void
    {
        $this->configLicense = $this->di['config']['license'];
        $this->expectException(Exception::class);
        $service = $this->di['mod_service']('branding');
        $this->di['config']['license'] = 'invalidLicense';
        $service->uninstall();
    }
}

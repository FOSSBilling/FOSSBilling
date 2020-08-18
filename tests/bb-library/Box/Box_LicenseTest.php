<?php
/**
 * @group Core
 */
class Box_LicenseTest extends PHPUnit\Framework\TestCase
{
    protected function setup() : void
    {
        global $di;
        $this->di = $di;
    }

    public function testLicense()
    {
        self::expectNotToPerformAssertions();
        $license = $this->di['license'];
        $license->getDetails();
        $license->getDetails(true);
    }
}
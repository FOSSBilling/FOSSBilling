<?php

/**
 * FOSSBilling tools tests
 */
class FOSS_ToolsTest extends PHPUnit\Framework\TestCase
{
        public function testValidateAndSanitizeEmail()
    {
        $testEmail = 'example@example.com';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $result = $this->di['tools']->validateAndSanitizeEmail($testEmail, false);
        $this->assertEquals($testEmail, $result);
    }

    public function testValidateAndSanitizeEmailID()
    {
        $testEmail = 'example@example-tést.eu';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $result = $this->di['tools']->validateAndSanitizeEmail($testEmail, false);
        $this->assertEquals($testEmail, $result);
    }

    public function testValidateAndSanitizeEmailInvalid()
    {
        $testEmail = '<a href="http://somethingnotgood.com">Totally an email</a>"';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $result = $this->di['tools']->validateAndSanitizeEmail($testEmail, false);
        $this->assertFalse($result);
    }
}

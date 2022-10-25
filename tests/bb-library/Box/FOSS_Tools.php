<?php

/**
 * FOSSBilling tools tests
 */
class FOSS_ToolsTest extends PHPUnit\Framework\TestCase
{
        public function testValidateAndSanitizeEmail()
    {
        $email = 'example@example.com';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $result = $this->di['tools']->validateAndSanitizeEmail($email, false);
        $this->assertEquals($email, $result)
    }

    public function testValidateAndSanitizeEmailID()
    {
        $email = 'example@example-tést.eu';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $result = $this->di['tools']->validateAndSanitizeEmail($email, false);
        $this->assertEquals($email, $result)
    }

    public function testValidateAndSanitizeEmailInvalid()
    {
        $email = '<a href="http://somethingnotgood.com">Totally an email</a>"';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $result = $this->di['tools']->validateAndSanitizeEmail($email, false);
        $this->assertFalse($result)
    }
}

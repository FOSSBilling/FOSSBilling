<?php

declare(strict_types=1);

namespace SpamcheckerTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testDisposableEmailCheck(): void
    {
        $result = Request::makeRequest('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $result = Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'check_temp_emails' => true]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // Generate a new test user with by using a throwaway email address, which should fail
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'email@yopmail.net',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertFalse($result->wasSuccessful(), 'The client account was created when it should not have been');
        $this->assertEquals('Disposable email addresses are not allowed', $result->getErrorMessage());

        // If the account was created, perform cleanup
        if ($result->wasSuccessful()) {
            $id = intval($result->getResult());
            Request::makeRequest('admin/client/delete', ['id' => $id]);
        }
    }

    public function testStopForumSpam(): void
    {
        $result = Request::makeRequest('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $result = Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'sfs' => true]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        /**
         * This one should create without any errors as long as the email address doesn't get listed as spam.
         *
         * @see http://api.stopforumspam.org/api?email=email@example.com
         */
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'email@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsNumeric($result->getResult());

        $id = intval($result->getResult());

        $result = Request::makeRequest('admin/client/delete', ['id' => $id]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }
}

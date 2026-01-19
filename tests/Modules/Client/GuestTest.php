<?php

declare(strict_types=1);

namespace ClientTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testCreateAndDestroyClient(): void
    {
        // Generate a new test user
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'client@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone_cc' => 1,
            'phone' => "(216) 245-2368"
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsNumeric($result->getResult());

        $id = intval($result->getResult());

        $result = Request::makeRequest('admin/client/delete', ['id' => $id]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testPhoneCCMustBeGreaterThanZero(): void
    {
        // Generate a new test user
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone_cc' => -1,
        ]);

        $this->assertFalse($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }

    public function testPhoneCCMaximum(): void
    {
        // Generate a new test user
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone_cc' => 1000,
        ]);

        $this->assertFalse($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }

    public function testPhoneNumberLength(): void
    {
        // Generate a new test user
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone' => "123456789101123",
        ]);

        $this->assertFalse($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }
}

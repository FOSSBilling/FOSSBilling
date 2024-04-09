<?php

declare(strict_types=1);

namespace SupportTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testTicketCreateForGuest(): void
    {
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsString($result->getResult());
        $this->assertGreaterThanOrEqual(200, strlen($result->getResult()));
        $this->assertLessThanOrEqual(255, strlen($result->getResult()));
    }

    public function testTicketCreateForGuestDisabled(): void
    {
        // Disable public tickets
        Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => true]);

        // Now ensure
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email2@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals("We currently aren't accepting support tickets from unregistered users. Please use another contact method.", $result->getErrorMessage());

        // Set it back
        Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => false]);
    }
}

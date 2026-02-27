<?php

declare(strict_types=1);

namespace SupportTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    /**
     * Indicates whether this test class modified the "disable_public_tickets" setting
     * and therefore needs it to be reset in tearDown().
     *
     * @var bool
     */
    private bool $restoreDisablePublicTickets = false;

    protected function tearDown(): void
    {
        if ($this->restoreDisablePublicTickets) {
            // Ensure that public tickets configuration is reset after tests that modify it.
            $configResetResult = Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => false]);
            if (!$configResetResult->wasSuccessful()) {
                // Fail the test explicitly if configuration restoration fails to avoid test pollution.
                $this->fail(
                    method_exists($configResetResult, 'generatePHPUnitMessage')
                        ? $configResetResult->generatePHPUnitMessage()
                        : 'Failed to restore disable_public_tickets configuration in tearDown().'
                );
            }
            $this->restoreDisablePublicTickets = false;
        }

        parent::tearDown();
    }

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
        $this->assertGreaterThanOrEqual(30, strlen($result->getResult()));
        $this->assertLessThanOrEqual(60, strlen($result->getResult()));
    }

    public function testTicketCreateForGuestDisabled(): void
    {
        // Disable public tickets
        $configResult = Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => true]);
        $this->assertTrue($configResult->wasSuccessful(), $configResult->generatePHPUnitMessage());

        // Verify that the configuration change to disable public tickets was actually applied.
        $configGetResult = Request::makeRequest('admin/extension/config_get', ['ext' => 'mod_support']);
        $this->assertTrue($configGetResult->wasSuccessful(), $configGetResult->generatePHPUnitMessage());
        $configData = $configGetResult->getResult();
        $this->assertIsArray($configData);
        $this->assertArrayHasKey('disable_public_tickets', $configData);
        $this->assertTrue((bool) $configData['disable_public_tickets']);

        // Mark that we need to restore this configuration in tearDown()
        $this->restoreDisablePublicTickets = true;

        // Now ensure that guest ticket creation fails when public tickets are disabled
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email2@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals("We currently aren't accepting support tickets from unregistered users. Please use another contact method.", $result->getErrorMessage());
    }

    public function testTicketCreateForGuestMissingName(): void
    {
        $result = Request::makeRequest('guest/support/ticket_create', [
            // 'name' is intentionally omitted
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Please enter your name', $result->getErrorMessage());
    }

    public function testTicketCreateForGuestMissingEmail(): void
    {
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            // 'email' is intentionally omitted
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Please enter your email address', $result->getErrorMessage());
    }

    public function testTicketCreateForGuestInvalidEmail(): void
    {
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'not-an-email',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Email address is invalid', $result->getErrorMessage());
    }

    public function testTicketCreateForGuestEmptySubject(): void
    {
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => '',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Please enter the subject', $result->getErrorMessage());
    }

    public function testTicketCreateForGuestEmptyMessage(): void
    {
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => '',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Please enter your message', $result->getErrorMessage());
    }
}

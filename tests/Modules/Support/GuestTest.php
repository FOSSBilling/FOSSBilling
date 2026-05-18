<?php

declare(strict_types=1);

namespace SupportTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    private const MIN_TICKET_ID_LENGTH = 30;
    private const MAX_TICKET_ID_LENGTH = 60;

    /**
     * Snapshot of the initial Support extension config captured in setUp().
     * Null means the config could not be determined and therefore cannot be safely restored.
     *
     * @var array<string, mixed>|null
     */
    private ?array $initialSupportConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $configGetResult = Request::makeRequest('admin/extension/config_get', ['ext' => 'mod_support']);
        if (!$configGetResult->wasSuccessful()) {
            $this->fail($configGetResult->generatePHPUnitMessage());
        }

        $configData = $configGetResult->getResult();
        $this->assertIsArray($configData);
        $this->initialSupportConfig = $configData;
    }

    protected function tearDown(): void
    {
        if ($this->initialSupportConfig !== null) {
            // Always restore the original Support configuration captured in setUp().
            $configResetResult = Request::makeRequest(
                'admin/extension/config_save',
                array_merge(['ext' => 'mod_support'], $this->initialSupportConfig)
            );
            if (!$configResetResult->wasSuccessful()) {
                // Fail explicitly if configuration restoration fails to avoid test pollution.
                $this->fail(
                    method_exists($configResetResult, 'generatePHPUnitMessage')
                        ? $configResetResult->generatePHPUnitMessage()
                        : 'Failed to restore Support configuration in tearDown().'
                );
            }
            $this->initialSupportConfig = null;
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
        $this->assertGreaterThanOrEqual(self::MIN_TICKET_ID_LENGTH, strlen($result->getResult()));
        $this->assertLessThanOrEqual(self::MAX_TICKET_ID_LENGTH, strlen($result->getResult()));
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

        // Now ensure that guest ticket creation fails when public tickets are disabled
        $result = Request::makeRequest('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email2@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals("We currently are not accepting support tickets from unregistered users. Please use another contact method.", $result->getErrorMessage());
    }

    public function testPublicTicketsEnabledReflectsConfiguration(): void
    {
        $enabledResult = Request::makeRequest('guest/support/public_tickets_enabled');
        $this->assertTrue($enabledResult->wasSuccessful(), $enabledResult->generatePHPUnitMessage());
        $this->assertTrue($enabledResult->getResult());

        $configResult = Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => true]);
        $this->assertTrue($configResult->wasSuccessful(), $configResult->generatePHPUnitMessage());

        $disabledResult = Request::makeRequest('guest/support/public_tickets_enabled');
        $this->assertTrue($disabledResult->wasSuccessful(), $disabledResult->generatePHPUnitMessage());
        $this->assertFalse($disabledResult->getResult());
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

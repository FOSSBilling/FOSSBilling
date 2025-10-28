<?php

declare(strict_types=1);

describe('Public Support Tickets', function () {
    it('allows guests to create support tickets', function () {
        $hash = api('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ])->getResult();

        expect($hash)
            ->toBeString()
            ->and(strlen($hash))
            ->toBeGreaterThanOrEqual(200)
            ->toBeLessThanOrEqual(255);
    });

    it('prevents ticket creation when public tickets are disabled', function () {
        // Disable public tickets
        api('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => true]);

        // Attempt to create ticket (should fail)
        $response = api('guest/support/ticket_create', [
            'name' => 'Name',
            'email' => 'email2@example.com',
            'subject' => 'Subject',
            'message' => 'message',
        ]);

        expect($response)
            ->toBeFailedResponse()
            ->and($response->getErrorMessage())
            ->toBe("We currently aren't accepting support tickets from unregistered users. Please use another contact method.");

        // Re-enable public tickets
        api('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => false]);
    });
});

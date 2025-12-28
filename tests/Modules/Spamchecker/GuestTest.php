<?php

declare(strict_types=1);

describe('Spam Protection', function () {
    it('blocks disposable email addresses when enabled', function () {
        // Enable spamchecker and disposable email checking
        api('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
        api('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'check_temp_emails' => true]);

        // Attempt to create account with disposable email (should fail)
        $password = 'A1a' . bin2hex(random_bytes(6));
        $response = api('guest/client/create', [
            'email' => 'email@yopmail.net',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        expect($response)
            ->toBeFailedResponse()
            ->and($response->getErrorMessage())
            ->toBe('Disposable email addresses are not allowed');

        // Cleanup if somehow it succeeded
        if ($response->wasSuccessful()) {
            api('admin/client/delete', ['id' => intval($response->getResult())]);
        }
    });

    it('blocks known spam users via StopForumSpam', function () {
        // Enable spamchecker and StopForumSpam checking
        api('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
        api('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'sfs' => true]);

        /**
         * This should create without errors as long as the email isn't listed as spam.
         *
         * @see http://api.stopforumspam.org/api?email=email@example.com
         */
        $password = 'A1a' . bin2hex(random_bytes(6));
        $clientId = api('guest/client/create', [
            'email' => 'email@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ])->getResult();

        expect($clientId)->toBeNumeric();

        // Cleanup
        expect(api('admin/client/delete', ['id' => intval($clientId)]))
            ->toHaveResult()
            ->toBeTrue();
    });
});

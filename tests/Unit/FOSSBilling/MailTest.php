<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Mail;

function mailGetUnderlyingEmail(Mail $mail): Symfony\Component\Mime\Email
{
    $ref = new ReflectionProperty($mail, 'email');

    return $ref->getValue($mail);
}

test('attach adds a file attachment to the underlying email message', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com', 'name' => 'Sender'],
        ['email' => 'receiver@example.com', 'name' => 'Receiver'],
        'Invoice created',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->attach('%PDF-1.4 fake invoice contents', 'Invoice-BB0001.pdf', 'application/pdf');

    $attachments = mailGetUnderlyingEmail($mail)->getAttachments();
    expect($attachments)->toHaveCount(1);

    $attachment = $attachments[0];
    expect($attachment->getFilename())->toBe('Invoice-BB0001.pdf');
    expect($attachment->getMediaType() . '/' . $attachment->getMediaSubtype())->toBe('application/pdf');
    expect($attachment->getBody())->toBe('%PDF-1.4 fake invoice contents');
});

test('attach can be called multiple times to add several attachments', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->attach('content-a', 'a.txt', 'text/plain');
    $mail->attach('content-b', 'b.txt', 'text/plain');

    expect(mailGetUnderlyingEmail($mail)->getAttachments())->toHaveCount(2);
});

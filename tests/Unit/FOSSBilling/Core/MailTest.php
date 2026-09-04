<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Core\Mail;

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

test('addBcc accepts a single address string', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addBcc('billing@example.com');

    $bcc = mailGetUnderlyingEmail($mail)->getBcc();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $bcc))
        ->toBe(['billing@example.com']);
});

test('addBcc accepts an array of address strings', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addBcc(['billing@example.com', 'accounts@example.com']);

    $bcc = mailGetUnderlyingEmail($mail)->getBcc();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $bcc))
        ->toBe(['billing@example.com', 'accounts@example.com']);
});

test('addTo accepts a single address string', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addTo('second@example.com');

    $to = mailGetUnderlyingEmail($mail)->getTo();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $to))
        ->toBe(['receiver@example.com', 'second@example.com']);
});

test('addTo accepts an array of address strings', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addTo(['second@example.com', 'third@example.com']);

    $to = mailGetUnderlyingEmail($mail)->getTo();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $to))
        ->toBe(['receiver@example.com', 'second@example.com', 'third@example.com']);
});

test('addCc accepts a single address string', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addCc('manager@example.com');

    $cc = mailGetUnderlyingEmail($mail)->getCc();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $cc))
        ->toBe(['manager@example.com']);
});

test('addCc accepts an array of address strings', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addCc(['manager@example.com', 'accounts@example.com']);

    $cc = mailGetUnderlyingEmail($mail)->getCc();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $cc))
        ->toBe(['manager@example.com', 'accounts@example.com']);
});

test('addReplyTo accepts a single address string', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addReplyTo('support@example.com');

    $replyTo = mailGetUnderlyingEmail($mail)->getReplyTo();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $replyTo))
        ->toBe(['support@example.com']);
});

test('addReplyTo accepts an array of address strings', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'sendmail'
    );

    $mail->addReplyTo(['support@example.com', 'billing@example.com']);

    $replyTo = mailGetUnderlyingEmail($mail)->getReplyTo();
    expect(array_map(static fn (Symfony\Component\Mime\Address $address): string => $address->getAddress(), $replyTo))
        ->toBe(['support@example.com', 'billing@example.com']);
});

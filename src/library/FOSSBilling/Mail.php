<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mail
{
    private readonly Email $email;
    private ?string $transport = null;
    private ?string $dsn = null;

    /**
     * Constructor for creating an email message. The custom DSN will be used if you either don't provide a transport or use 'custom' for it.
     *
     * @param array|string $from      the sender's email and name (optional) as an associative array with keys 'email' and 'name', a string representing the email address, or an array of email addresses
     * @param array|string $to        the recipient's email and name (optional) as an associative array with keys 'email' and 'name', a string representing the email address, or an array of email addresses
     * @param string       $subject   the subject line of the email
     * @param string       $bodyHTML  the HTML content of the email body
     * @param string|null  $transport (optional) The name of the transport to use for sending the email
     * @param string|null  $dsn       (optional) The DSN to use for sending the email. (See: https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport)
     *
     * @return void
     */
    public function __construct(array|string $from, array|string $to, string $subject, string $bodyHTML, ?string $transport, string $dsn = null)
    {
        if (isset($from['email']) && isset($from['name'])) {
            $fromAddress = new Address($from['email'], $from['name']);
        } elseif (isset($from['email'])) {
            $fromAddress = new Address($from['email']);
        } else {
            $fromAddress = $to;
        }

        if (isset($to['email']) && isset($to['name'])) {
            $toAddress = new Address($to['email'], $to['name']);
        } elseif (isset($to['email'])) {
            $toAddress = new Address($to['email']);
        } else {
            $toAddress = $to;
        }

        $this->email = (new Email())
            ->from($fromAddress)
            ->to($toAddress)
            ->subject($subject)
            ->html($bodyHTML);
        $this->transport = $transport ?? null;
        $this->dsn = $dsn ?? null;
    }

    /**
     * Add one or more email addresses to the email's "To" field.
     *
     * @param string|array $toAddresses The email address(es) to add to the "To" field. Can be a string for a single address or an array for multiple addresses.
     */
    public function addTo(string|array $toAddresses): void
    {
        $this->email->addTo($toAddresses);
    }

    /**
     * Adds one or more Carbon Copy (Cc) email addresses to the message.
     *
     * @param string|array $ccAddresses The email address(es) to add. Can be a string with a single email address, or an array with multiple email addresses.
     */
    public function addCc(string|array $ccAddresses): void
    {
        $this->email->addCc($ccAddresses);
    }

    /**
     * Add one or more Bcc addresses to the email.
     *
     * @param string|array $bccAddresses a single email address or an array of email addresses to add as Bcc recipients
     */
    public function addBcc(string|array $bccAddresses): void
    {
        $this->email->addBcc($bccAddresses);
    }

    /**
     * Add reply-to addresses to the email.
     *
     * @param string|array $replyToAddresses the email address(es) to add as reply-to
     */
    public function addReplyTo(string|array $replyToAddresses): void
    {
        $this->email->addReplyTo($replyToAddresses);
    }

    /**
     * Set the priority of the email message. Can be an integer between 1 and 5, with 1 being the highest priority.
     * It's recommended to use Symfony's pre-defined constants instead of a specific integer, but either should work. (Example: Email::PRIORITY_HIGH).
     *
     * @param int $priority the priority of the email message, represented as an integer between 1 and 5
     *
     * @throws InformationException if the provided priority is invalid
     */
    public function setPriority(int $priority): void
    {
        if (is_int($priority) && $priority >= Email::PRIORITY_HIGHEST && $priority <= Email::PRIORITY_LOWEST) {
            $this->email->priority($priority);
        } else {
            throw new InformationException('Provided priority (:priority) is invalid. Please provide an integer between 1 and 5 or use the pre-defined symfony constants.', [':priority' => $priority]);
        }
    }

    /**
     * Sends the email that was created and configured when the class was constructed.
     *
     * @param array|null $options an optional array of options specific to the chosen transport method
     *
     * @throws InformationException If the transport method is unknown or if required options for the selected transport aren't defined
     */
    public function send(array $options = null): void
    {
        switch ($this->transport) {
            case 'sendmail':
                $dsn = 'sendmail://default';
                if (!function_exists('proc_open')) {
                    throw new InformationException('FOSSBilling requires the proc_open PHP function to be enabled when using the sendmail transport');
                }

                break;
            case 'smtp':
                $dsn = $this->__smtpDsn($options);

                break;
            case 'sendgrid':
                if (empty($options['sendgrid_key'])) {
                    throw new InformationException('A SendGrid API key is required to send emails via SendGrid');
                }
                $dsn = 'sendgrid://' . $options['sendgrid_key'] . '@default';

                break;
            case 'custom':
                if (empty($this->dsn)) {
                    throw new InformationException("Unable to send email: 'Custom' transport method was selected without a custom DSN");
                }
                $dsn = $this->dsn;

                break;
            default:
                throw new InformationException('Unknown mail transport: :transport', [':transport' => $this->transport]);
        }

        try {
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);
            $mailer->send($this->email);
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Failed to send email via :transport with the exception :e', [':transport' => $this->transport, ':e' => $e]);
        }
    }

    /**
     * Create a DSN string for SMTP transport based on given options.
     *
     * @param array $options an associative array of SMTP options including 'smtp_host', 'smtp_port', 'smtp_username', and 'smtp_password'
     *
     * @return string a string representing the DSN for the SMTP transport
     *
     * @throws InformationException if the SMTP host or port is not configured
     */
    private function __smtpDsn(array $options): string
    {
        if (empty($options['smtp_host']) || empty($options['smtp_port'])) {
            throw new InformationException('SMTP host or port is not configured');
        }

        $host = urlencode(trim($options['smtp_host']));

        if (!empty($options['smtp_username'])) {
            $username = urlencode(trim($options['smtp_username']));
            $pass = urlencode(trim($options['smtp_password'] ?? ''));

            $authString = !empty($pass) ? $username . ':' . $pass : $username;

            return "smtp://$authString@" . $host . ':' . $options['smtp_port'];
        } else {
            return 'smtp://' . $host . ':' . $options['smtp_port'];
        }
    }
}

<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;

class FOSSBilling_Mail
{
    protected ?\Pimple\Container $di;

    private Email $email;
    private $transport = null;
    private $dsn = null;

    /**
     * @param \Pimple\Container|null $di
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Constructor for creating an email message. The custom DSN will be used if you either don't provide a transport or use 'custom' for it.
     *
     * @param array|string $from      The sender's email and name (optional) as an associative array with keys 'email' and 'name', a string representing the email address, or an array of email addresses.
     * @param array|string $to        The recipient's email and name (optional) as an associative array with keys 'email' and 'name', a string representing the email address, or an array of email addresses.
     * @param string $subject         The subject line of the email.
     * @param string $bodyHTML        The HTML content of the email body.
     * @param string|null $transport  (optional) The name of the transport to use for sending the email.
     * @param string|null $dsn        (optional) The DSN to use for sending the email. (See: https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport)
     *
     * @return void
     */
    public function __construct(array|string $from, array|string $to, string $subject, string $bodyHTML, string|null $transport, string|null $dsn = null)
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
     *
     * @return void
     */
    public function addTo(string|array $toAddresses): void
    {
        $this->email->addTo($toAddresses);
    }

    /**
     * Adds one or more Carbon Copy (Cc) email addresses to the message.
     *
     * @param string|array $ccAddresses The email address(es) to add. Can be a string with a single email address, or an array with multiple email addresses.
     *
     * @return void
     */
    public function addCc(string|array $ccAddresses): void
    {
        $this->email->addCc($ccAddresses);
    }

    /**
     * Add one or more Bcc addresses to the email.
     *
     * @param string|array $bccAddresses A single email address or an array of email addresses to add as Bcc recipients.
     *
     * @return void
     */
    public function addBcc(string|array $bccAddresses): void
    {
        $this->email->addBcc($bccAddresses);
    }

    /**
     * Add reply-to addresses to the email.
     *
     * @param string|array $replyToAddresses The email address(es) to add as reply-to.
     *
     * @return void
     */
    public function addReplyTo(string|array $replyToAddresses): void
    {
        $this->email->addReplyTo($replyToAddresses);
    }

    /**
     * Set the priority of the email message. Can be an integer between 1 and 5, with 1 being the highest priority.
     * It's recommended to use Symfony's pre-defined constants instead of a specific integer, but either should work. (Example: Email::PRIORITY_HIGH)
     *
     * @param int $priority The priority of the email message, represented as an integer between 1 and 5.
     *
     * @throws \Box_Exception if the provided priority is invalid.
     *
     * @return void
     */
    public function setPriority(int $priority): void
    {
        if (is_int($priority) && $priority >= Email::PRIORITY_HIGHEST && $priority <= Email::PRIORITY_LOWEST) {
            $this->email->priority($priority);
        } else {
            throw new \Box_Exception("Provided priority (:priority) is invalid. Please provide an integer between 1 and 5 or use the pre-defined symfony constants.", [':priority' => $priority]);
        }
    }

    /**
     * Sends the email that was created and configured when the class was constructed
     *
     * @param array|null $options An optional array of options specific to the chosen transport method.
     * @throws \Box_Exception If the transport method is unknown or if required options for the selected transport aren't defined
     *
     * @return void
     */
    public function send(array|null $options = null)
    {
        switch ($this->transport) {
            case 'sendmail':
                $dsn = 'sendmail://default';
                if (!function_exists('proc_open')) {
                    throw new \Box_Exception("FOSSBilling requires the proc_open PHP function to be enabled when using the sendmail transport");
                }
                break;
            case 'smtp':
                $dsn = $this->__smtpDsn($options);
                break;
            case 'sendgrid':
                if (empty($options['sendgrid_key'])) {
                    throw new \Box_Exception("A Sendgrid API key is required to send emails via Sendgrid");
                }
                $dsn = 'sendgrid://' . $options['sendgrid_key'] . '@default';
                break;
            case 'custom':
                if (empty($this->dsn)) {
                    throw new \Box_Exception("Unable to send email: 'Custom' transport method was selected without a custom DSN");
                }
                $dsn = $this->dsn;
                break;
            default:
                throw new \Box_Exception('Unknown mail transport: :transport', [':transport' => $this->transport]);
        }

        try {
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);
            $mailer->send($this->email);
        } catch (TransportExceptionInterface $e) {
            throw new \Box_Exception("Failed to send email via :transport with the exception :e", [':transport' => $this->transport, ':e' => $e]);
        }
        return true;
    }

    /**
     * Create a DSN string for SMTP transport based on given options.
     *
     * @param array $options An associative array of SMTP options including 'smtp_host', 'smtp_port', 'smtp_username', and 'smtp_password'.
     *
     * @throws \Box_Exception if the SMTP host or port is not configured.
     *
     * @return string A string representing the DSN for the SMTP transport.
     */
    private function __smtpDsn(array $options): string
    {
        if (empty($options['smtp_host']) || empty($options['smtp_port'])) {
            throw new \Box_Exception('SMTP host or port is not configured');
        }

        $host = urlencode(trim($options['smtp_host']));

        if (!empty($options['smtp_username'])) {
            $username = urlencode(trim($options['smtp_username']));
            $pass = urlencode(trim($options['smtp_password'] ?? ''));

            $authString = !empty($pass) ? $username . ':' . $pass : $username;
            return "smtp://$authString@" . $host . ":" . $options['smtp_port'];
        } else {
            return "smtp://" . $host . ":" . $options['smtp_port'];
        }
    }
}

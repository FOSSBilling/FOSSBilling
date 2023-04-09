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

class Box_Mail
{
    protected $di = null;

    private Email $email;
    private $method = null;
    private $dsn = null;

    /**
     * @param Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }

    public function __construct(string $from, string|null $from_name, string $to, string $subject, string $bodyHTML, string|null $method, string|null $dsn = null)
    {
        if (empty($from_name)) {
            $from = new Address($from);
        } else {
            $from = new Address($from, $from_name);
        }

        $this->email = (new Email())
            ->from($from)
            ->to($to)
            ->priority(Email::PRIORITY_NORMAL)
            ->subject($subject)
            ->html($bodyHTML);
        $this->method = $method;
        $this->dsn = $dsn ?? null;
    }

    public function addTo(string|array $toAddresses)
    {
        $this->email->addto($toAddresses);
    }

    public function addCc(string|array $ccAddresses)
    {
        $this->email->addCc($ccAddresses);
    }

    public function addBcc(string|array $bccAddresses)
    {
        $this->email->addBcc($bccAddresses);
    }

    public function addReplyto(string|array $replyToAddresses)
    {
        $this->email->addReplyTo($replyToAddresses);
    }

    public function setPriority(int $priority)
    {
        if (is_int($priority) && $priority >= 1 && $priority <= 5) {
            $this->email->priority($priority);
        } else {
            throw new \Box_Exception("Provided priority (:priority) is invalid. Please provide an integer between 1 and 5 or use the pre-defined symfony constants.", [':priority' => $priority]);
        }
    }

    public function send(array|null $options = null)
    {
        switch ($this->method) {
            case 'sendmail':
                $dsn = 'sendmail://default';
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
            case null:
                if (empty($this->dsn)) {
                    throw new \Box_Exception("Error: No transport method was provided and the custom DSN is empty");
                }
                #See: https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport
                $dsn = $this->dsn;
                break;
            default:
                throw new \Box_Exception('Unknown mail transport: :transport', [':transport' => $this->method]);
                break;
        }

        try {
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);
            $mailer->send($this->email);
        } catch (TransportExceptionInterface $e) {
            error_log("Failed to send email via $this->method with the exception $e");
        }
    }

    /**
     * @param array $options List of 
     * @return string 
     * @throws Box_Exception 
     */
    private function __smtpDsn(array $options)
    {
        if (empty($options['smtp_host']) || empty($options['smtp_port'])) {
            throw new \Box_Exception('SMTP host or port is not configured');
        }

        $host = urlencode($options['smtp_host']);

        if (!empty($options['smtp_username'])) {
            $username = urlencode($options['smtp_username']);
            $pass = urlencode($options['smtp_password'] ?? '');

            $authString = !empty($pass) ? $username . ':' . $pass : $username;
            return "smtp://$authString@" . $host . ":" . $options['smtp_port'];
        } else {
            return "smtp://" . $host . ":" . $options['smtp_port'];
        }
    }
}

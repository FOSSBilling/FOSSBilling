<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Spamchecker;

use EmailChecker\Adapter;
use EmailChecker\Utilities;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public static function onBeforeClientSignUp(\Box_Event $event)
    {
        $di = $event->getDi();
        $spamCheckerService = $di['mod_service']('Spamchecker');
        $spamCheckerService->isBlockedIp($event);
        $spamCheckerService->isSpam($event);
        $spamCheckerService->isTemp($event);
    }

    public static function onBeforeGuestPublicTicketOpen(\Box_Event $event)
    {
        $di = $event->getDi();
        $spamCheckerService = $di['mod_service']('Spamchecker');
        $spamCheckerService->isBlockedIp($event);
        $spamCheckerService->isSpam($event);
        $spamCheckerService->isTemp($event);
    }

    /**
     * @param \Box_Event $event
     */
    public function isBlockedIp($event)
    {
        $di = $event->getDi();
        $config = $di['mod_config']('Spamchecker');
        if (isset($config['block_ips']) && $config['block_ips'] && isset($config['blocked_ips'])) {
            $blocked_ips = explode(PHP_EOL, $config['blocked_ips']);
            $blocked_ips = array_map('trim', $blocked_ips);
            if (in_array($di['request']->getClientAddress(), $blocked_ips)) {
                throw new \FOSSBilling\InformationException('Your IP address (:ip) is blocked. Please contact our support to lift your block.', [':ip' => $di['request']->getClientAddress()], 403);
            }
        }
    }

    public function isSpam(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        $data = [
            'ip' => $params['ip'] ?? null,
            'email' => $params['email'] ?? null,
            'recaptcha_challenge_field' => $params['recaptcha_challenge_field'] ?? null,
            'recaptcha_response_field' => $params['recaptcha_response_field'] ?? null,
        ];

        $config = $di['mod_config']('Spamchecker');

        if (isset($config['captcha_enabled']) && $config['captcha_enabled']) {
            if (isset($config['captcha_version']) && $config['captcha_version'] == 2) {
                if (!isset($config['captcha_recaptcha_privatekey']) || $config['captcha_recaptcha_privatekey'] == '') {
                    throw new \FOSSBilling\InformationException("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>here</a>");
                }

                if (!isset($params['g-recaptcha-response']) || $params['g-recaptcha-response'] == '') {
                    throw new \FOSSBilling\InformationException('You have to complete the CAPTCHA to continue');
                }

                $client = HttpClient::create(['bindto' => BIND_TO]);
                $response = $client->request('POST', 'https://google.com/recaptcha/api/siteverify', [
                    'body' => [
                        'secret' => $config['captcha_recaptcha_privatekey'],
                        'response' => $params['g-recaptcha-response'],
                        'remoteip' => $di['request']->getClientAddress(),
                    ],
                ]);
                $content = $response->toArray();

                if (!$content['success']) {
                    throw new \FOSSBilling\InformationException('reCAPTCHA verification failed.');
                }
            } else {
                throw new \FOSSBilling\InformationException('reCAPTCHA verification failed.');
            }
        }

        if (isset($config['sfs']) && $config['sfs']) {
            $spamCheckerService = $di['mod_service']('Spamchecker');
            $spamCheckerService->isInStopForumSpamDatabase($data);
        }
    }

    public function isTemp(\Box_Event $event)
    {
        $di = $event->getDi();
        $config = $di['mod_config']('Spamchecker');

        $check = $config['check_temp_emails'] ?? false;
        if ($check) {
            $spamCheckerService = $di['mod_service']('Spamchecker');
            $params = $event->getParameters();
            $email = $params['email'] ?? '';

            $spamCheckerService->isATempEmail($email, true);
        }
    }

    /**
     * Pass params:.
     *
     * ip
     * email
     * username
     *
     * @return bool
     */
    public function isInStopForumSpamDatabase(array $data)
    {
        $data['f'] = 'json';
        $url = 'https://www.stopforumspam.com/api?' . http_build_query($data);
        $file_contents = file_get_contents($url);

        $json = json_decode($file_contents);
        if (!is_object($json) || isset($json->success) && !$json->success) {
            return false;
        }

        if (isset($json->username->appears) && $json->username->appears) {
            throw new \FOSSBilling\InformationException('Your username is blacklisted in the Stop Forum Spam database');
        }
        if (isset($json->email->appears) && $json->email->appears) {
            throw new \FOSSBilling\InformationException('Your e-mail is blacklisted in the Stop Forum Spam database');
        }
        if (isset($json->ip->appears) && $json->ip->appears) {
            throw new \FOSSBilling\InformationException('Your IP address is blacklisted in the Stop Forum Spam database');
        }

        return false;
    }

    /**
     * Checks if a provided email address is using a disposable email service.
     *
     * @param string $email the email address to check
     * @param bool   $throw (optional) Configures if you want the function to throw an exception. Defaults to true.
     *
     * @return bool true if the email address is disposable, false if it isn't
     */
    public function isATempEmail(string $email, bool $throw = true): bool
    {
        /*
         * The EmailChecker package utilizes PHP's email verification which does not correctly validate international email addresses.
         * We are already using a proper validation package that does validate these as it should, so below is actually a workaround for the limitation.
         * @see https://github.com/MattKetmo/EmailChecker/issues/92
         *
         * Without this workaround, FOSSBilling would be unable to accept international email addresses when disposable email checking is enabled.
         */
        $adapter = new Adapter\ArrayAdapter($this->getTempMailDomainDB());

        try {
            [$local, $domain] = Utilities::parseEmailAddress($email);
        } catch (\Exception) {
            // Just to be on the safe side, assume the email is valid if there was an error.
            return false;
        }
        $invalid = $adapter->isThrowawayDomain($domain);

        if ($invalid && $throw) {
            throw new \FOSSBilling\InformationException('Disposable email addresses are not allowed');
        }

        return $invalid;
    }

    /**
     * Fetches the most recent list of disposable email addresses, parses them to remove blanks or invalid domains, and then returns it as an array.
     * The database is from here: https://github.com/7c/fakefilter
     * Results are cached for 1 week unless there's an error at which point the list will be retried in a half hour.
     */
    private function getTempMailDomainDB(): array
    {
        return $this->di['cache']->get('tempMailDB', function (ItemInterface $item) {
            $item->expiresAfter(86400); // The list is updated once every 24 hours, so we will cache it for that long

            $client = HttpClient::create(['bindto' => BIND_TO]);
            $response = $client->request('GET', 'https://raw.githubusercontent.com/7c/fakefilter/main/txt/data.txt');
            $dbPath = PATH_CACHE . DIRECTORY_SEPARATOR . 'tempEmailDB.txt';

            if ($response->getStatusCode() === 200) {
                @file_put_contents($dbPath, $response->getContent());
            } else {
                $item->expiresAfter(3600);

                return [];
            }

            @$database = file($dbPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            @unlink($dbPath);
            if (!$database) {
                $item->expiresAfter(3600);

                return [];
            }

            return array_filter($database, fn ($domain): bool => !str_starts_with($domain, '#') && filter_var($domain, FILTER_VALIDATE_DOMAIN));
        });
    }
}

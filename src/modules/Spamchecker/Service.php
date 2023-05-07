<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Spamchecker;

use \FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

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
    }

    public static function onBeforeGuestPublicTicketOpen(\Box_Event $event)
    {
        $di = $event->getDi();
        $spamCheckerService = $di['mod_service']('Spamchecker');
        $spamCheckerService->isBlockedIp($event);
        $spamCheckerService->isSpam($event);
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
                throw new \Box_Exception('Your IP address (:ip) is blocked. Please contact our support to lift your block.', [':ip' => $di['request']->getClientAddress()], 403);
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
            if (isset($config['captcha_version']) && 2 == $config['captcha_version']) {
                if (!isset($config['captcha_recaptcha_privatekey']) || '' == $config['captcha_recaptcha_privatekey']) {
                    throw new \Box_Exception("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>here</a>");
                }

                if (!isset($params['g-recaptcha-response']) || '' == $params['g-recaptcha-response']) {
                    throw new \Box_Exception('You have to complete the CAPTCHA to continue');
                }

                $client = HttpClient::create();
                $response = $client->request('POST', 'https://google.com/recaptcha/api/siteverify', [
                    'body'  => [
                        'secret' => $config['captcha_recaptcha_privatekey'],
                        'response' => $params['g-recaptcha-response'],
                        'remoteip' => $di['request']->getClientAddress(),
                    ],
                ]);
                $content = $response->toArray();

                if (!$content['success']) {
                    throw new \Box_Exception('reCAPTCHA verification failed.');
                }
            } else {
                throw new \Box_Exception('reCAPTCHA verification failed.');
            }
        }

        if (isset($config['sfs']) && $config['sfs']) {
            $spamCheckerService = $di['mod_service']('Spamchecker');
            $spamCheckerService->isInStopForumSpamDatabase($data);
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
            throw new \Box_Exception('Your username is blacklisted in the Stop Forum Spam database');
        }
        if (isset($json->email->appears) && $json->email->appears) {
            throw new \Box_Exception('Your e-mail is blacklisted in the Stop Forum Spam database');
        }
        if (isset($json->ip->appears) && $json->ip->appears) {
            throw new \Box_Exception('Your IP address is blacklisted in the Stop Forum Spam database');
        }

        return false;
    }
}

<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Spamchecker;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{

    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function forumSpamChecker(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        $client = $di['db']->load('Client', $params['client_id']);
        $comment = array(
            'comment_type'              => 'comment',
            'comment_author'            => $client->first_name . ' ' . $client->last_name,
            'comment_author_email'      => $client->email,
            'comment_content'           => $params['message'],
        );

        $spamCheckerService = $di['mod_service']('Spamchecker');
        $spamCheckerService->isCommentSpam($event, $comment);
        $spamCheckerService->isBlockedIp($event);
    }

    public static function onBeforeClientCreateForumTopic(\Box_Event $event)
    {
        $di = $event->getDi();
        $spamCheckerService = $di['mod_service']('Spamchecker');
        $spamCheckerService->forumSpamChecker($event);
    }
    
    public static function onBeforeClientRepliedInForum(\Box_Event $event)
    {
        $di = $event->getDi();
        $spamCheckerService = $di['mod_service']('Spamchecker');
        $spamCheckerService->forumSpamChecker($event);
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
        if(isset($config['block_ips']) && $config['block_ips'] && isset($config['blocked_ips'])) {
            $blocked_ips = explode(PHP_EOL, $config['blocked_ips']);
            $blocked_ips = array_map('trim', $blocked_ips);
            if(in_array($di['tools']->getIpv4(), $blocked_ips)) {
                throw new \Box_Exception('IP :ip is blocked', array(':ip'=>$di['tools']->getIpv4()), 403);
            }
        }
    }

    /**
     * @param \Box_Event $event
     */
    public function isCommentSpam($event, $comment)
    {
        $di = $event->getDi();
        $config = $di['mod_config']('Spamchecker');
        if(!isset($config['akismet_enabled']) || !$config['akismet_enabled']) {
            return false;
        }
        
        require_once BB_PATH_MODS . '/Spamchecker/akismet.curl.class.php';
        
        $akismet = new \akismet($config['akismet_api_key'], $di['config']['url']);
        if(!$akismet->valid_key()) {
            $extensionService = $di['mod_service']('Extension');
            if($extensionService->isExtensionActive('mod', 'notification')) {
                $notificationService = $di['mod_service']('Notification');
                $notificationService->create('Akismet Key is not valid!');
            } else {
                error_log('Akismet Key is not valid!');
            }
            return false;
        }

        if($akismet->is_spam($comment)) {
            throw new \Box_Exception('Akismet detected this message is spam');
        }
    }

    public function isSpam(\Box_Event $event)
    {
        $di = $event->getDi();
        $params      = $event->getParameters();
        $data = array(
            'ip'                        =>  $this->di['array_get']($params, 'ip', NULL),
            'email'                     =>  $this->di['array_get']($params, 'email', NULL),
            'recaptcha_challenge_field' =>  $this->di['array_get']($params, 'recaptcha_challenge_field', NULL),
            'recaptcha_response_field'  =>  $this->di['array_get']($params, 'recaptcha_response_field', NULL),
        );

        $config = $di['mod_config']('Spamchecker');

        if (isset($config['captcha_enabled']) && $config['captcha_enabled']) {
            if (isset($config['captcha_version']) && $config['captcha_version'] == 2) {

                if (!isset($config['captcha_recaptcha_privatekey']) || $config['captcha_recaptcha_privatekey'] == '') {
                    throw new \Box_Exception("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>");
                }

                if (!isset($params['g-recaptcha-response']) || $params['g-recaptcha-response'] == '') {
                    throw new \Box_Exception("No response received");
                }

                $postData = array(
                    'secret'   => $config['captcha_recaptcha_privatekey'],
                    'response' => $params['g-recaptcha-response'],
                    'remoteip' => $di['request']->getClientAddress()
                );
                $request  = $di['guzzle_client']->post('https://www.google.com/recaptcha/api/siteverify', null, $postData);
                $response = $di['guzzle_client']->send($request)->json();

                if (!$response['success']) {
                    throw new \Box_Exception('Captcha verification failed.');
                }

            } else {

                $privatekey = $config['captcha_recaptcha_privatekey'];

                if ($privatekey == null || $privatekey == '') {
                    throw new \Box_Exception("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>");
                }

                require_once BB_PATH_MODS . '/Spamchecker/recaptchalib.php';
                $resp = recaptcha_check_answer($privatekey,
                                               $data['ip'],
                                               $data["recaptcha_challenge_field"],
                                               $data["recaptcha_response_field"]);
                if (!$resp->is_valid) {
                    error_log($resp->error);
                    throw new \Box_Exception('Captcha verification failed.');
                }
            }
        }
        
        if(isset($config['sfs']) && $config['sfs']) {
            $spamCheckerService = $di['mod_service']('Spamchecker');
            $spamCheckerService->isInStopForumSpamDatabase($data);
        }
    }
    
    /**
     * Pass params:
     *
     * ip
     * email
     * username
     *
     * @param array $data
     * @return bool
     */
    public function isInStopForumSpamDatabase(array $data)
    {
        $data['f'] = 'json';
        $url = 'http://www.stopforumspam.com/api?'.http_build_query($data);
        $file_contents = $this->di['tools']->file_get_contents($url);

        $json = json_decode($file_contents);
        if(!is_object($json) || isset($json->success) && !$json->success) {
            return false;
        }

        if(isset($json->username->appears) && $json->username->appears) {
            throw new \Box_Exception('Your Username is blacklisted in global database');
        }
        if(isset($json->email->appears) && $json->email->appears) {
            throw new \Box_Exception('Your Email is blacklisted in global database');
        }
        if(isset($json->ip->appears) && $json->ip->appears) {
            throw new \Box_Exception('Your IP is blacklisted in global database');
        }

        return false;
    }
}
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
class Service
{
    public static function onBeforeClientCreateForumTopic(\Box_Event $event)
    {
        $api    = $event->getApiAdmin();
        $params = $event->getParameters();
        $client = $api->client_get(array('id'=>$params['client_id']));
        $comment = array(
            'comment_type'              => 'comment',
            'comment_author'            => $client['first_name'] . ' ' . $client['last_name'],
            'comment_author_email'      => $client['email'],
            'comment_content'           => $params['message'],
        );
        self::_isCommentSpam($event, $comment);
        self::_isBlockedIp($event);
    }
    
    public static function onBeforeClientRepliedInForum(\Box_Event $event)
    {
        $api         = $event->getApiAdmin();
        $params = $event->getParameters();
        $client = $api->client_get(array('id'=>$params['client_id']));
        $comment = array(
            'comment_type'              => 'comment',
            'comment_author'            => $client['first_name'] . ' ' . $client['last_name'],
            'comment_author_email'      => $client['email'],
            'comment_content'           => $params['message'],
        );
        self::_isCommentSpam($event, $comment);
        self::_isBlockedIp($event);
    }
    
    public static function onBeforeClientSignUp(\Box_Event $event)
    {
        self::_isBlockedIp($event);
        self::_isSpam($event);
    }

    public static function onBeforeGuestPublicTicketOpen(\Box_Event $event)
    {
        self::_isBlockedIp($event);
        self::_isSpam($event);
    }

    private static function _isBlockedIp($event)
    {
        $api = $event->getApiAdmin();
        $config      = $api->extension_config_get(array("ext"=>"mod_spamchecker"));
        if(isset($config['block_ips']) && $config['block_ips'] && isset($config['blocked_ips'])) {
            $blocked_ips = explode(PHP_EOL, $config['blocked_ips']);
            array_walk($blocked_ips, create_function('&$val', '$val = trim($val);'));
            if(in_array(Box_Tools::getIpv4(), $blocked_ips)) {
                throw new \Box_Exception('IP :ip is blocked', array(':ip'=>Box_Tools::getIpv4()), 403);
            }
        }
    }

    private static function _isCommentSpam($event, $comment)
    {
        $api = $event->getApiAdmin();
        
        $config      = $api->extension_config_get(array("ext"=>"mod_spamchecker"));
        if(!isset($config['akismet_enabled']) or !$config['akismet_enabled']) {
            return false;
        }
        
        require_once BB_PATH_MODS . '/Spamchecker/akismet.curl.class.php';
        
        $akismet = new akismet($config['akismet_api_key'], BB_URL);
        if(!$akismet->valid_key()) {
            if($event->getApiGuest()->extension_is_on(array('mod'=>'notification'))) {
                $api->notification_add(array('message'=>'Akismet Key is not valid!'));
            } else {
                error_log('Akismet Key is not valid!');
            }
            return false;
        }

        if($akismet->is_spam($comment)) {
            throw new \Box_Exception('Akismet detected this message is spam');
        }
    }
    
    private static function _isSpam(\Box_Event $event)
    {
        $api         = $event->getApiAdmin();
        $params      = $event->getParameters();
        $data = array(
            'ip'                        =>  isset($params['ip']) ? $params['ip'] : NULL,
            'email'                     =>  isset($params['email']) ? $params['email'] : NULL,
            'recaptcha_challenge_field' =>  isset($params['recaptcha_challenge_field']) ? $params['recaptcha_challenge_field'] : NULL,
            'recaptcha_response_field'  =>  isset($params['recaptcha_response_field']) ? $params['recaptcha_response_field'] : NULL,
        );
            
        $config      = $api->extension_config_get(array("ext"=>"mod_spamchecker"));
        
        if(isset($config['captcha_enabled']) && $config['captcha_enabled']) {
            
            $privatekey = $config['captcha_recaptcha_privatekey'];

            if ($privatekey == null || $privatekey == '') {
                throw new \Box_Exception("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>");
            }

            require_once BB_PATH_MODS . '/Spamchecker/recaptchalib.php';
            $resp = recaptcha_check_answer ($privatekey,
                                            $data['ip'],
                                            $data["recaptcha_challenge_field"],
                                            $data["recaptcha_response_field"]);
            if (!$resp->is_valid) {
                error_log($resp->error);
                throw new \Box_Exception('Captcha verification failed.');
            }
        }
        
        if(isset($config['sfs']) && $config['sfs']) {
            self::_isInStopForumSpamDatabase($data);
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
    private static function _isInStopForumSpamDatabase(array $data)
    {
        $data['f'] = 'json';
        $url = 'http://www.stopforumspam.com/api?'.http_build_query($data);
        $file_contents = file_get_contents($url);

        $json = json_decode($file_contents);
        if(!is_object($json) || !$json->success) {
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
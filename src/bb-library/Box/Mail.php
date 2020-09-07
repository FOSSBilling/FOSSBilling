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

/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Box_Mail
{
    private $_bodyHtml  = NULL;
    private $_from      = NULL;
    private $_from_name = NULL;
    private $_subject   = NULL;
    private $_replyTo   = NULL;
    private $_replyTo_name   = NULL;
    private $_to        = NULL;
    private $_headers   = '';

    public function send($transport = 'sendmail', $options = array())
    {
        if($transport == 'sendmail') {
            $this->_sendMail($options);
        } else if($transport == 'smtp') {
            $this->_sendSmtpMail($options);
        } else if($transport == 'sendgrid') {
            $this->_sendSendgrid($options);
        } else {
            throw new \Box_Exception('Unknown mail transport: :transport', array(':transport'=>$transport));
        }
    }

    public function setBodyHtml($param)
    {
        $this->_bodyHtml = $param;
        return $this;
    }

    public function setFrom($email, $name = null)
    {
        $this->_from = $this->_filterEmail($email);
        $this->_from_name = $this->_filterName($name);

        return $this;
    }

    public function getSubject()
    {
        return $this->_subject;
    }
    
    public function getBody()
    {
        return $this->_bodyHtml;
    }

    public function setSubject($subject)
    {
        $this->_subject =  $this->_filterOther($subject);
        return $this;
    }

    public function setReplyTo($email, $name = null)
    {
        $this->_replyTo = $this->_filterEmail($email);
        $this->_replyTo_name = $this->_filterName($name);
        return $this;
    }

    public function addTo($email, $name='')
    {
        $this->_to = $this->_filterEmail($email);
        return $this;
    }
    protected function _sendSendgrid($options)
    {
        if(!isset($options['sendgrid_username']) || !isset($options['sendgrid_password'])) {
            throw new \Box_Exception('Sendgrid is not configured');
        }
        
        $user = isset($options['sendgrid_username']) ? $options['sendgrid_username'] : NULL;
        $pass = isset($options['sendgrid_password']) ? $options['sendgrid_password'] : NULL;
        
        // Create JSON array
        $params = array(
            'api_user'  => $user,
            'api_key'   => $pass,
            'to'        => $this->_to,
            'subject'   => $this->_subject,
            'html'      => $this->_bodyHtml . 'Reply Address: ' . $this->_from,
            'text'      => $this->_bodyHtml . 'Reply Address: ' . $this->_from,
            'from'      => $replyaddy,
        );
        
        // create the request URL
        $request =  'https://api.sendgrid.com/api/mail.send.json';
        
        $session = curl_init($request);
        // Tell curl to use HTTP POST
        curl_setopt ($session, CURLOPT_POST, true);
        // Tell curl that this is the body of the POST
        curl_setopt ($session, CURLOPT_POSTFIELDS, $params);
        // Tell curl not to return headers, but do return the response
        curl_setopt($session, CURLOPT_HEADER, false);
        // Tell PHP not to use SSLv3 (instead opting for TLS)
        curl_setopt($session, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        // obtain response
        $response = curl_exec($session);
        curl_close($session);
        $dat = json_decode($response);
        
        if ($dat->message != "success") {
            error_log("ERROR: Sendgrid email was not successful");
        }
    }
    
    protected function _sendSmtpMail($options)
    {
        if(!isset($options['smtp_host'])) {
            throw new \Box_Exception('SMTP host not configured');
        }
        
        if(!isset($options['smtp_port'])) {
            throw new \Box_Exception('SMTP port not configured');
        }
        
        $user       = isset($options['smtp_username']) ? $options['smtp_username'] : NULL;
        $pass       = isset($options['smtp_password']) ? $options['smtp_password'] : NULL;
        $port       = isset($options['smtp_port']) ? $options['smtp_port'] : NULL;
        $host       = isset($options['smtp_host']) ? $options['smtp_host'] : NULL;
        $security   = isset($options['smtp_security']) ? $options['smtp_security'] : NULL;

        if(empty($host)) {
            throw new \Box_Exception('SMTP hostname is not configured.');
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'utf-8';
        $mail->IsSMTP();     
        $mail->Host         = $host; 
        $mail->SMTPDebug     = 0; 
        
        if($port)
            $mail->Port     = (int)$port;
        
        if($user) {
            $mail->SMTPAuth     = true;
            $mail->SMTPSecure     = $security;
            $mail->Username     = $user;
            $mail->Password     = $pass;
        }
            
        $mail->SetFrom($this->_from, $this->_from_name);
        $mail->AddReplyTo($this->_from);
        $mail->AddAddress($this->_to);

        $mail->Subject  = $this->_subject;
        $mail->MsgHTML($this->_bodyHtml);
        $mail->send();
    }

    protected function _sendMail()
    {
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'utf-8';
            $mail->AddReplyTo($this->_from, $this->_from_name);
            $mail->SetFrom($this->_from, $this->_from_name);
            $mail->AddAddress($this->_to);
            $mail->Subject    = $this->_subject;
            $mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
            $mail->MsgHTML($this->_bodyHtml);
            $mail->Send();
        } catch(Exception $e) {
            error_log($e->getMessage());

            //simple mail sending
            $subject = "=?utf-8?B?".base64_encode($this->_subject)."?=";
            $this->addHeader('From', $this->_from);
            $this->addHeader('Reply-To', $this->_replyTo);
            $this->addHeader('Return-Path', $this->_from);
            $this->addHeader('Content-type', 'text/html;charset=utf-8');
            $this->addHeader('Content-Transfer-Encoding', '8bit');
            $this->addHeader('X-mailer', 'BoxBilling/'.Box_Version::VERSION);
            mail($this->_to,$subject,$this->_bodyHtml,$this->_headers);
        }
    }

    private function addHeader($name, $value)
    {
        $this->_headers .= $name.": ".$value."\r\n";
    }

    /**
     * Temporary error handler for PHP native mail().
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     * @param array  $errcontext
     * @return true
     */
    public function _handleMailErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        throw new \Box_Exception($errstr);
    }

    /**
     * Filter of email data
     *
     * @param string $email
     * @return string
     */
    private function _filterEmail($email)
    {
        $rule = array("\r" => '',
                      "\n" => '',
                      "\t" => '',
                      '"'  => '',
                      ','  => '',
                      '<'  => '',
                      '>'  => '',
        );

        return strtr($email, $rule);
    }

    /**
     * Filter of name data
     *
     * @param string $name
     * @return string
     */
    private function _filterName($name)
    {
        $rule = array("\r" => '',
                      "\n" => '',
                      "\t" => '',
                      '"'  => "'",
                      '<'  => '[',
                      '>'  => ']',
        );

        return trim(strtr($name, $rule));
    }

    /**
     * Filter of other data
     *
     * @param string $data
     * @return string
     */
    private function _filterOther($data)
    {
        $rule = array("\r" => '',
                      "\n" => '',
                      "\t" => '',
        );

        return strtr($data, $rule);
    }
}

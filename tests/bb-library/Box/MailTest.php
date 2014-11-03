<?php
/**
 * @group Core
 */
class Box_MailTest extends PHPUnit_Framework_TestCase
{
    public function testMail()
    {
        try {
            $mail = new Box_Mail();
            $mail->setFrom('me@gmail.com');
            $mail->setReplyTo('info@boxbilling.com');
            $mail->addTo('example@gmail.com');
            $mail->setSubject('PHPUnit');
            $mail->setBodyHtml('testing email <b>html</b>');
            $mail->send();
        } catch (Exception $e) {
            print $e->getMessage();
        }
    }

    /*
    public function testSmtp()
    {
        $options = array(
            'smtp_security' =>  'tls',
            'smtp_port'     =>  '587',
            'smtp_username' =>  'john.doe@gmail.com',
            'smtp_password' =>  '',
            'smtp_host'     =>  'smtp.gmail.com',
        );
        $mail = new Box_Mail();
        $mail->setFrom('me@gmail.com');
        $mail->addTo('support@1freehosting.com');
        $mail->setSubject('PHPUnit');
        $mail->setBodyHtml('testing email <b>html</b>');
        $mail->send('smtp', $options);
    }
    */
}
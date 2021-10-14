<?php
/**
 * @group Core
 */
class Box_MailTest extends PHPUnit\Framework\TestCase
{

    public function testsend_TransportSendMail()
    {
        $transport = 'sendmail';

        $mailMock = $this->getMockBuilder(Box_Mail::class)
            ->setMethods(array('_sendMail'))
            ->getMock();

        $mailMock->expects($this->once())
            ->method('_sendMail')
            ->with(array());

        $mailMock->send($transport);
    }

    public function testsend_TransportSmtp()
    {
        $transport = 'smtp';

        $mailMock = $this->getMockBuilder('Box_Mail')
            ->setMethods(array('_sendSmtpMail'))
            ->getMock();

        $mailMock->expects($this->once())
            ->method('_sendSmtpMail')
            ->with(array());

        $mailMock->send($transport);
    }

    public function testsend_TransportUnknown()
    {
        $transport = 'mailServer';

        $mail = new Box_Mail();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Unknown mail transport: %s', $transport));

        $mail->send($transport);
    }

    public function testsetBodyHtml()
    {
        $context = 'Mail body';
        $mail = new Box_Mail();

        $result = $mail->setBodyHtml($context);
        $this->assertInstanceOf(Box_Mail::class, $result);

        $result = $mail->getBody();
        $this->assertEquals($context, $result);
    }

    public function testsetFrom()
    {
        $context = 'jobs@boxbilling.com';
        $mail = new Box_Mail();

        $result = $mail->setFrom($context);

        $this->assertInstanceOf(Box_Mail::class, $result);
    }

    public function testsetSubject()
    {
        $context = 'Mail title';
        $mail = new Box_Mail();

        $result = $mail->setSubject($context);
        $this->assertInstanceOf(Box_Mail::class, $result);

        $result = $mail->getSubject();
        $this->assertEquals($context, $result);
    }

    public function testsetReplyTo()
    {
        $context = 'do-not-reply@boxbilling.com';
        $mail = new Box_Mail();

        $result = $mail->setReplyTo($context);
        $this->assertInstanceOf(Box_Mail::class, $result);
    }

    public function testaddTo()
    {
        $context = 'bcc@boxbilling.com';
        $mail = new Box_Mail();

        $result = $mail->addTo($context);
        $this->assertInstanceOf(Box_Mail::class, $result);
    }
}
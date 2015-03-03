<?php
namespace Box\Tests\Mod\Email\Api;

class AdminTest extends \PHPUnit_Framework_TestCase
{

    public function testEmail_get_list()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = new \Box\Mod\Email\Service();

        $willReturn = array(
            "list" => array(
                'id' => 1
            ),
        );


        $pager = $this->getMockBuilder('Box_Pagination')->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($willReturn));

        $di          = new \Box_Di();
        $di['pager'] = $pager;

        $adminApi->setDi($di);

        $service = $emailService;
        $adminApi->setService($service);

        $result = $adminApi->email_get_list(array());
        $this->assertInternalType('array', $result);

        $this->assertArrayHasKey('list', $result);
        $this->assertInternalType('array', $result['list']);
    }


    public function testEmail_get()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data         = array(
            'id' => 1
        );
        $id           = 10;
        $client_id    = 5;
        $sender       = 'sender@example.com';
        $recipients   = 'recipient@example.com';
        $subject      = 'Subject';
        $content_html = 'HTML';
        $content_text = 'TEXT';
        $created      = date('Y-m-d H:i:s', time() - 86400);
        $updated      = date('Y-m-d H:i:s');

        $model               = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id           = $id;
        $model->client_id    = $client_id;
        $model->sender       = $sender;
        $model->recipients   = $recipients;
        $model->subject      = $subject;
        $model->content_html = $content_html;
        $model->content_text = $content_text;
        $model->created_at   = $created;
        $model->updated_at   = $updated;

        $expected = array(
            'id'           => $id,
            'client_id'    => $client_id,
            'sender'       => $sender,
            'recipients'   => $recipients,
            'subject'      => $subject,
            'content_html' => $content_html,
            'content_text' => $content_text,
            'created_at'   => $created,
            'updated_at'   => $updated,
        );

        $service = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('getEmailById', 'toApiArray'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getEmailById')
            ->will($this->returnValue($model));
        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue($expected));

        $adminApi->setService($service);

        $result = $adminApi->email_get($data);

        $this->assertInternalType('array', $result);
        $this->assertEquals($result, $expected);
    }

    public function testSend()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'to'        => 'to@example.com',
            'to_name'   => 'Recipient Name',
            'from'      => 'from@example.com',
            'from_name' => 'Sender Name',
            'subject'   => 'Subject',
            'content'   => 'Content'
        );

        $model     = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;

        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('sendMail'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendMail')
            ->will($this->returnValue(true));


        $adminApi->setService($emailService);

        $result = $adminApi->send($data);

        $this->assertTrue($result);
    }

    public function testSendExceptionsProvider()
    {
        return array(
            array(
                array()
            ),
            array(
                array(
                    'to' => 'to@example.com',
                )
            ),
            array(
                array(
                    'to'      => 'to@example.com',
                    'to_name' => 'Recipient Name',
                )
            ),
            array(
                array(
                    'to'      => 'to@example.com',
                    'to_name' => 'Recipient Name',
                    'from'    => 'from@example.com',
                )
            ),
            array(
                array(
                    'to'        => 'to@example.com',
                    'to_name'   => 'Recipient Name',
                    'from'      => 'from@example.com',
                    'from_name' => 'Sender Name',
                )
            ),
            array(
                array(
                    'to'        => 'to@example.com',
                    'to_name'   => 'Recipient Name',
                    'from'      => 'from@example.com',
                    'from_name' => 'Sender Name',
                    'subject'   => 'Subject',
                )
            )
        );
    }

    /**
     * @dataProvider testSendExceptionsProvider
     * @expectedException \Box_Exception
     */
    public function testSendExceptions($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $emailService = new \Box\Mod\Email\Service();
        $adminApi->setService($emailService);

        $adminApi->send($data);
    }

    public function testResend()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'id' => 1
        );

        $model     = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $adminApi->setDi($di);

        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('resend'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('resend')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $result = $adminApi->email_resend($data);

        $this->assertTrue($result);
    }

    public function testResend_ExceptionEmailNotFound()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'id' => 1
        );

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $adminApi->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Email not found');
        $adminApi->email_resend($data);
    }


    public function testDelete_ExceptionEmailNotFound()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'id' => 1
        );

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $adminApi->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Email not found');
        $adminApi->email_delete($data);
    }


    public function testEmail_delete()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = new \Box\Mod\Email\Service();

        $data = array(
            'id' => 1
        );

        $model     = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(true));

        $loggerMock = $this->getMockBuilder('Box_Log')->getMock();

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $loggerMock;
        $adminApi->setDi($di);

        $adminApi->setService($emailService);

        $result = $adminApi->email_delete($data);

        $this->assertTrue($result);
    }


    public function testTemplate_get_list()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = new \Box\Mod\Email\Service();

        $willReturn = array(
            "list"     => array(
                'id' => 1
            ),
        );

        $pager = $this->getMockBuilder('Box_Pagination')->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($willReturn));

        $di = new \Box_Di();
        $di['pager'] = $pager;

        $adminApi->setDi($di);

        $service = $emailService;
        $adminApi->setService($service);

        $result = $adminApi->template_get_list(array());
        $this->assertInternalType('array', $result);

        $this->assertArrayHasKey('list', $result);
        $this->assertInternalType('array', $result['list']);
    }

    public function testTemplate_get()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'id' => 1
        );

        $model = new \Model_EmailTemplate();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $db = $this->getMockBuilder('\Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $adminApi->setDi($di);

        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('templateToApiArray'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateToApiArray')
            ->will($this->returnValue(array()));
        $adminApi->setService($emailService);

        $result = $adminApi->template_get($data);
        $this->assertInternalType('array', $result);
    }

    public function testTemplate_delete()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'id' => 1
        );

        $model     = new \Model_EmailTemplate();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $loggerMock = $this->getMockBuilder('Box_Log')->getMock();

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $loggerMock;
        $adminApi->setDi($di);

        $result = $adminApi->template_delete($data);
        $this->assertTrue($result);
    }

    public function testtemplate_delete_TemplateNotFound()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $data = array(
            'id' => 1
        );

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $adminApi->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Email template not found');
        $adminApi->template_delete($data);
    }


    public function testTemplate_create()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $modelId = rand(1, 100);

        $templateModel = new \Model_EmailTemplate();
        $templateModel->loadBean(new \RedBeanPHP\OODBBean());
        $templateModel->id = $modelId;

        $data    = array(
            'action_code' => 'Action_code',
            'subject'     => 'Subject',
            'content'     => 'Content'
        );

        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('templateCreate'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateCreate')
            ->will($this->returnValue($templateModel));
        $adminApi->setService($emailService);

        $result = $adminApi->template_create($data);
        $this->assertEquals($result, $modelId);
    }

    public function testTemplate_createExceptionsProvider()
    {
        return array(
            array(
                array()
            ),
            array(
                array(
                    'action_code' => 'action_code',
                )
            ),
            array(
                array(
                    'action_code' => 'action_code',
                    'subject'     => 'Subject',
                )
            ),
        );
    }

    /**
     * @dataProvider testTemplate_createExceptionsProvider
     * @expectedException \Box_Exception
     */
    public function testTemplate_createExceptions($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $emailService = new \Box\Mod\Email\Service();
        $adminApi->setService($emailService);

        $adminApi->template_create($data);
    }

    public function testIdNotSetExceptionsProvider()
    {
        return array(
            array(
                array() // "id" is not set
            ),
            array(
                array(
                    'id' => '' // "id" is set, but it is empty
                )
            )
        );
    }

    /**
     * @expectedException \Box_Exception
     * @dataProvider testIdNotSetExceptionsProvider
     */
    public function testTemplate_updateIdNotSetException($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->template_update($data);

    }

    /**
     * @expectedException \Box_Exception
     * @dataProvider testIdNotSetExceptionsProvider
     */
    public function testTemplate_deleteIdNotSetExceptions($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->template_delete($data);

    }

    /**
     * @expectedException \Box_Exception
     * @dataProvider testIdNotSetExceptionsProvider
     */
    public function testTemplate_getIdNotSetException($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $adminApi->template_get($data);

    }

    /**
     * @expectedException \Box_Exception
     * @dataProvider testIdNotSetExceptionsProvider
     */
    public function testEmail_deleteIdNotSetException($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->email_delete($data);
    }

    /**
     * @expectedException \Box_Exception
     * @dataProvider testIdNotSetExceptionsProvider
     */
    public function testEmail_resendIdNotSetException($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->email_resend($data);
    }

    /**
     * @expectedException \Box_Exception
     * @dataProvider testIdNotSetExceptionsProvider
     */
    public function testEmail_getIdNotSetException($data)
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->email_get($data);
    }

 /**
     * @expectedException \Box_Exception
     */
    public function testTemplate_resetCodeNotSetException()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->template_reset(array());
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testTemplate_sendCodeNotSetException()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->template_send(array());
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testTemplate_sendToNotSetException()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();
        $adminApi->template_send(array('code' => 'code'));
    }

    public function testTemplate_update()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $id = rand(1, 100);
        $data = array(
            'id'          => $id,
            'enabled'     => '1',
            'category'    => 'Category',
            'action_code' => 'Action_code',
            'category'    => '',
            'subject'     => 'Subject',
            'content'     => 'Content'
        );

        $emailTemplateModel = new \Model_EmailTemplate();
        $emailTemplateModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($emailTemplateModel));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('updateTemplate'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('updateTemplate')
            ->with($emailTemplateModel, $data['enabled'], $data['category'], $data['subject'], $data['content'])
            ->will($this->returnValue(true));
        $adminApi->setService($emailService);
        $adminApi->setDi($di);

        $result = $adminApi->template_update($data);
        $this->assertEquals($result, true);
    }

    public function testTemplate_reset()
    {
        $adminApi = new \Box\Mod\Email\Api\Admin();

        $id = rand(1, 100);
        $data = array(
            'code' => 'CODE'
        );

        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('resetTemplateByCode'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('resetTemplateByCode')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $result = $adminApi->template_reset($data);
        $this->assertEquals($result, $id);
    }

    public function testBatch_template_generate()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('templateBatchGenerate'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateBatchGenerate')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $result = $adminApi->batch_template_generate();
        $this->assertTrue($result);
    }

    public function testBatch_template_disable()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('templateBatchDisable'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateBatchDisable')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $result = $adminApi->batch_template_disable(array());
        $this->assertTrue($result);
    }

    public function testBatch_template_enable()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('templateBatchEnable'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('templateBatchEnable')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $result = $adminApi->batch_template_enable(array());
        $this->assertTrue($result);
    }

    public function testSend_test()
    {
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('sendTemplate'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $result = $adminApi->send_test(array());
        $this->assertTrue($result);
    }

    public function testBatch_sendmail(){
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('batchSend'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('batchSend')
            ->will($this->returnValue(null));

        $adminApi->setService($emailService);

        $result = $adminApi->batch_sendmail();
        $this->assertNull($result);
    }

    public function testTemplate_send(){
        $adminApi     = new \Box\Mod\Email\Api\Admin();
        $emailService = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('sendTemplate'))->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->will($this->returnValue(true));

        $adminApi->setService($emailService);

        $data    = array(
            'code'                => 'mod_email_test',
            'to'                  => 'example@example.com',
            'default_subject'     => 'SUBJECT',
            'default_template'    => 'TEMPLATE',
            'default_description' => 'DESCRIPTION',
        );

        $result = $adminApi->template_send($data);
        $this->assertTrue($result);
    }

    public function testTemplate_render()
    {

        $adminApi = $this->getMockBuilder('Box\Mod\Email\Api\Admin')->setMethods(array('template_get'))->getMock();
        $adminApi->expects($this->atLeastOnce())
            ->method('template_get')
            ->will($this->returnValue(array('vars' => array(), 'content' => 'content')));

        $twig = $this->getMockBuilder('Twig_Environment')->getMock();
        $twig->expects($this->atLeastOnce())
            ->method('render')
            ->will($this->returnValue('rendered'));

        $di         = new \Box_Di();
        $di['twig'] = $twig;
        $adminApi->setDi($di);

        $result = $adminApi->template_render(array('id' => 5));
        $this->assertEquals($result, 'rendered');
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Email\Api\Admin')->setMethods(array('email_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('email_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }

}

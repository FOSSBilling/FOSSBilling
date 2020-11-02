<?php
namespace Box\Tests\Mod\Email;

class ServiceTest extends \BBTestCase
{
    public function testDi()
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function getSearchQueryProvider()
    {
        return array(
            array(
                array(),
                'SELECT * FROM activity_client_email ORDER BY id DESC',
                array(),
            ),
            array(
                array(
                    'search' => "search_query"
                ),
                'SELECT * FROM activity_client_email WHERE (sender LIKE :sender OR recipients LIKE :recipient OR subject LIKE :subject OR content_text LIKE :content_text OR content_html LIKE :content_html) ORDER BY id DESC',
                array(
                    ':sender'       => '%search_query%',
                    ':recipient'    => '%search_query%',
                    ':subject'      => '%search_query%',
                    ':content_text' => '%search_query%',
                    ':content_html' => '%search_query%',
                ),
            ),
            array(
                array(
                    'client_id' => 5
                ),
                'SELECT * FROM activity_client_email WHERE client_id = :client_id ORDER BY id DESC',
                array(
                    ':client_id' => 5,
                ),
            ),
            array(
                array(
                    'search'    => "search_query",
                    'client_id' => 5
                ),
                'SELECT * FROM activity_client_email WHERE (sender LIKE :sender OR recipients LIKE :recipient OR subject LIKE :subject OR content_text LIKE :content_text OR content_html LIKE :content_html) AND client_id = :client_id ORDER BY id DESC',
                array(
                    ':sender'       => '%search_query%',
                    ':recipient'    => '%search_query%',
                    ':subject'      => '%search_query%',
                    ':content_text' => '%search_query%',
                    ':content_html' => '%search_query%',
                    ':client_id'    => 5,
                )
            ),
        );
    }

    /**
     * @dataProvider getSearchQueryProvider
     */
    public function testGetSearchQuery($data, $query, $bindings)
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);
        $result = $service->getSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($result[0], $query);
        $this->assertEquals($result[1], $bindings);

    }

    public function testEmailFindOneForClientById()
    {
        $service   = new \Box\Mod\Email\Service();
        $di        = new \Box_Di();
        $id        = 5;
        $client_id = 1;

        $activityEmail            = new \Model_ActivityClientEmail();
        $activityEmail->loadBean(new \RedBeanPHP\OODBBean());
        $activityEmail->client_id = $client_id;
        $activityEmail->id        = $id;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($activityEmail));

        $di['db'] = $db;
        $service->setDi($di);

        $client     = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = $client_id;


        $result = $service->findOneForClientById($client, $id);

        $this->assertInstanceOf('Model_ActivityClientEmail', $result);
        $this->assertNotNull($result->id);
        $this->assertEquals($result->id, $activityEmail->id);
        $this->assertEquals($result->client_id, $activityEmail->client_id);
    }

    public function testEmailRmByClient()
    {
        $service = new \Box\Mod\Email\Service();
        $di      = new \Box_Di();


        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($model)));

        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di['db'] = $db;
        $service->setDi($di);

        $client     = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = 1;


        $result = $service->rmByClient($client);
        $this->assertTrue($result);
    }

    public function testEmailRm()
    {
        $service = new \Box\Mod\Email\Service();
        $di      = new \Box_Di();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di['db'] = $db;
        $service->setDi($di);

        $email     = new \Model_ActivityClientEmail();
        $email->loadBean(new \RedBeanPHP\OODBBean());
        $email->id = 1;

        $result = $service->rm($email);
        $this->assertTrue($result);
    }

    public function testEmailToApiArray()
    {
        $service = new \Box\Mod\Email\Service();

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


        $result = $service->toApiArray($model);
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testSetVars()
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db'] = $db;
        $di['crypt'] = $cryptMock;
        $service->setDi($di);

        $t    = new \stdClass();
        $vars = array(
            'param1' => 'value1'
        );

        $result = $service->setVars($t, $vars);
        $this->assertTrue($result);
    }

    public function testGetVars()
    {
        $service = new \Box\Mod\Email\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');

        $expected = array('param1' => 'value1');
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue($expected));

        $di['db'] = $db;
        $di['tools'] = $toolsMock;
        $di['crypt'] = $cryptMock;
        $service->setDi($di);

        $t       = new \stdClass();
        $t->vars = 'haNUZYeNjo1oXhH6OkoKuHGPxakyKY10qR3O/DSy9Og=';

        $result = $service->getVars($t);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testSendTemplateNotExists()
    {
        $service = new \Box\Mod\Email\Service();
        $di      = new \Box_Di();

        $data = array(
            'code'                => 'mod_email_test_not_existing',
            'to'                  => 'example@example.com',
            'default_subject'     => 'SUBJECT',
            'default_description' => 'DESCRIPTION',
        );

        $emailTemplate = new \Model_EmailTemplate();
        $emailTemplate->loadBean(new \RedBeanPHP\OODBBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($emailTemplate));
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(1));

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db']        = $db;
        $di['crypt'] = $cryptMock;
        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $service->setDi($di);

        $result = $service->sendTemplate($data);

        $this->assertFalse($result);
    }

    public function testSendTemplateExists()
    {
        $data    = array(
            'code'                => 'mod_email_test',
            'to'                  => 'example@example.com',
            'default_subject'     => 'SUBJECT',
            'default_template'    => 'TEMPLATE',
            'default_description' => 'DESCRIPTION',
        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendMail'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di      = new \Box_Di();

        $emailTemplate = new \Model_EmailTemplate();
        $emailTemplate->loadBean(new \RedBeanPHP\OODBBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($emailTemplate));
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(1));

        $systemService = $this->getMockBuilder('Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue('value'));

        
        $twig = $this->getMockBuilder('\Twig\Environment')->disableOriginalConstructor()->getMock();

        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $cryptMock = $this->getMockBuilder('\Box_Crypt')
            ->disableOriginalConstructor()
            ->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');

        $di['db']          = $db;
        $di['crypt']       = $cryptMock;
        $di['twig']        = $twig;
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });
        $di['tools'] = new \Box_Tools();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);

        $result = $serviceMock->sendTemplate($data);

        $this->assertTrue($result);
    }


    public function sendTemplateExistsStaffProvider()
    {
        return array(
            array(
                array(
                    'code'                => 'mod_email_test',
                    'to'                  => 'example@example.com',
                    'default_subject'     => 'SUBJECT',
                    'default_template'    => 'TEMPLATE',
                    'default_description' => 'DESCRIPTION',
                    'to_staff'            => 1,
                ),
                $this->never(),
                $this->atLeastOnce(),
            ),
            array(
                array(
                    'code'                => 'mod_email_test',
                    'to'                  => 'example@example.com',
                    'default_subject'     => 'SUBJECT',
                    'default_template'    => 'TEMPLATE',
                    'default_description' => 'DESCRIPTION',
                    'to_client'           => 1
                ),
                $this->atLeastOnce(),
                $this->never(),
            ),

        );
    }

    /**
     * @dataProvider sendTemplateExistsStaffProvider
     */
    public function testSendTemplateExistsStaff($data, $clientGetExpects, $staffgetListExpects)
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendMail'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendMail')
            ->willReturn(true);

        $di      = new \Box_Di();

        $emailTemplate = new \Model_EmailTemplate();
        $emailTemplate->loadBean(new \RedBeanPHP\OODBBean());

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($emailTemplate));
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(1));

        $system = $this->getMockBuilder('Box\Mod\System\Service')->getMock();
        $system->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue('value'));

        $system->expects($this->atLeastOnce())
        ->method('renderString')
        ->will($this->returnValue('value'));    


        $staffServiceMock = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffServiceMock->expects($staffgetListExpects)
            ->method('getList')
            ->will($this->returnValue(
                array(
                    'list' => array(
                        0 => array(
                            'id'    => 1,
                            'email' => 'staff@boxbilling.com',
                            'name'  => 'George'
                        )
                    )
                )
            ));

        $clientServiceMock = $this->getMockBuilder('Box\Mod\Client\Service')->getMock();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientServiceMock->expects($clientGetExpects)
            ->method('get')
            ->will($this->returnValue($clientModel));
        $clientApiArray = array(
            'id'         => 1,
            'email'      => 'staff@boxbilling.com',
            'first_name' => 'John',
            'last_name'  => 'Smith'
        );
        $clientServiceMock->expects($clientGetExpects)
            ->method('toApiArray')
            ->will($this->returnValue($clientApiArray));
            
        $loader = new \Twig\Loader\ArrayLoader();
        $twig = $this->getMockBuilder('Twig\Environment')->setConstructorArgs([$loader,['debug' => false]])->getMock();
        

        $cryptMock = $this->getMockBuilder('\Box_Crypt')
            ->disableOriginalConstructor()
            ->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt');


        $di['api_admin'] = function () use ($di) {
            $api = new \Api_Handler(new \Model_Admin());
            $api->setDi($di);

            return $api;
        };

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $di['db']          = $db;
        $di['twig']        = $twig;
        $di['crypt']       = $cryptMock;
        $di['mod_service'] = $di->protect(function ($name) use ($system, $staffServiceMock, $clientServiceMock) {
            if ($name == 'staff') {
                return $staffServiceMock;
            } elseif ($name == 'System' || $name == 'system') {
                return $system;
            } elseif ($name == 'client') {
                return $clientServiceMock;
            }
        });
        $di['tools'] = new \Box_Tools();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);

        $result = $serviceMock->sendTemplate($data);

        $this->assertTrue($result);
    }

    public function testResend()
    {
        $service = new \Box\Mod\Email\Service();

        $di       = new \Box_Di();
        $db       = $this->getMockBuilder('Box_Database')->getMock();
        $mailMock = $this->getMockBuilder('Box_Mail')->getMock();

        $emailSettings = array(
            'mailer'              => 'sendmail',
            'smtp_authentication' => 'login',
            'smtp_host'           => NULL,
            'smtp_security'       => NULL,
            'smtp_port'           => NULL,
            'smtp_username'       => NULL,
            'smtp_password'       => NULL,
        );

        $di['db']          = $db;
        $di['mail']        = $mailMock;

        $config = array();
        $di['mod_config']  = $di->protect(function ($modName) use($config){
            return $config;
        });
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);

        $model               = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->client_id    = 1;
        $model->sender       = 'sender@exemple.com';
        $model->recipients   = 'recipient@example.com';
        $model->subject      = 'Email Title';
        $model->content_html = '<b>Content</b>';
        $model->content_text = 'Content';

        $result = $service->resend($model);

        $this->assertTrue($result);
    }

    public function templateGetSearchQueryProvider()
    {
        return array(
            array(
                array(),
                'SELECT * FROM email_template ORDER BY category ASC',
                array()
            ),
            array(
                array(
                    'search' => 'keyword'
                ),
                'SELECT * FROM email_template WHERE (action_code LIKE :action_code OR subject LIKE :subject OR content LIKE :content) ORDER BY category ASC',
                array(
                    ':action_code' => '%keyword%',
                    ':subject'     => '%keyword%',
                    ':content'     => '%keyword%',
                ),
            ),
            array(
                array(
                    'search' => 'keyword',
                    'code'   => 'code'
                ),
                'SELECT * FROM email_template WHERE action_code LIKE :code AND (action_code LIKE :action_code OR subject LIKE :subject OR content LIKE :content) ORDER BY category ASC',
                array(
                    ':code'        => '%code%',
                    ':action_code' => '%keyword%',
                    ':subject'     => '%keyword%',
                    ':content'     => '%keyword%',
                ),
            ),
            array(
                array(
                    'code' => 'code'
                ),
                'SELECT * FROM email_template WHERE action_code LIKE :code ORDER BY category ASC',
                array(
                    ':code' => '%code%',
                ),
            ),

        );
    }

    /**
     * @dataProvider templateGetSearchQueryProvider
     */
    public function testTemplateGetSearchQuery($data, $query, $bindings)
    {
        $service = new \Box\Mod\Email\Service();
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);
        $result = $service->templateGetSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($result[0], $query);
        $this->assertEquals($result[1], $bindings);

    }

    public function testTemplateToApiArray()
    {
        $id          = 1;
        $action_code = 'code';
        $category    = 'category';
        $enabled     = 1;
        $subject     = 'Subject';
        $description = 'Description';
        $content     = 'content';

        $model              = new \Model_EmailTemplate();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id          = $id;
        $model->action_code = $action_code;
        $model->category    = $category;
        $model->enabled     = $enabled;
        $model->subject     = $subject;
        $model->description = $description;
        $model->content     = $content;


        $expected = array(
            'id'          => $id,
            'action_code' => $action_code,
            'category'    => $category,
            'enabled'     => $enabled,
            'subject'     => $subject,
            'description' => $description,
        );


        $service = new \Box\Mod\Email\Service();
        $result  = $service->templateToApiArray($model);

        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testTemplateToApiArrayDeep()
    {
        $id          = 1;
        $action_code = 'code';
        $category    = 'category';
        $enabled     = 1;
        $subject     = 'Subject';
        $description = 'Description';
        $content     = 'content';

        $model              = new \Model_EmailTemplate();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id          = $id;
        $model->action_code = $action_code;
        $model->category    = $category;
        $model->enabled     = $enabled;
        $model->subject     = $subject;
        $model->description = $description;
        $model->content     = $content;


        $expected = array(
            'id'          => $id,
            'action_code' => $action_code,
            'category'    => $category,
            'enabled'     => $enabled,
            'subject'     => $subject,
            'description' => $description,
            'content'     => $content,
            'vars'        => array(
                'param1' => 'value1'
            )
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->setMethods(array('getVars'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getVars')
            ->will($this->returnValue(array('param1' => 'value1')));


        $result = $serviceMock->templateToApiArray($model, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('vars', $result);
        $this->assertIsArray($result['vars']);

        $this->assertEquals($result, $expected);
    }

    public function template_updateProvider()
    {
        return array(
            array(
                array(
                    'id'       => 5,
                    'enabled'  => 1,
                    'category' => 'Category',
                    'subject'  => null,
                    'content'  => null
                ),
                $this->never()
            ),
            array(
                array(
                    'id'       => 5,
                    'enabled'  => 1,
                    'category' => 'Category',
                    'subject'  => 'Subject',
                    'content'  => 'Content'
                ),
                $this->atLeastOnce()
            ),
        );
    }

    /**
     * @dataProvider template_updateProvider
     */
    public function testTemplate_update($data, $templateRenderExpects)
    {
        $id        = rand(1, 100);
        $model     = new \Model_EmailTemplate();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = $id;

        $emailServiceMock = $this->getMockBuilder('Box\Mod\Email\Service')->setMethods(array('template_render'))->getMock();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store');

        $loggerMock = $this->getMockBuilder('Box_Log')->getMock();

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue(array()));

        $twigMock = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $loggerMock;
        $di['tools']  = $toolsMock;
        $di['crypt']  = $cryptMock;
        $di['twig']   = $twigMock;      
       
        $systemServiceMock = $this->getMockBuilder('Box\Mod\System\Service')->getMock();
        
        $di['mod_service'] = $di->protect(function () use ($systemServiceMock) {
                return $systemServiceMock;
            });    

        $emailServiceMock->setDi($di);

        $templateModel = new \Model_EmailTemplate();
        $templateModel->loadBean(new \RedBeanPHP\OODBBean());

        $result = $emailServiceMock->updateTemplate($templateModel, $data['enabled'], $data['category'], $data['subject'], @$data['content']);
        $this->assertEquals($result, true);
    }

    public function testGetEmailById(){
        $service = new \Box\Mod\Email\Service();

        $id = rand(1, 100);
        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = $id;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->getEmailById($id);

        $this->assertEquals($id, $result->id);
    }

    public function testGetEmailByIdException()
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $service->getEmailById(5);
    }

    public function testTemplateCreate()
    {
        $service = new \Box\Mod\Email\Service();

        $id = rand(1, 100);
        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = $id;

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($id));
        $emailTemplateModel = new \Model_EmailTemplate();
        $emailTemplateModel->loadBean(new \RedBeanPHP\OODBBean());
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($emailTemplateModel));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $data = array(
            'action_code' => 'Action_code',
            'subject'     => 'Subject',
            'content'     => 'Content',
            'category'    => 'category'
        );

        $result = $service->templateCreate($data['action_code'], $data['subject'], $data['content'], 1, $data['category']);

        $this->assertEquals($emailTemplateModel, $result);
    }

    public function batchTemplateGenerateProvider()
    {
        return array(
            array(true, false, $this->never(), $this->never()),
            array(false, true, $this->atLeastOnce(), $this->atLeastOnce()),
            array(true, true, $this->atLeastOnce(), $this->never()),
        );
    }
    /**
     * @dataProvider batchTemplateGenerateProvider
     */
    public function testBatchTemplateGenerate($findOneReturn, $isExtensionActiveReturn, $findOneExpects, $dispenseExpects)
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($findOneExpects)
            ->method('findOne')
            ->will($this->returnValue($findOneReturn));

        $emailTemplateModel = new \Model_EmailTemplate();
        $emailTemplateModel->loadBean(new \RedBeanPHP\OODBBean());
        $db->expects($dispenseExpects)
            ->method('dispense')
            ->will($this->returnValue($emailTemplateModel));

        $extension = $this->getMockBuilder('Box\Mod\Extension\Service')->getMock();
        $extension->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->will($this->returnValue($isExtensionActiveReturn));


        $di                = new \Box_Di();
        $di['db']          = $db;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(function () use ($extension) {
            return $extension;
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);


        $result = $service->templateBatchGenerate();

        $this->assertTrue($result);
    }

    public function testTemplateBatchDisable()
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue(true));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $result = $service->templateBatchDisable();

        $this->assertTrue($result, true);
    }

    public function testTemplateBatchEnable()
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue(true));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);


        $result = $service->templateBatchEnable();

        $this->assertTrue($result, true);
    }

    public function testbatchSend(){
        $service = new \Box\Mod\Email\Service();

        $queueModel = new \Model_ModEmailQueue();
        $queueModel->loadBean(new \RedBeanPHP\OODBBean());
        $queueModel->priority = 10;
        $queueModel->tries = 10;
        $queueModel->subject = 'subject';
        $queueModel->client_id = 1;
        $queueModel->sender = 'sender@example.com';
        $queueModel->recipient = 'receiver@example.com';
        $queueModel->content = 'content';
        $queueModel->from_name = 'From Name';
        $queueModel->to_name = 'To Name';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->exactly(2))
            ->method('findOne')
            ->will($this->onConsecutiveCalls($queueModel, false));
         $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));


        $activityMock = $this->getMockBuilder('\Box\Mod\Activity\Service')->setMethods(array('logEmail'))->getMock();
        $activityMock->expects($this->atLeastOnce())
            ->method('logEmail')
            ->will($this->returnValue(true));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array(
                'log_enabled' => 1,
                'cancel_after' => 1
            )));


          $mailMock = $this->getMockBuilder('\Box_Mail')->disableOriginalConstructor()->getMock();
       /* Will not work be called because APPLICATION_ENV != 'production'
        * $mailMock->expects($this->atLeastOnce())
            ->method('send')
            ->will($this->returnValue(true));
       */

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['mail']     = $mailMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(function ($name) use ($activityMock) {return $activityMock;});
        $di['mod'] = $di->protect(function () use ($modMock) {
           return $modMock;
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);


        $result = $service->batchSend();

        $this->assertNull($result);
    }

    public function testResetTemplateByCode()
    {
        $service = new \Box\Mod\Email\Service();

        $templateModel = new \Model_EmailTemplate();
        $templateModel->loadBean(new \RedBeanPHP\OODBBean());
        $templateModel->id = rand(1,100);
        $templateModel->action_code = 'mod_email_test';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($templateModel));

        $cryptMock = $this->getMockBuilder('\Box_Crypt')->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue(array()));

        $twigMock = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['tools']  = $toolsMock;
        $di['crypt']  = $cryptMock;
        $di['twig']   = $twigMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        
        $systemService = $this->getMockBuilder('Box\Mod\System\Service')->getMock();
        
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });

        $service->setDi($di);

        $result = $service->resetTemplateByCode('mod_email_test');

        $this->assertTrue($result);
    }

    public function testResetTemplateByCodeException()
    {
        $service = new \Box\Mod\Email\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $service->resetTemplateByCode('mod_email_test');
    }

    public function testsendMail()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $queueEmail = new \Model_ModEmailQueue();
        $queueEmail->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ModEmailQueue')
            ->willReturn($queueEmail);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array(
                'cancel_after' => 1
            )));

        $mailMock = $this->getMockBuilder('\Box_Mail')->disableOriginalConstructor()->getMock();


        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $di['mail']     = $mailMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod'] = $di->protect(function () use ($modMock) {
            return $modMock;
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service = new \Box\Mod\Email\Service();
        $service->setDi($di);

        $to = 'receiver@example.com';
        $from = 'sender@example.com';
        $subject = 'Important message';
        $content = 'content';
        $result = $service->sendMail($to, $from, $subject, $content);
        $this->assertTrue($result);
    }
}
<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_AdminTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'emails.xml';

    public function testGenerate(): void
    {
        $bool = $this->api_admin->email_batch_template_generate();
        $this->assertTrue($bool);
    }

    public function testReset(): void
    {
        $data = [
            'code' => 'mod_email_test',
        ];
        $bool = $this->api_admin->email_template_reset($data);
        $this->assertTrue($bool);
    }

    public function testTemplates(): void
    {
        $array = $this->api_admin->email_template_get_list();
        $this->assertIsArray($array);

        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->email_template_get($data);
        $this->assertIsArray($array);

        $data = [
            'action_code' => 'test',
            'subject' => 'test',
            'enabled' => 1,
            'content' => 'test',
        ];
        $id = $this->api_admin->email_template_create($data);
        $this->assertTrue($id > 0);

        $data['id'] = $id;
        $bool = $this->api_admin->email_template_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->email_template_delete($data);
        $this->assertTrue($bool);
    }

    public function testUpdate(): void
    {
        $subject = 'New subject';
        $data['id'] = 1;
        $data['subject'] = $subject;
        $bool = $this->api_admin->email_template_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->email_template_get($data);
        $this->assertEquals($subject, $array['subject']);
    }

    public function testEmails(): void
    {
        $array = $this->api_admin->email_email_get_list();
        $this->assertIsArray($array);

        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->email_email_get($data);
        $this->assertIsArray($array);
    }

    public function testSend(): void
    {
        $data = [
            'to' => 'demo@boxbiling.com',
            'to_name' => 'Client name',
            'from' => 'admin@fossbilling.org',
            'from_name' => 'Admin',
            'subject' => 'This is subject',
            'content' => 'This is message',
        ];
        $bool = $this->api_admin->email_send($data);
        $this->assertTrue($bool);
    }

    public function testResend(): void
    {
        $data = [
            'id' => 1,
        ];
        $bool = $this->api_admin->email_email_resend($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->email_email_get_list();
        $this->assertEquals(2, $array['total']);
    }

    public function testDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $bool = $this->api_admin->email_email_delete($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->email_email_get_list();
        $this->assertEquals(0, $array['total']);
    }

    public function testBatch(): void
    {
        $bool = $this->api_admin->email_batch_template_disable();
        $this->assertTrue($bool);

        $bool = $this->api_admin->email_batch_template_enable();
        $this->assertTrue($bool);
    }

    public function testCheck(): void
    {
        $bool = $this->api_admin->email_send_test();
        $this->assertTrue($bool);
    }

    public function testRender(): void
    {
        $data = [
            'id' => 1,
            '_tpl' => '{{ now|date("Y") }}',
        ];
        $string = $this->api_admin->email_template_render($data);
        $this->assertEquals(date('Y'), $string);
    }

    public function testTemplateGeneralSend(): void
    {
        $params = [];
        $params['to'] = 'client@fossbilling.org';
        $params['to_name'] = 'Client PHPUnit';

        $params['code'] = 'mod_client_signup';

        $params['default_template'] = 'Hello, message from {{ admin.client_get({"id":client_id}).first_name }}, {{subject}}';
        $params['default_subject'] = 'My subject for client';

        $params['client_id'] = 1;

        $bool = $this->api_admin->email_template_send($params);
        $this->assertTrue($bool);
    }

    public function testTemplatePopulateVariables(): void
    {
        $params = [];
        $params['to'] = 'client@fossbilling.org';
        $params['to_name'] = 'Client PHPUnit';

        $params['code'] = 'mod_client_signup';

        $params['default_template'] = 'Hello, message from {{ admin.client_get({"id":client_id}).first_name }}, {{subject}}';
        $params['default_subject'] = 'My subject for client';

        $params['to_client'] = 1;

        $bool = $this->api_admin->email_template_send($params);
        $this->assertTrue($bool);

        $emailModel = $this->di['db']->findOne('ModEmailQueue', ' order by id desc');

        $clientModel = $this->di['db']->load('Client', $params['to_client']);

        $this->assertTrue(str_contains($emailModel->subject, $clientModel->first_name), 'Template variables were not populated');
    }

    public function testSendToClient(): void
    {
        $params = [];
        $params['to_client'] = 1;
        $params['code'] = 'mod_client_signup';

        $bool = $this->api_admin->email_template_send($params);
        $this->assertTrue($bool);
    }

    public function testSendToStaff(): void
    {
        $params = [];
        $params['to_staff'] = true;
        $params['code'] = 'mod_staff_client_signup';
        $params['client'] = $this->api_admin->client_get(['id' => 1]);

        $bool = $this->api_admin->email_template_send($params);
        $this->assertTrue($bool);
    }

    public function testBatchSendmail(): void
    {
        $result = $this->api_admin->email_batch_sendmail();
        $this->assertNull($result);
    }

    public function testEmailEmailGetList(): void
    {
        $array = $this->api_admin->email_email_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('client_id', $item);
            $this->assertArrayHasKey('sender', $item);
            $this->assertArrayHasKey('recipients', $item);
            $this->assertArrayHasKey('subject', $item);
            $this->assertArrayHasKey('content_html', $item);
            $this->assertArrayHasKey('content_text', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }

    public function testEmailTemplateGetList(): void
    {
        $array = $this->api_admin->email_template_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('action_code', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('enabled', $item);
            $this->assertArrayHasKey('subject', $item);
            $this->assertArrayHasKey('description', $item);
        }
    }

    public function testBatchDelete(): void
    {
        $array = $this->api_admin->email_email_get_list([]);

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->email_batch_delete(['ids' => $ids]);
        $array = $this->api_admin->email_email_get_list([]);

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }
}

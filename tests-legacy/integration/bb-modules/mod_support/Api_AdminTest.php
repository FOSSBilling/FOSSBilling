<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_SupportTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_support.xml';

    public function testCanned(): void
    {
        $array = $this->api_admin->support_canned_pairs();
        $this->assertIsArray($array);

        $array = $this->api_admin->support_canned_get_list();
        $this->assertIsArray($array);
        $list = $array['list'];
        $this->assertIsArray($list);
        $item = $list[0];
        $this->assertArrayHasKey('category', $item);

        $data = [
            'id' => '1',
        ];

        $data['category_id'] = 1;
        $data['title'] = 'new canned title';
        $id = $this->api_admin->support_canned_create($data);
        $this->assertTrue(is_numeric($id));

        $array = $this->api_admin->support_canned_get($data);
        $this->assertIsArray($array);

        $data['id'] = $id;
        $data['title'] = 'new title';
        $bool = $this->api_admin->support_canned_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_canned_delete($data);
        $this->assertTrue($bool);
    }

    public function testCannedCategory(): void
    {
        $array = $this->api_admin->support_canned_category_pairs();
        $this->assertIsArray($array);

        $data = [
            'id' => '1',
        ];
        $array = $this->api_admin->support_canned_category_get($data);
        $this->assertIsArray($array);

        $data['title'] = 'new cat title';
        $id = $this->api_admin->support_canned_category_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $data['title'] = 'new title';
        $bool = $this->api_admin->support_canned_category_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_canned_category_delete($data);
        $this->assertTrue($bool);
    }

    public function testPublicTickets(): void
    {
        $array = $this->api_admin->support_public_ticket_get_statuses();
        $this->assertIsArray($array);

        $array = $this->api_admin->support_public_ticket_get_list();
        $this->assertIsArray($array);
        $list = $array['list'];
        $this->assertIsArray($list);

        $bool = $this->api_admin->support_batch_public_ticket_auto_close();
        $this->assertTrue($bool);

        $data = [
            'name' => 'This is me',
            'email' => 'email@email.com',
            'subject' => 'subject',
            'message' => 'Message',
        ];
        $id = $this->api_admin->support_public_ticket_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $array = $this->api_admin->support_public_ticket_get($data);
        $this->assertIsArray($array);

        $data['subject'] = 'new subject';
        $data['status'] = 'closed';
        $bool = $this->api_admin->support_public_ticket_update($data);
        $this->assertTrue($bool);

        $data['content'] = 'new message';
        $id = $this->api_admin->support_public_ticket_reply($data);
        $this->assertTrue(is_numeric($id));

        $id = $this->api_admin->support_public_ticket_reply($data);
        $this->assertTrue(is_numeric($id));

        $bool = $this->api_admin->support_public_ticket_close($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_public_ticket_delete($data);
        $this->assertTrue($bool);
    }

    public function testSupport(): void
    {
        $bool = $this->api_admin->support_batch_ticket_auto_close();
        $this->assertTrue($bool);

        $array = $this->api_admin->support_ticket_get_statuses([]);
        $this->assertIsArray($array);

        $data = [
            'client_id' => 1,
            'support_helpdesk_id' => 1,
            'subject' => 'this is subject',
            'content' => 'this is content',
        ];
        $id = $this->api_admin->support_ticket_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $array = $this->api_admin->support_ticket_get($data);
        $this->assertIsArray($array);

        $data['content'] = 'other message';
        $mid = $this->api_admin->support_ticket_reply($data);
        $this->assertTrue(is_numeric($mid));

        $mid = $this->api_admin->support_ticket_reply($data);
        $this->assertTrue(is_numeric($mid));

        $bool = $this->api_admin->support_ticket_message_update(['id' => $mid, 'content' => 'This is a new content']);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_ticket_close($data);
        $this->assertTrue($bool);

        $data['subject'] = 'new subject';
        $bool = $this->api_admin->support_ticket_update($data);
        $this->assertTrue($bool);

        $data = [
            'ticket_id' => $id,
            'note' => 'this is note',
        ];
        $nid = $this->api_admin->support_note_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $nid;
        $bool = $this->api_admin->support_note_delete($data);
        $this->assertTrue($bool);

        $data['id'] = $id;
        $bool = $this->api_admin->support_ticket_delete($data);
        $this->assertTrue($bool);
    }

    public function testHelpdesk(): void
    {
        $data = [
            'name' => 'title',
        ];
        $id = $this->api_admin->support_helpdesk_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $array = $this->api_admin->support_helpdesk_get($data);
        $this->assertIsArray($array);

        $data['name'] = 'new name';
        $data['can_reopen'] = 1;
        $data['close_after'] = 15;
        $data['signature'] = 'new name';
        $data['email'] = 'new@email.com';
        $bool = $this->api_admin->support_helpdesk_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_helpdesk_delete($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->support_helpdesk_get_list($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->support_helpdesk_get_pairs($data);
        $this->assertIsArray($array);
    }

    public function testExpirePublicTicket(): void
    {
        $data = [
            'name' => 'Me',
            'email' => 'test@test.com',
            'subject' => 'test@test.com',
            'message' => 'test@test.com',
        ];
        $hash = $this->api_guest->support_ticket_create($data);

        $ticket = $this->api_guest->support_ticket_get(['hash' => $hash]);

        $model = $this->di['db']->load('SupportPTicket', $ticket['id']);
        $model->status = 'on_hold';
        $model->updated_at = date('Y-m-d H:i:s', strtotime('-50 days'));
        $this->di['db']->store($model);

        $bool = $this->api_admin->support_batch_public_ticket_auto_close();

        $array = $this->api_admin->support_public_ticket_get_list(['status' => 'on_hold']);
        $this->assertIsArray($array);
        $this->assertTrue(empty($array['list']));
    }

    public function testTicketTask(): void
    {
        $data = [
            'support_helpdesk_id' => 1,
            'subject' => 'test@test.com',
            'content' => 'test@test.com',

            'rel_type' => 'order',
            'rel_task' => 'cancel',
        ];

        $id = $this->api_client->support_ticket_create($data);

        $data = [
            'id' => $id,
        ];

        $bool = $this->api_admin->support_task_complete($data);
        $this->assertTrue($bool);
    }

    public function testSupportTicketGetList(): void
    {
        $array = $this->api_admin->support_ticket_get_list([]);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('support_helpdesk_id', $item);
            $this->assertArrayHasKey('client_id', $item);

            $this->assertArrayHasKey('priority', $item);
            $this->assertArrayHasKey('subject', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('rel_type', $item);
            $this->assertArrayHasKey('rel_task', $item);
            $this->assertArrayHasKey('rel_new_value', $item);
            $this->assertArrayHasKey('rel_status', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('replies', $item);

            $this->assertArrayHasKey('first', $item);
            $this->assertIsArray($item['first']);

            $this->assertArrayHasKey('helpdesk', $item);
            $helpdesk = $item['helpdesk'];
            if (count($helpdesk)) {
                $this->assertIsArray($helpdesk);
                $this->assertArrayHasKey('id', $helpdesk);
                $this->assertArrayHasKey('name', $helpdesk);
                $this->assertArrayHasKey('email', $helpdesk);
                $this->assertArrayHasKey('close_after', $helpdesk);
                $this->assertArrayHasKey('can_reopen', $helpdesk);
                $this->assertArrayHasKey('signature', $helpdesk);
                $this->assertArrayHasKey('created_at', $helpdesk);
                $this->assertArrayHasKey('updated_at', $helpdesk);
            }

            $this->assertArrayHasKey('messages', $item);
            $this->assertIsArray($item['messages']);

            $this->assertArrayHasKey('rel', $item);
            $this->assertIsArray($item['rel']);

            $this->assertArrayHasKey('client', $item);
            $this->assertIsArray($item['client']);
        }
    }

    public function testTicketBatchDelete(): void
    {
        $array = $this->api_admin->support_ticket_get_list([]);

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->support_batch_delete(['ids' => $ids]);
        $array = $this->api_admin->support_ticket_get_list([]);

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testPublicTicketBatchDelete(): void
    {
        $array = $this->api_admin->support_public_ticket_get_list([]);

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->support_batch_delete_public(['ids' => $ids]);
        $array = $this->api_admin->support_public_ticket_get_list([]);

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testKbCategory(): void
    {
        $array = $this->api_admin->support_kb_category_get_list();
        $this->assertIsArray($array);

        $array = $this->api_admin->support_kb_category_get_pairs();
        $this->assertIsArray($array);

        $data = [
            'title' => 'test',
        ];
        $id = $this->api_admin->support_kb_category_create($data);
        $this->assertTrue(is_numeric($id));

        $data = [
            'id' => $id,
        ];
        $array = $this->api_admin->support_kb_category_get($data);
        $this->assertIsArray($array);

        $data['id'] = $id;
        $data['title'] = 'new';
        $bool = $this->api_admin->support_kb_category_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_kb_category_delete($data);
        $this->assertTrue($bool);
    }

    public function testKbArticle(): void
    {
        $array = $this->api_admin->support_kb_article_get_list();
        $this->assertIsArray($array);

        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->support_kb_article_get($data);
        $this->assertIsArray($array);

        $data = [
            'kb_article_category_id' => 1,
            'title' => 'test',
        ];
        $id = $this->api_admin->support_kb_article_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $data['title'] = 'new';
        $bool = $this->api_admin->support_kb_article_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->support_kb_article_delete($data);
        $this->assertTrue($bool);
    }

    public function testKbArticleGetList(): void
    {
        $array = $this->api_admin->support_kb_article_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('views', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('category', $item);

            $category = $item['category'];
            $this->assertIsArray($category);
            $this->assertArrayHasKey('id', $category);
            $this->assertArrayHasKey('slug', $category);
            $this->assertArrayHasKey('title', $category);
        }
    }

    public function testKbCategoryGetList(): void
    {
        $array = $this->api_admin->support_kb_category_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('articles', $item);

            $articles = $item['articles'];
            if (count($articles)) {
                $article = $articles[0];
                $this->assertIsArray($article);
                $this->assertArrayHasKey('id', $article);
                $this->assertArrayHasKey('slug', $article);
                $this->assertArrayHasKey('title', $article);
            }
        }
    }
}

<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Guest_SupportTest extends ApiTestCase
{
    public function testContact(): void
    {
        $data = [
            'name' => 'This is me',
            'email' => 'email@email.com',
            'subject' => 'subject',
            'message' => 'Message',
        ];
        $hash = $this->api_guest->support_ticket_create($data);
        $this->assertIsString($hash);

        $data = [
            'hash' => $hash,
        ];
        $array = $this->api_guest->support_ticket_get($data);
        $this->assertIsArray($array);

        $data = [
            'hash' => $hash,
            'message' => 'Hello',
        ];
        $hash = $this->api_guest->support_ticket_reply($data);
        $this->assertIsString($hash);

        $bool = $this->api_guest->support_ticket_close($data);
        $this->assertTrue($bool);
    }

    public function testKb(): void
    {
        $array = $this->api_guest->support_kb_article_get_list();
        $this->assertIsArray($array);

        $array = $this->api_guest->support_kb_category_get_list();
        $this->assertIsArray($array);

        $data = [
            'id' => 1,
        ];
        $array = $this->api_guest->support_kb_article_get($data);
        $this->assertIsArray($array);

        $data = [
            'slug' => 'how-to-contact-support',
        ];
        $array = $this->api_guest->support_kb_article_get($data);
        $this->assertIsArray($array);

        $data = [
            'slug' => 'discuss-about-everything',
        ];
        $array = $this->api_guest->support_kb_category_get($data);
        $this->assertIsArray($array);
    }

    public function testKbCategoryGetPairs(): void
    {
        $array = $this->api_guest->support_kb_category_get_pairs();
        $this->assertIsArray($array);
    }

    public function testKbArticleGetList(): void
    {
        $array = $this->api_guest->support_kb_article_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
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

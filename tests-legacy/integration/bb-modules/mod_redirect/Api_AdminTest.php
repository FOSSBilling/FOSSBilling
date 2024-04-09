<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Redirect_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'redirect';
    protected $_initialSeedFile = 'mod_redirect.xml';

    public function testRedirect(): void
    {
        $array = $this->api_admin->redirect_get_list();
        $this->assertIsArray($array);

        $int = $this->api_admin->redirect_create(['path' => '/knowledgebase', 'target' => 'support/kb']);
        $this->assertIsInt($int);

        $r = $this->api_admin->redirect_get(['id' => $int]);
        $this->assertTrue(isset($r['id']));
        $this->assertTrue(isset($r['path']));
        $this->assertTrue(isset($r['target']));

        $bool = $this->api_admin->redirect_update(['id' => $int, 'target' => 'new-target']);
        $this->assertTrue($bool);

        $bool = $this->api_admin->redirect_update(['id' => $int, 'path' => '/new-path']);
        $this->assertTrue($bool);

        $bool = $this->api_admin->redirect_delete(['id' => $int]);
        $this->assertTrue($bool);
    }
}

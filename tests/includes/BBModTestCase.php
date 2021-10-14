<?php
class BBModTestCase extends BBDbApiTestCase
{
    protected $_mod = null;
    
    public function setUp(): void
    {
        global $di;
        $mod = $di['mod']($this->_mod);
        if(!$mod->isCore()) {
            $mod->install();
        }

        parent::setUp();

        try {
            $this->api_admin->extension_activate(array('id'=>$this->_mod,'type'=>'mod'));
        } catch(Exception $e) {
            
        }
    }
}
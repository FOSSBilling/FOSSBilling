<?php

class BBModTestCase extends BBDbApiTestCase
{
    protected $_mod;

    public function setUp(): void
    {
        global $di;
        $mod = $di['mod']($this->_mod);
        if (!$mod->isCore()) {
            $mod->install();
        }

        parent::setUp();

        try {
            $this->api_admin->extension_activate(['id' => $this->_mod, 'type' => 'mod']);
        } catch (Exception) {
        }
    }
}

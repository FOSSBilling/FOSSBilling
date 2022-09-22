<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Wysiwyg\Api;

class Admin extends \Api_Abstract
{
    public function editor()
    {
        $mod = $this->di['mod']('wysiwyg');
        $config = $mod->getConfig();
        return $this->di['array_get']($config, 'editor', 'markitup');
    }

    public function editors()
    {
        return array(
            'markitup'  =>  'markItUp',
            'ckeditor'  =>  'CKEditor',
        );
    }
}
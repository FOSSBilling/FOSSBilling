<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
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
        if (empty($this->di['array_get']($config, 'editor', 'CKEditor')) || !(defined($this->di['array_get']($config, 'editor', 'CKEditor')))) {
            return 'ckeditor';
        } else {
            return $this->di['array_get']($config, 'editor', 'CKEditor');
        }
    }

    public function editors()
    {
        return array(
            'ckeditor'  =>  'CKEditor',
        );
    }
}

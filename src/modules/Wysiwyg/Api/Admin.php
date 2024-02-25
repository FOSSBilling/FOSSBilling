<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Wysiwyg\Api;

class Admin extends \Api_Abstract
{
    public function editor()
    {
        $mod = $this->di['mod']('wysiwyg');
        $config = $mod->getConfig();

        return $config['editor'] ?? 'ckeditor';
    }

    public function editors()
    {
        return [
            'ckeditor' => 'CKEditor',
        ];
    }
}

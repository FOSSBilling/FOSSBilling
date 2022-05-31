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

namespace Box\Mod\Wysiwyg;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function uninstall()
    {
        $model = $this->di['db']->findOne('ExtensionMeta', 'extension = :ext AND meta_key = :key',
            array(':ext'=>'mod_wysiwyg', ':key'=>'config'));
        if ($model instanceof \Model_ExtensionMeta) {
            $this->di['db']->trash($model);
        }
        return true;
    }

    public function install()
    {
        $extensionService = $this->di['mod_service']('Extension');
        $defaultConfig = array(
            'ext' => 'mod_wysiwyg',
            'editor' => 'ckeditor'
        );
        $extensionService->setConfig($defaultConfig);
        return true;
    }
}

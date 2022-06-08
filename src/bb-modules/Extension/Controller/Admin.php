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

namespace Box\Mod\Extension\Controller;

class Admin implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
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

    public function fetchNavigation()
    {
        return [
            'group' => [
                'location' => 'extensions',
                'index' => 1000,
                'label' => 'Extensions',
                'class' => 'iPlugin',
                'sprite_class' => 'dark-sprite-icon sprite-electroPlug',
            ],
            'subpages' => [
                [
                    'location' => 'extensions',
                    'label' => 'Overview',
                    'uri' => $this->di['url']->adminLink('extension'),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'extensions',
                    'label' => 'Languages',
                    'index' => 200,
                    'uri' => $this->di['url']->adminLink('extension/languages'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/extension', 'get_index', [], get_class($this));
        $app->get('/extension/settings/:mod', 'get_settings', ['mod' => '[a-z0-9-]+'], get_class($this));
        $app->get('/extension/languages', 'get_langs', [], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_extension_index');
    }

    public function get_langs(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_extension_languages');
    }

    public function get_settings(\Box_App $app, $mod)
    {
        $this->di['is_admin_logged'];
        $file = 'mod_'.$mod.'_settings';

        return $app->render($file);
    }
}

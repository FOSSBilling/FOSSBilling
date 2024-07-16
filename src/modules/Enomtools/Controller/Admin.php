<?php

namespace Box\Mod\Enomtools\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface{

    protected $di;

    public function setDi(\Pimple\Container|null $di): void{
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container{
        return $this->di;
    }

    /**
     * This method registers menu items in admin area navigation block
     * This navigation is cached in data/cache/{hash}. To see changes please
     * remove the file.
     *
     * @return array
     */
    public function fetchNavigation(): array{
        return [
            'subpages' => [
                [
                    'location' => 'system',
                    'label' => __trans('Enom Tools'),
                    'index' => 1500,
                    'uri' => $this->di['url']->adminLink('enomtools'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app): void{
        $app->get('/enomtools', 'get_index', [], static::class);
    }

    public function get_index(\Box_App $app){
        $this->di['is_admin_logged'];

        $api = $this->di['api_admin'];
        $TLDExist = $api->enomtools_get_existing_tlds();

        $params = [];
        $params['TLDExist'] = $TLDExist;

        return $app->render('mod_enomtools_index', $params);
    }

}

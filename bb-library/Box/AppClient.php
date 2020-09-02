<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_AppClient extends Box_App
{
    protected function init()
    {
        $m = $this->di['mod']($this->mod);
        $m->registerClientRoutes($this);

        if($this->mod == 'api') {
            define ('BB_MODE_API', TRUE);
        } else {
            $extensionService = $this->di['mod_service']('extension');
            if ($extensionService->isExtensionActive('mod', 'redirect')) {
                $m = $this->di['mod']('redirect');
                $m->registerClientRoutes($this);
            }

            //init index module manually
            $this->get('', 'get_index');
            $this->get('/', 'get_index');


            //init custom methods for undefined pages
            $this->get('/:page', 'get_custom_page', array('page' => '[a-z0-9-/.//]+'));
            $this->post('/:page', 'get_custom_page', array('page' => '[a-z0-9-/.//]+'));
        }
    }

    public function get_index()
    {
        return $this->render('mod_index_dashboard');
    }

    public function get_custom_page($page)
    {
        $ext = $this->ext;
        if(strpos($page, '.') !== false) {
            $ext = substr($page, strpos($page, '.')+1);
            $page = substr($page, 0, strpos($page, '.'));
        }
        $page = str_replace('/', '_', $page);
        $tpl = 'mod_page_'.$page;
        try {
            return $this->render($tpl, array('post'=>$_POST), $ext);
        } catch(Exception $e) {
            if(BB_DEBUG) error_log($e);
        }
      // throw new \Box_Exception('Page :url not found', array(':url'=>$page), 404);
      $e = new \Box_Exception('Page :url not found', array(':url'=>$this->url), 404);
        
      error_log($e->getMessage());
     header("HTTP/1.0 404 Not Found");
     return $this->render('404', array('exception'=>$e));
    }

    /**
     * @param string $fileName
     */
    public function render($fileName, $variableArray = array(), $ext = 'phtml')
    {
        try {
            $template = $this->getTwig()->load($fileName.'.'.$ext);
        } catch (Twig\Error\LoaderError $e) {
            error_log($e->getMessage());
            throw new \Box_Exception('Page not found', null, 404);
        }
        return $template->render($variableArray);
    }

    protected function getTwig()
    {
        $service = $this->di['mod_service']('theme');
        $code = $service->getCurrentClientAreaThemeCode();
        $theme = $service->getTheme($code);
        $settings = $service->getThemeSettings($theme);

        $loader = new Box_TwigLoader(array(
                "mods" => BB_PATH_MODS,
                "theme" => BB_PATH_THEMES.DIRECTORY_SEPARATOR.$code,
                "type" => "client"
            )
        );

        $twig = $this->di['twig'];
        $twig->setLoader($loader);

        $twig->addGlobal('current_theme', $code);
        $twig->addGlobal('settings', $settings);

        if($this->di['auth']->isClientLoggedIn()) {
            $twig->addGlobal('client', $this->di['api_client']);
        }

        if($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        return $twig;
    }
}
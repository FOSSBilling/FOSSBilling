<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Box_AppAdmin extends Box_App
{
    public function init()
    {
        $m = $this->di['mod']($this->mod);
        $controller = $m->getAdminController();
        if(!is_null($controller)){
            $controller->register($this);
        }
    }

    protected function checkPermission()
    {
        $service = $this->di['mod_service']('Staff');
        
        if($this->mod !== 'extension' && $this->di['auth']->isAdminLoggedIn() && !$service->hasPermission(null, $this->mod)){
            http_response_code(403);
            $e = new \FOSSBilling\InformationException('You do not have permission to access the :mod: module', [':mod:' => $this->mod], 403);
            echo $this->render('error', ['exception' => $e]);
            exit;
        }
    }

    public function render($fileName, $variableArray = [])
    {
        $template = $this->getTwig()->load($fileName . '.html.twig');

        return $template->render($variableArray);
    }

    public function redirect($path): never
    {
        $location = $this->di['url']->adminLink($path);
        header("Location: $location");
        exit;
    }

    protected function getTwig()
    {
        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();

        $loader = new Box_TwigLoader([
                'mods' => PATH_MODS,
                'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . $theme['code'],
                'type' => 'admin',
            ]
        );

        $twig = $this->di['twig'];
        $twig->setLoader($loader);

        $twig->addGlobal('theme', $theme);

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        return $twig;
    }
}

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

class Box_AppAdmin extends Box_App
{
    public function init()
    {
        $m = $this->di['mod']($this->mod);
        $controller = $m->getAdminController();
        $controller->register($this);
    }

    public function render($fileName, $variableArray = [])
    {
        $template = $this->getTwig()->load($fileName . '.html.twig');

        return $template->render($variableArray);
    }

    public function redirect($path)
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
                'mods' => BB_PATH_MODS,
                'theme' => BB_PATH_THEMES . DIRECTORY_SEPARATOR . $theme['code'],
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

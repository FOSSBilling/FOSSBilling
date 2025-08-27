<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Symfony\Component\Filesystem\Path;

class Box_AppAdmin extends Box_App
{
    public function init(): void
    {
        $m = $this->di['mod']($this->mod);
        $controller = $m->getAdminController();
        if (!is_null($controller)) {
            $controller->register($this);
        }
    }

    protected function checkPermission(): void
    {
        $service = $this->di['mod_service']('Staff');

        if ($this->mod !== 'extension' && $this->di['auth']->isAdminLoggedIn() && !$service->hasPermission(null, $this->mod)) {
            http_response_code(403);
            $e = new FOSSBilling\InformationException('You do not have permission to access the :mod: module', [':mod:' => $this->mod], 403);
            echo $this->render('error', ['exception' => $e]);
            exit;
        }
    }

    public function render($fileName, $variableArray = []): string
    {
        $template = $this->getTwig()->load(Path::changeExtension($fileName, '.html.twig'));

        return $template->render($variableArray);
    }

    public function redirect($path): never
    {
        $location = $this->di['url']->adminLink($path);
        header("Location: $location");
        exit;
    }

    /**
     * Get Twig environment for admin area.
     *
     * @return Twig\Environment
     */
    protected function getTwig(): Twig\Environment
    {
        $twigFactory = new FOSSBilling\Twig\TwigFactory($this->di);
        return $twigFactory->createAdminEnvironment($this->debugBar);
    }
}

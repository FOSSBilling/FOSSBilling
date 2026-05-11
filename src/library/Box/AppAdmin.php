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

use FOSSBilling\Environment;
use FOSSBilling\Http\HttpResponseException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;

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
            $e = new FOSSBilling\InformationException('You do not have permission to access the :mod: module', [':mod:' => $this->mod], 403);
            throw new HttpResponseException(new Response($this->render('error', ['exception' => $e]), 403));
        }
    }

    #[Override]
    public function render($fileName, $variableArray = []): string
    {
        $template = $this->getTwig()->load(Path::changeExtension($fileName, '.html.twig'));

        return $template->render($variableArray);
    }

    #[Override]
    public function redirect($path): never
    {
        $location = $this->di['url']->adminLink($path);
        $this->abortWithResponse(new \Symfony\Component\HttpFoundation\RedirectResponse($location));
    }

    /**
     * Get Twig environment for admin area.
     */
    protected function getTwig(): Twig\Environment
    {
        $twigFactory = $this->di['twig_factory'];

        return $twigFactory->createAdminEnvironment($this->debugBar);
    }
}

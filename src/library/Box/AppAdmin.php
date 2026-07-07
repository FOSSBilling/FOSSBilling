<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    protected function checkPermission(): ?Response
    {
        /*
         * Disable permission checks when an update is pending finalization.
         *
         * This is to make sure update finalization still works when there are changes to the
         * permission system and patches need to be applied before everything starts working again.
         */
        if ($this->di['auth']->isAdminLoggedIn() && $this->di['update_finalization']->isRequired()) {
            if (!$this->di['update_finalization']->isAdminPathAllowed($this->uri)) {
                return $this->redirect('system/update/finalize');
            }

            return null;
        }

        $service = $this->di['mod_service']('Staff');

        if ($this->mod !== 'extension' && $this->di['auth']->isAdminLoggedIn() && !$service->hasPermission(null, $this->mod)) {
            $e = new FOSSBilling\InformationException('You do not have permission to access the :mod: module', [':mod:' => $this->mod], 403);

            return $this->errorResponse($e, 403);
        }

        return null;
    }

    #[Override]
    public function render($fileName, $variableArray = []): string
    {
        $template = $this->getTwig()->load(Path::changeExtension($fileName, '.html.twig'));

        return $template->render($variableArray);
    }

    #[Override]
    public function redirect($path): RedirectResponse
    {
        return $this->responseFactory()->redirect($this->di['url']->adminLink($path));
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

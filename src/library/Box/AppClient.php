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
use Symfony\Component\HttpFoundation\Response;

class Box_AppClient extends Box_App
{
    protected function init(): void
    {
        $m = $this->di['mod']($this->mod);
        $m->registerClientRoutes($this);

        if ($this->mod == 'api') {
            define('API_MODE', true);

            // Prevent errors from being displayed in API mode as it can cause invalid JSON to be returned.
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        } else {
            $extensionService = $this->di['mod_service']('extension');
            if ($extensionService->isExtensionActive('mod', 'redirect')) {
                $m = $this->di['mod']('redirect');
                $m->registerClientRoutes($this);
            }

            // init index module manually
            $this->get('', 'get_index');
            $this->get('/', 'get_index');

            // init custom methods for undefined pages
            $this->get('/:page', 'get_custom_page', ['page' => '[a-z0-9-/.//]+']);
            $this->post('/:page', 'get_custom_page', ['page' => '[a-z0-9-/.//]+']);
        }
    }

    public function get_index(): string
    {
        return $this->render('mod_index_dashboard');
    }

    public function get_custom_page($page): Response
    {
        $ext = $this->ext;
        if (str_contains((string) $page, '.')) {
            $ext = substr((string) $page, strpos((string) $page, '.') + 1);
            $page = substr((string) $page, 0, strpos((string) $page, '.'));
        }
        $page = str_replace('/', '_', $page);
        $tpl = 'mod_page_' . $page;

        try {
            $content = $this->render($tpl, ['post' => $this->getRequest()->request->all()], $ext);

            if ("{$tpl}.{$ext}" === 'mod_page_sitemap.xml') {
                return $this->responseFactory()->html($content, 200, ['Content-Type' => 'application/xml']);
            }

            return $this->responseFactory()->html($content);
        } catch (FOSSBilling\InformationException $e) {
            // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
            if (DEBUG) {
                error_log($e->getMessage());
            }
        } catch (Twig\Error\LoaderError|Twig\Error\RuntimeError|Twig\Error\SyntaxError $e) {
            // A real template bug, not a missing page. Surface as a 500 so the
            // next regression of this shape (issue #3818) cannot hide behind a
            // generic 404.
            $this->di['logger']->setChannel('routing')->error(sprintf(
                'Template rendering failed for "%s" (page "%s"): %s',
                $tpl,
                (string) $page,
                $e->getMessage(),
            ), ['exception' => $e]);

            $internal = new FOSSBilling\InformationException('The requested page could not be rendered.', [], 500);

            return $this->errorResponse($internal);
        }
        $e = new FOSSBilling\InformationException('Page :url not found', [':url' => $this->url], 404);

        $this->di['logger']->setChannel('routing')->info($e->getMessage());

        return $this->errorResponse($e, 404);
    }

    /**
     * @param string $fileName
     */
    #[Override]
    public function render($fileName, $variableArray = [], $ext = 'html.twig'): string
    {
        try {
            $template = $this->getTwig()->load(Path::changeExtension($fileName, $ext));
        } catch (Twig\Error\LoaderError $e) {
            $this->di['logger']->setChannel('routing')->info($e->getMessage());

            throw new FOSSBilling\InformationException('Page not found', null, 404);
        }

        return $template->render($variableArray);
    }

    /**
     * Get Twig environment for client area.
     */
    protected function getTwig(): Twig\Environment
    {
        $twigFactory = $this->di['twig_factory'];

        return $twigFactory->createClientEnvironment($this->debugBar);
    }
}

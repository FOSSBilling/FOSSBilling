<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use DebugBar\Bridge\NamespacedTwigProfileCollector;
use FOSSBilling\Environment;
use FOSSBilling\TwigExtensions\DebugBar;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

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

    public function get_custom_page($page): string
    {
        $ext = $this->ext;
        if (str_contains($page, '.')) {
            $ext = substr($page, strpos($page, '.') + 1);
            $page = substr($page, 0, strpos($page, '.'));
        }
        $page = str_replace('/', '_', $page);
        $tpl = 'mod_page_' . $page;

        try {
            return $this->render($tpl, ['post' => $_POST], $ext);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log($e);
            }
        }
        $e = new FOSSBilling\InformationException('Page :url not found', [':url' => $this->url], 404);

        $this->di['logger']->setChannel('routing')->info($e->getMessage());
        http_response_code(404);

        return $this->render('error', ['exception' => $e]);
    }

    /**
     * @param string $fileName
     */
    public function render($fileName, $variableArray = [], $ext = 'html.twig'): string
    {
        try {
            $template = $this->getTwig()->load($fileName . '.' . $ext);
        } catch (Twig\Error\LoaderError $e) {
            $this->di['logger']->setChannel('routing')->info($e->getMessage());
            http_response_code(404);

            throw new FOSSBilling\InformationException('Page not found', null, 404);
        }

        if ($fileName . '.' . $ext == 'mod_page_sitemap.xml') {
            header('Content-Type: application/xml');
        }

        return $template->render($variableArray);
    }

    protected function getTwig(): Twig\Environment
    {
        $service = $this->di['mod_service']('theme');
        $code = $service->getCurrentClientAreaThemeCode();
        $theme = $service->getTheme($code);
        $settings = $service->getThemeSettings($theme);

        $loader = new Box_TwigLoader(
            [
                'mods' => PATH_MODS,
                'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . $code,
                'type' => 'client',
            ]
        );

        $twig = $this->di['twig'];
        $twig->setLoader($loader);

        $twig->addGlobal('current_theme', $code);
        $twig->addGlobal('settings', $settings);

        if (Environment::isDevelopment()) {
            $profile = new Profile();
            $twig->addExtension(new ProfilerExtension($profile));
            $collector = new NamespacedTwigProfileCollector($profile);
            if (!$this->debugBar->hasCollector($collector->getName())) {
                $this->debugBar->addCollector($collector);
            }
        }

        $twig->addExtension(new DebugBar($this->getDebugBar()));

        if ($this->di['auth']->isClientLoggedIn()) {
            $twig->addGlobal('client', $this->di['api_client']);
        }

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        return $twig;
    }
}

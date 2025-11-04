<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Imageproxy\Controller;

/**
 * Image Proxy Admin Controller.
 *
 * Handles admin area routes for the Image Proxy module.
 */
class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    /**
     * Set dependency injection container.
     *
     * @param \Pimple\Container $di Dependency injection container
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * Get dependency injection container.
     *
     * @return \Pimple\Container|null Dependency injection container
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Register routes for the Imageproxy module.
     * This method is automatically called by FOSSBilling during module initialization.
     *
     * @param \Box_App $app Application instance passed by reference
     */
    public function register(\Box_App &$app): void
    {
        $app->get('/imageproxy/image', 'get_image', [], static::class);
    }

    /**
     * Serve a proxied image directly from controller.
     * Bypasses API layer to avoid session issues with binary content.
     * Follows the pattern used by Orderbutton module.
     *
     * @param \Box_App $app Application instance
     *
     * @throws \FOSSBilling\InformationException If not authenticated or URL parameter missing
     */
    public function get_image(\Box_App $app): void
    {
        // Check admin authentication (throws if not logged in)
        $this->di['is_admin_logged']; // @phpstan-ignore expr.resultUnused

        // Close session to prevent locks and fingerprint issues with concurrent image requests
        session_write_close();

        // Get and validate URL parameter
        $encoded = $this->di['request']->query->get('u');
        if (!$encoded) {
            throw new \FOSSBilling\InformationException('Missing image URL');
        }

        /** @var \Box\Mod\Imageproxy\Service $service */
        $service = $this->di['mod_service']('imageproxy');
        $service->serveProxiedImage($encoded);
    }
}

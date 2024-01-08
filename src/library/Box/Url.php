<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Url implements FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected $baseUri;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function setBaseUri($baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Generates a URL.
     */
    public function get($uri)
    {
        return $this->baseUri . $uri;
    }

    /**
     * @param string $uri
     */
    public function link($uri = null, $params = [])
    {
        $uri = trim($uri, '/');
        $link = $this->baseUri . $uri;
        if (!empty($params)) {
            $link .= '?' . http_build_query($params);
        }

        return $link;
    }

    public function adminLink($uri, $params = [])
    {
        $uri = trim($uri, '/');
        $uri = ADMIN_PREFIX . '/' . $uri;

        return $this->link($uri, $params);
    }
}

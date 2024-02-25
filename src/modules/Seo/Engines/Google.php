<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Seo\Engines;

use Symfony\Component\HttpClient\HttpClient;

class Google implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getDetails()
    {
        return [
            'id' => 'google',
            'name' => 'Google',
        ];
    }

    public function pingSitemap(string $url)
    {
        $link = 'https://www.google.com/ping';
        $httpClient = HttpClient::create(['bindto' => BIND_TO]);

        $request = $httpClient->request('GET', $link, [
            'query' => [
                'sitemap' => $url,
            ],
        ]);

        return $request->getStatusCode() == 200;
    }
}

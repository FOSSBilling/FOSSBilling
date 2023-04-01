<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2023
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Seo\Engines;

class Google implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
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
        
        $request = $this->di['http_client']->request('GET', $link, [
            'query' => [
                'sitemap'   => $url,
            ],
        ]);

        return $request->getStatusCode() == 200;
    }
}
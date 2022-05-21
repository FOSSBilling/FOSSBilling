<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_Url implements Box\InjectionAwareInterface
{
    protected $di;
    protected $baseUri;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function setBaseUri($baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Generates a URL
     */
    public function get ($uri)
    {
        return $this->baseUri . $uri;
    }

    /**
     * @param string $uri
     */
    public function link($uri = null, $params = array())
    {
        $uri = trim($uri, '/');
        $link =$this->baseUri .'index.php?_url=/' . $uri;
        if(BB_SEF_URLS) {
            $link = $this->baseUri . $uri;
            if (!empty($params)){
                $link .= '?';
            }
        }

        if(!empty($params)) {
            $link  .= '&' . http_build_query($params);
        }
        return $link;
    }

    public function adminLink($uri, $params = array())
    {
        $uri = trim($uri, '/');
        $prefix = $this->di['config']['admin_area_prefix'];
        $uri = $prefix . '/' . $uri;
        return $this->link($uri, $params);
    }
}
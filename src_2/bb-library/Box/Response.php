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


class Box_Response implements \Box\InjectionAwareInterface
{
    protected $content = '';
    protected $content_type;
    protected $json_content;
    protected $is_sent;
    protected $code;
    protected $etag;
    protected $status;
    protected $headers;
    protected $cookies;
    protected $di;

    public function __construct ($content = null, $code = null, $status = null)
    {

    }

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param mixed $etag
     */
    public function setEtag($etag)
    {
        $this->etag = $etag;
    }

    /**
     * @param mixed $contentType
     */
    public function setContentType($contentType, $charset = null)
    {
        $this->content_type = $contentType;
    }

    public function redirect($location, $externalRedirect = false, $statusCode = null)
    {

    }
    
    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    public function appendContent($content)
    {
        $this->content .= $content;
    }
    
    /**
     * @param mixed $json_content
     */
    public function setJsonContent($json_content)
    {
        $this->json_content = $json_content;
    }

    /**
     * @return bool
     */
    public function isSent()
    {
        return (bool)$this->is_sent;
    }


    public function sendHeaders()
    {

    }
    
    public function sendCookies()
    {
        
    }
    
    public function send()
    {

        $this->is_sent = true;
    }

    public function setFileToSend($filePath, $attachmentName = null)
    {
        
    }

}
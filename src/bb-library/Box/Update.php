<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Box_Update
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    private $_url = 'http://api.boxbilling.com/compare-version.php';

    public function __construct()
    {
        if(defined('BB_VERSION_URL') && BB_VERSION_URL) {
            $this->_url = BB_VERSION_URL;
        }
    }

    /**
     * Returns latest information
     */
    private function _getLatestVersionInfo()
    {
        return $this->di['tools']->cache_function(array($this, 'getJson'), array(), 86400);
    }

    /**
     * Return latest version number
     * @return string
     */
    public function getLatestVersion()
    {
        $response = $this->_getLatestVersionInfo();
        if(!is_object($response)) {
            return Box_Version::VERSION;
        }
        return $response->version;
    }

    /**
     * Latest version link
     * @return string
     */
    public function getLatestVersionDownloadLink()
    {
        $response = $this->_getLatestVersionInfo();
        if(isset($response->update)) {
            return $response->update;
        }
        return $response->link;
    }

    /**
     * Check if we need to update current BoxBilling version
     * @return bool
     */
    public function getCanUpdate()
    {
        $version = $this->getLatestVersion();
        $result = Box_Version::compareVersion($version);
        return ($result > 0);
    }

    /**
     * Check if given file is same as original
     * @param string $file - filepath
     * @return bool
     */
    private function isHashValid($file)
    {
        if(!file_exists($file)) {
            return false;
        }
        
        $response = $this->_getLatestVersionInfo();
        $hash = md5($response->version.filesize($file));
        return ($hash == $response->hash);
    }

    public function getJson()
    {
        $url = $this->_url . '?current=' . Box_Version::VERSION;
        $curl = new Box_Curl($url);
        $curl->request();
        $response = $curl->getBody();
        return json_decode($response);
    }

    /**
     * Perform update
     *
     * @throws Exception
     */
    public function performUpdate()
    {
        if(!$this->getCanUpdate()) {
            throw new LogicException('You have latest version of BoxBilling. You do not need to update.');
        }

        error_log('Started BoxBilling auto-update script');
        $latest_version = $this->getLatestVersion();
        $latest_version_archive = BB_PATH_CACHE.DIRECTORY_SEPARATOR.$latest_version.'.zip';

        // download latest archive from link
        $content = $this->di['tools']->file_get_contents($this->getLatestVersionDownloadLink());
        $f = fopen($latest_version_archive,'wb');
        fwrite($f,$content,strlen($content));
        fclose($f);

        //@todo validate downloaded file hash

        // Extract latest archive on top of current version
        $ff = new Box_Zip($latest_version_archive);
        $ff->decompress(BB_PATH_ROOT);

        if(file_exists(BB_PATH_ROOT.'/bb-update.php')) {
            error_log('Calling bb-update.php script from auto-updater');
            $this->di['tools']->file_get_contents(BB_URL.'bb-update.php');
        }
        
        // clean up things
        $this->di['tools']->emptyFolder(BB_PATH_CACHE);
        $this->di['tools']->emptyFolder(BB_PATH_ROOT.'/install');
        rmdir(BB_PATH_ROOT.'/install');
        return true;
    }
}
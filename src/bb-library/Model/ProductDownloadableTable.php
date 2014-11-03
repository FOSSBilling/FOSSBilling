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


class Model_ProductDownloadableTable extends Model_ProductTable
{
    /**
     * @deprecated copied to \Product\Service
     */
    public function getSavePath($filename = null)
    {
        $path = BB_PATH_DATA.'/uploads/';
        if(null !== $filename) {
            $path .= md5($filename);
        }
        return $path;
    }

    /**
     * @deprecated copied to \Product\Service
     */
    public function removeOldFile($config)
    {
        if(isset($config['filename'])) {
            $f = $this->getSavePath($config['filename']);
            if(file_exists($f)) {
                unlink($f);
            }
        }
    }
}
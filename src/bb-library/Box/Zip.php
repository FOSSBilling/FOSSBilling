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

class Box_Zip
{
    private $zip = null;

    function __construct($zip)
    {
        $this->zip = $zip;
    }

    function decompress($to)
    {
        if(!file_exists($this->zip)) {
            throw new \Box_Exception('File :file does not exist', array(':file'=>$this->zip));
        }

        $zip = new \PhpZip\ZipFile();
        try{
            $zip->openFile($this->zip);
            $zip->extractTo($to);
            $zip->close();

            return true;
        }
        catch(\PhpZip\Exception\ZipException $e){
            $zip->close();
            throw new \Box_Exception('Failed to extract file! Exception:<br>' . $e);
        }
    }
}


<?php declare(strict_types=1);
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
 */

namespace FOSSBilling;

class Request implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Gets most possible client IPv4 Address. This method search in $_SERVER[‘REMOTE_ADDR’] and optionally in $_SERVER[‘HTTP_X_FORWARDED_FOR’]
     * @param bool $trustForwardedHeader - No by default because this can be changed to anything extremely easy, making it unreliable for tracking and adding a potential source for external data to be executed.
     * Please see: https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
     * return string
     */
    public function getClientAddress($trustForwardedHeader = false)
    {
        $address = null;
        if($trustForwardedHeader) {
            $address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        }
        if(is_null($address)) {
            $address = $_SERVER['REMOTE_ADDR'] ?? null;
        }
        if(is_string($address)) {
            if(strpos($address, ',') !== false) {
                list($address) = explode(',', $address);
            }
        }
        return $address;
    }

    /**
     * Checks whether request includes attached files
     * @return int - number of files
     */
    public function hasFiles($onlySuccessful = true)
    {
        $number_of_files = 0;
        $number_of_successful_files = 0;
        foreach($_FILES as $file) {
            $number_of_files++;
            if(isset($file['error']) && $file['error'] == 0) {
                $number_of_successful_files++;
            }
        }
        return ($onlySuccessful)  ? $number_of_successful_files : $number_of_files;
    }

    /**
     * Gets attached files as SplFileInfo collection
     * @return array
     */
    public function getUploadedFiles($onlySuccessful = true)
    {
        $files = array();
        foreach($_FILES as $file) {
            $f = new \FOSSBilling\RequestFile($file);
            if($onlySuccessful) {
                if($file['error'] == 0) {
                    $files[] = $f;
                }
            } else {
                $files[] = $f;
            }
        }
        return $files;
    }
}

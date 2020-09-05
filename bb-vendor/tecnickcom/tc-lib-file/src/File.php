<?php
/**
 * File.php
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 *
 * This file is part of tc-lib-file software library.
 */

namespace Com\Tecnick\File;

use \Com\Tecnick\File\Exception as FileException;

/**
 * Com\Tecnick\File\File
 *
 * Function to read byte-level data
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 */
class File
{
    /**
     * Wrapper to use fopen only with local files
     *
     * @param string $filename Name of the file to open
     * @param string $mode     The fopen mode parameter specifies the type of access you require to the stream
     *
     * @return resource Returns a file pointer resource on success
     *
     * @throws FileException in case of error
     */
    public function fopenLocal($filename, $mode)
    {
        if (strpos($filename, '://') === false) {
            $filename = 'file://'.$filename;
        } elseif (strpos($filename, 'file://') !== 0) {
            throw new FileException('this is not a local file');
        }
        $handler = @fopen($filename, $mode);
        if ($handler === false) {
            throw new FileException('unable to open the file: '.$filename);
        }
        return $handler;
    }

    /**
     * Read a 4-byte (32 bit) integer from file.
     *
     * @param resource $handle A file system pointer resource that is typically created using fopen().
     *
     * @return int 4-byte integer
     */
    public function fReadInt($handle)
    {
        $val = unpack('Ni', fread($handle, 4));
        return $val['i'];
    }

    /**
     * Binary-safe file read.
     * Reads up to length bytes from the file pointer referenced by handle.
     * Reading stops as soon as one of the following conditions is met:
     * length bytes have been read; EOF (end of file) is reached.
     *
     * @param resource $handle A file system pointer resource that is typically created using fopen().
     * @param int      $length Number of bytes to read.
     *
     * @return string
     *
     * @throws FileException in case of error
     */
    public function rfRead($handle, $length)
    {
        $data = @fread($handle, $length);
        if ($data === false) {
            throw new FileException('unable to read the file');
        }
        $rest = ($length - strlen($data));
        if (($rest > 0) && !feof($handle)) {
            $stream_meta_data = stream_get_meta_data($handle);
            if ($stream_meta_data['unread_bytes'] > 0) {
                $data .= $this->rfRead($handle, $rest);
            }
        }
        return $data;
    }

    /**
     * Reads entire file into a string.
     * The file can be also an URL.
     *
     * @param string $file Name of the file or URL to read.
     *
     * @return string File content
     */
    public function fileGetContents($file)
    {
        $alt = $this->getAltFilePaths($file);
        foreach ($alt as $path) {
            $ret = $this->getFileData($path);
            if ($ret !== false) {
                return $ret;
            }
        }
        throw new FileException('unable to read the file: '.$file);
    }

    /**
     * Reads entire file into a string.
     * The file can be also an URL if the URL wrappers are enabled.
     *
     * @param string $file Name of the file or URL to read.
     *
     * @return string File content or FALSE in case the file is unreadable
     */
    public function getFileData($file)
    {
        $ret = @file_get_contents($file);
        if ($ret !== false) {
            return $ret;
        }
        // try to use CURL for URLs
        return $this->getUrlData($file);
    }

    /**
     * Reads entire remote file into a string using CURL
     *
     * @param string $url URL to read.
     *
     * @return string File content or FALSE in case the file is unreadable or curl is not available
     */
    public function getUrlData($url)
    {
        if ((ini_get('allow_url_fopen') && !defined('FORCE_CURL'))
            || !function_exists('curl_init')
            || !preg_match('%^(https?|ftp)://%', $url)
        ) {
            return false;
        }
        // try to get remote file data using cURL
        $crs = curl_init();
        curl_setopt($crs, CURLOPT_URL, $url);
        curl_setopt($crs, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($crs, CURLOPT_FAILONERROR, true);
        curl_setopt($crs, CURLOPT_RETURNTRANSFER, true);
        if ((ini_get('open_basedir') == '') && (!ini_get('safe_mode'))) {
            curl_setopt($crs, CURLOPT_FOLLOWLOCATION, true);
        }
        curl_setopt($crs, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($crs, CURLOPT_TIMEOUT, 30);
        curl_setopt($crs, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($crs, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($crs, CURLOPT_USERAGENT, 'tc-lib-file');
        $ret = curl_exec($crs);
        curl_close($crs);
        return $ret;
    }

    /**
     * Returns an array of possible alternative file paths or URLs
     *
     * @param string $file Name of the file or URL to read.
     *
     * @return array
     */
    public function getAltFilePaths($file)
    {
        $alt = array($file);
        $alt[] = $this->getAltLocalUrlPath($file);
        $url = $this->getAltMissingUrlProtocol($file);
        $alt[] = $url;
        $alt[] = $this->getAltPathFromUrl($url);
        $alt[] = $this->getAltUrlFromPath($file);
        return array_unique($alt);
    }

    /**
     * Replace URL relative path with full real server path
     *
     * @param string $file Relative URL path
     *
     * @return string
     */
    protected function getAltLocalUrlPath($file)
    {
        if ((strlen($file) > 1)
            && ($file[0] === '/')
            && ($file[1] !== '/')
            && !empty($_SERVER['DOCUMENT_ROOT'])
            && ($_SERVER['DOCUMENT_ROOT'] !== '/')
        ) {
            $findroot = strpos($file, $_SERVER['DOCUMENT_ROOT']);
            if (($findroot === false) || ($findroot > 1)) {
                $file = htmlspecialchars_decode(urldecode($_SERVER['DOCUMENT_ROOT'].$file));
            }
        }
        return $file;
    }

    /**
     * Add missing local URL protocol
     *
     * @param string $file Relative URL path
     *
     * @return string local path or original $file
     */
    protected function getAltMissingUrlProtocol($file)
    {
        if (preg_match('%^//%', $file) && !empty($_SERVER['HTTP_HOST'])) {
            $file = $this->getDefaultUrlProtocol().':'.str_replace(' ', '%20', $file);
        }
        return htmlspecialchars_decode($file);
    }

    /**
     * Get the default URL protocol (http or https)
     *
     * @return string
     */
    protected function getDefaultUrlProtocol()
    {
        $protocol = 'http';
        if (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) {
            $protocol .= 's';
        }
        return $protocol;
    }

    /**
     * Add missing local URL protocol
     *
     * @param string $url Relative URL path
     *
     * @return string local path or original $file
     */
    protected function getAltPathFromUrl($url)
    {
        if (!preg_match('%^(https?)://%', $url)
            || empty($_SERVER['HTTP_HOST'])
            || empty($_SERVER['DOCUMENT_ROOT'])
        ) {
            return $url;
        }
        $urldata = parse_url($url);
        if (!empty($urldata['query'])) {
            return $url;
        }
        $host = $this->getDefaultUrlProtocol().'://'.$_SERVER['HTTP_HOST'];
        if (strpos($url, $host) === 0) {
            // convert URL to full server path
            $tmp = str_replace($host, $_SERVER['DOCUMENT_ROOT'], $url);
            return htmlspecialchars_decode(urldecode($tmp));
        }
        return $url;
    }

    /**
     * Get an alternate URL from a file path
     *
     * @param string $file File name and path
     *
     * @return string
     */
    protected function getAltUrlFromPath($file)
    {
        if (isset($_SERVER['SCRIPT_URI'])
            && !preg_match('%^(https?|ftp)://%', $file)
            && !preg_match('%^//%', $file)
        ) {
            $urldata = @parse_url($_SERVER['SCRIPT_URI']);
            return $urldata['scheme'].'://'.$urldata['host'].(($file[0] == '/') ? '' : '/').$file;
        }
        return $file;
    }
}

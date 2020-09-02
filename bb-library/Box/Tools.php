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


class Box_Tools
{
    protected $di = null;

    /**
     * @param Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }

    public function file_put_contents($content, $target, $mode = 'wt')
    {
        $fp = @fopen($target, $mode);

        if ($fp) {
            $bytes = fwrite($fp, $content);
            fclose($fp);
            return $bytes;
        } else {
            $error = error_get_last();

            throw new RuntimeException(
                sprintf(
                    'Could not write to %s: %s',
                    $target,
                    substr(
                        $error['message'],
                        strpos($error['message'], ':') + 2
                    )
                )
            );
        }
    }

    public function file_get_contents($filename, $use_include_path = false, $context = null, $offset = -1)
    {
        return file_get_contents($filename, $use_include_path, $context, $offset);
    }

    public function get_url($url, $timeout = 10)
    {
        $ch = curl_init();
        $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $data = curl_exec($ch);
        if($data === false) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }
        curl_close($ch);
        return $data;
    }

    /**
     * Return site url
     * @return string
     */
    public function url($link = null)
    {
        $link = trim($link, '/');
        if(BB_SEF_URLS) {
            return BB_URL . $link;
        }

        return BB_URL .'index.php?_url=/' . $link;
    }
    
    public function hasService($type)
    {
        $file = BB_PATH_MODS . '/mod_'.$type.'/Service.php';
        return file_exists($file);
    }
    
    public function getService($type)
    {
        $class = 'Box_Mod_'.ucfirst($type).'_Service';
        $file = BB_PATH_MODS . '/mod_'.$type.'/Service.php';
        if(!file_exists($file)){
            throw new \Box_Exception('Service class :class was not found in :path', array(':class'=>$class,':path'=>$file));
        }
        require_once $file;
    	return new $class();
    }
    
    /**
     * Get client IP
     * @return string
     */
    public function getIpv4()
    {
        $ip = NULL;
        if (isset($_SERVER) ) {
            if (isset($_SERVER['REMOTE_ADDR']) ) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $iplist = explode(',', $ip);
                if(is_array($iplist)) {
                    $ip = trim(array_pop($iplist));
                }
            }
        }
        return $ip;
    }

    public function checkPerms($path, $perm = '0777')
    {
        clearstatcache();
        $configmod = substr(sprintf('%o', fileperms($path)), -4);
        $int = (int)$configmod;
        if($configmod == $perm) {
            return true;
        }

        if((int)$configmod < (int)$perm) {
            return true;
        }
        return false;
    }

    public function emptyFolder($folder)
    {
        if(!is_dir($folder)) {
            return;
        }
        $d = dir($folder);
        while (false !== ($entry = $d->read())) {
            $isdir = is_dir($folder."/".$entry);
            if (!$isdir && $entry!="." && $entry!="..") {
                unlink($folder."/".$entry);
            } elseif ($isdir  &&  $entry!="." && $entry!="..") {
                $this->emptyFolder($folder."/".$entry);
                rmdir($folder."/".$entry);
            }
        }
        $d->close();
    }

    /**
     * Returns referer url
     * @return string
     */
    public function getReferer($default = '/')
    {
        $r = empty($_SERVER['HTTP_REFERER']) ? $default : $_SERVER['HTTP_REFERER'];
        return $r;
    }

    /**
     * Generates random password
     * @param int $length
     * @param int $strength
     * @return string
     */
    public function generatePassword($length=8, $strength=3) {
    	$upper = 0;
    	$lower = 0;
    	$numeric = 0;
    	$other = 0;

    	$upper_letters = 'QWERTYUIOPASDFGHJKLZXCVBNM';
    	$lower_letters = 'qwertyuiopasdfghjklzxccvbnm';
    	$numbers = '1234567890';
    	$symbols = '!@#$%&?()+-_';

		switch ($strength) {
			//lowercase
			case 1:
				$lower = $length;
			break;
			//lowercase + numeric
			case 2:
				$lower = rand(1, $length - 1);
				$numeric = $length - $lower;
			break;
			//lowercase + uppsercase + numeric
			case 3:
				$lower = rand(1, $length - 2);
				$upper = rand(1, $length - $lower - 1);
				$numeric = $length - $lower - $upper;
			break;
			//lowercase + uppercase + numeric + symbols
            case 4:
			default:
				$lower = rand(1, $length - 3);
				$upper = rand(1, $length - $lower - 2);
				$numeric = rand(1, $length - $lower - $upper - 1);
				$other = $length - $lower - $upper - $numeric;
			break;
		}

        $passOrder = array();

		for ($i = 0; $i < $upper; $i++) {
        	$passOrder[] = $upper_letters[rand() % strlen($upper_letters)];
    	}
    	for ($i = 0; $i < $lower; $i++) {
        	$passOrder[] = $lower_letters[rand() % strlen($lower_letters)];
    	}
    	for ($i = 0; $i < $numeric; $i++) {
        	$passOrder[] = $numbers[rand() % strlen($numbers)];
    	}
    	for ($i = 0; $i < $other; $i++) {
        	$passOrder[] = $symbols[rand() % strlen($symbols)];
    	}

    	shuffle($passOrder);
    	$password = implode('', $passOrder);

        return $password;
    }

    public function autoLinkText($text)
    {
       $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';
       $callback = function($matches){
           $url       = array_shift($matches);
           $url_parts = parse_url($url);
           if(!isset($url_parts["scheme"])) {
              $url = "http://".$url;
           }
           return sprintf('<a target="_blank" href="%s">%s</a>', $url, $url);
        };
       return preg_replace_callback($pattern, $callback, $text);
    }

    public function getResponseCode($theURL)
    {
        $headers = get_headers($theURL);
        return substr($headers[0], 9, 3);
    }

    public function slug($str)
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', "-", $str);
        $str = trim($str, '-');
        return $str;
    }

    public function escape($string)
    {
    	$string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    	return stripslashes($string);
    }

    /**
     * @Deprecated not used anywhere
     * @param $filename
     * @return mixed|string
     */
    public function get_mime_content_type($filename)
    {
        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $ext = explode('.',$filename);
        $ext = array_pop($ext);
        $ext = strtolower($ext);
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }

    public function to_camel_case($str, $capitalise_first_char = false) {
        if($capitalise_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        $func = function($c){ return strtoupper($c[1]); };
        return preg_replace_callback('/-([a-z])/', $func, $str);
    }

    public function from_camel_case($str) {
        $str[0] = strtolower($str[0]);
        $func = function($c){ return "-" . strtolower($c[1]); };
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    public function decodeJ($json_str)
    {
        $config = json_decode($json_str, true);
        return is_array($config) ? $config : array();
    }
    
    public function sortByOneKey(array $array, $key, $asc = true) {
        $result = array();

        $values = array();
        foreach ($array as $id => $value) {
            $values[$id] = isset($value[$key]) ? $value[$key] : '';
        }

        if ($asc) {
            asort($values);
        }
        else {
            arsort($values);
        }

        foreach ($values as $key => $value) {
            $result[$key] = $array[$key];
        }

        return $result;
    }
    
    public function cache_function($buildCallback, array $args = array(), $timeoutSeconds = 3600)
    {
            // Set up the filename for the cache file 
            if(is_array($buildCallback)){
                    $cacheKey = get_class($buildCallback[0]) .'::'. $buildCallback[1];
            }else{
                    $cacheKey = $buildCallback . ':' . implode(':', $args);
            }
            $cacheKey .= ':' . implode(':', $args);
            $file_path = BB_PATH_CACHE .DIRECTORY_SEPARATOR. md5($cacheKey);

            // If the file hasn't yet been created or is out of date then call the require function and store it's result.
            if(!file_exists($file_path) || filemtime($file_path) < (time() - $timeoutSeconds)){
                    $result = call_user_func_array($buildCallback, $args);
                    file_put_contents($file_path, serialize($result), LOCK_EX);
            // Else, grab the result from the cache.
            }else{
                    $result = unserialize(file_get_contents($file_path));
            }

            return $result;
    }

    public function fileExists($file)
    {
        return file_exists($file);
    }

    public function rename($old, $new)
    {
        return rename($old, $new);
    }

    public function unlink($file)
    {
        return unlink($file);
    }

    public function mkdir($destination, $perm, $recursive = false)
    {
        return mkdir($destination, $perm, $recursive);
    }

    public function glob($pattern, $flag = 0)
    {
        return glob($pattern, $flag);
    }

    public function getTable($type)
    {
        $class = 'Model_'.ucfirst($type).'Table';
        $file = BB_PATH_LIBRARY . '/Model/'.$type.'Table.php';
        if(!file_exists($file)){
            throw new \Box_Exception('Service class :class was not found in :path', array(':class'=>$class,':path'=>$file));
        }
        require_once $file;
        return new $class();
    }

    public function getPairsForTableByIds($table, $ids)
    {
        if (empty ($ids)) {
            return array();
        }

        $slots = (count($ids)) ? implode(',', array_fill(0, count($ids), '?')) : ''; //same as RedBean genSlots() method

        $rows = $this->di['db']->getAll('SELECT id, title FROM ' . $table . ' WHERE id in (' . $slots . ')', $ids);

        $result = array();
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

}

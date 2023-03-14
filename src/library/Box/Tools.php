<?php

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

    public function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $useoffset = true)
    {
        if ($useoffset) {
            return file_get_contents($filename, $use_include_path, $context, $offset);
        } else {
            return file_get_contents($filename, $use_include_path, $context);
        }
    }

    /**
     * Return site url
     * @return string
     */
    public function url($link = null)
    {
        $link = trim($link, '/');
        if (BB_SEF_URLS) {
            return BB_URL . $link;
        }

        return BB_URL . 'index.php?_url=/' . $link;
    }

    public function hasService($type)
    {
        $file = PATH_MODS . '/mod_' . $type . '/Service.php';
        return file_exists($file);
    }

    public function getService($type)
    {
        $class = 'Box_Mod_' . ucfirst($type) . '_Service';
        $file = PATH_MODS . '/mod_' . $type . '/Service.php';
        if (!file_exists($file)) {
            throw new \Box_Exception('Service class :class was not found in :path', array(':class' => $class, ':path' => $file));
        }
        require_once $file;
        return new $class();
    }

    public function checkPerms($path, $perm = '0777')
    {
        clearstatcache();
        $configmod = substr(sprintf('%o', fileperms($path)), -4);
        $int = (int)$configmod;
        if ($configmod == $perm) {
            return true;
        }

        if ((int)$configmod < (int)$perm) {
            return true;
        }
        return false;
    }

    public function emptyFolder($folder)
    {
        /* Original source for this lovely codesnippet: https://stackoverflow.com/a/24563703
         * With modification suggested from KeineMaster (replaced $file with$file->getRealPath())
         */
        if (file_exists($folder)) {
            $di = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($ri as $file) {
                $file->isDir() ?  rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
        }
    }

    /**
     * Returns referer url
     * @return string
     */
    public function getReferer($default = '/')
    {
        return empty($_SERVER['HTTP_REFERER']) ? $default : $_SERVER['HTTP_REFERER'];
    }

    /**
     * Generates random password
     * @param int $length
     * @param int $strength
     * @return string
     */
    public function generatePassword($length = 8, $strength = 3)
    {
        $upper = 0;
        $lower = 0;
        $numeric = 0;
        $other = 0;

        $upper_letters = 'QWERTYUIOPASDFGHJKLZXCVBNM';
        $lower_letters = 'qwertyuiopasdfghjklzxccvbnm';
        $numbers = '1234567890';
        $symbols = '!@#$%&?()+-_';

        switch ($strength) {
                //lowercase + uppsercase + numeric
            case 3:
                $lower = random_int(1, $length - 2);
                $upper = random_int(1, $length - $lower - 1);
                $numeric = $length - $lower - $upper;
                break;
                //lowercase + uppercase + numeric + symbols
            case 4:
            default:
                $lower = random_int(1, $length - 3);
                $upper = random_int(1, $length - $lower - 2);
                $numeric = random_int(1, $length - $lower - $upper - 1);
                $other = $length - $lower - $upper - $numeric;
                break;
        }

        $passOrder = array();

        for ($i = 0; $i < $upper; $i++) {
            $passOrder[] = $upper_letters[random_int(0, getrandmax()) % strlen($upper_letters)];
        }
        for ($i = 0; $i < $lower; $i++) {
            $passOrder[] = $lower_letters[random_int(0, getrandmax()) % strlen($lower_letters)];
        }
        for ($i = 0; $i < $numeric; $i++) {
            $passOrder[] = $numbers[random_int(0, getrandmax()) % strlen($numbers)];
        }
        for ($i = 0; $i < $other; $i++) {
            $passOrder[] = $symbols[random_int(0, getrandmax()) % strlen($symbols)];
        }

        shuffle($passOrder);
        return implode('', $passOrder);
    }

    public function autoLinkText($text)
    {
        $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';
        $callback = function ($matches) {
            $url       = array_shift($matches);
            $url_parts = parse_url($url);
            if (!isset($url_parts["scheme"])) {
                $url = "http://" . $url;
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

    public function to_camel_case($str, $capitalise_first_char = false)
    {
        if ($capitalise_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        $func = function ($c) {
            return strtoupper($c[1]);
        };
        return preg_replace_callback('/-([a-z])/', $func, $str);
    }

    public function from_camel_case($str)
    {
        $str[0] = strtolower($str[0]);
        $func = function ($c) {
            return "-" . strtolower($c[1]);
        };
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    public function decodeJ($json_str)
    {
        if (isset($json_str)) {
            $config = json_decode($json_str, true);
            return is_array($config) ? $config : array();
        } else {
            return array();
        }
    }

    public function sortByOneKey(array $array, $key, $asc = true)
    {
        $result = array();

        $values = array();
        foreach ($array as $id => $value) {
            $values[$id] = isset($value[$key]) ? $value[$key] : '';
        }

        if ($asc) {
            asort($values);
        } else {
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
        if (is_array($buildCallback)) {
            $cacheKey = get_class($buildCallback[0]) . '::' . $buildCallback[1];
        } else {
            $cacheKey = $buildCallback . ':' . implode(':', $args);
        }
        $cacheKey .= ':' . implode(':', $args);
        $file_path = PATH_CACHE . DIRECTORY_SEPARATOR . md5($cacheKey);

        // If the file hasn't yet been created or is out of date then call the require function and store it's result.
        if (!file_exists($file_path) || filemtime($file_path) < (time() - $timeoutSeconds)) {
            $result = call_user_func_array($buildCallback, $args);
            file_put_contents($file_path, serialize($result), LOCK_EX);
            // Else, grab the result from the cache.
        } else {
            $result = unserialize(file_get_contents($file_path));
        }

        return $result;
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d Default imageset to use [ 404 | mp | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    public function get_gravatar($email, $size = 80, $d = 'mp', $r = 'g', $img = false, $atts = array())
    {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$size&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val)
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
        return $url;
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
        $class = 'Model_' . ucfirst($type) . 'Table';
        $file = PATH_LIBRARY . '/Model/' . $type . 'Table.php';
        if (!file_exists($file)) {
            throw new \Box_Exception('Service class :class was not found in :path', array(':class' => $class, ':path' => $file));
        }
        require_once $file;
        return new $class();
    }

    public function getPairsForTableByIds($table, $ids)
    {
        if (empty($ids)) {
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

    public function updateConfig()
    {
        $configPath = PATH_ROOT . '/config.php';
        $currentConfig = include $configPath;

        if (!is_array($currentConfig)) {
            throw new \Box_Exception('Unable to load existing configuration. updateConfig() is unable to progress.');
        }
        if (!copy($configPath, $configPath . '.old')) {
            throw new \Box_Exception('Unable to create backup of configuration file. Canceling config migration.');
        }

        $newConfig = $currentConfig;

        $newConfig['security']['mode'] ??= 'strict';
        $newConfig['security']['force_https'] ??= true;
        $newConfig['security']['cookie_lifespan'] ??= '7200';

        $newConfig['update_branch'] ??= 'release';
        $newConfig['log_stacktrace'] ??= true;
        $newConfig['stacktrace_length'] ??= 25;

        $newConfig['maintenance_mode']['enabled'] ??= false;
        $newConfig['maintenance_mode']['allowed_urls'] ??= [];
        $newConfig['maintenance_mode']['allowed_ips'] ??= [];

        $newConfig['disable_auto_cron'] ??= false;
        $newConfig['locale_date_format'] = ($currentConfig['locale_date_format'] === '%A, %d %B %G') ? 'l, d F o' : $currentConfig['locale_date_format'];
        $newConfig['locale_time_format'] = ($currentConfig['locale_time_format'] === ' %T') ? ' G:i:s' : $currentConfig['locale_time_format'];

        $newConfig['db']['port'] ??= '3306';

        $newConfig['api']['throttle_delay'] ??= 2;
        $newConfig['api']['rate_span_login'] ??= 60;
        $newConfig['api']['rate_limit_login'] ??= 20;
        $newConfig['api']['CSRFPrevention'] ??= true;

        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($newConfig, true) . ';';
        if (file_put_contents($configPath, $output)) {
            return true;
        } else {
            throw new \Box_Exception('Error when writing updated configuration file.');
        }
    }

    public function validateAndSanitizeEmail($email, $throw = true)
    {
        $email = htmlspecialchars($email);

        if (!filter_var(idn_to_ascii($email), FILTER_VALIDATE_EMAIL)) {
            if ($throw) {
                throw new \Box_Exception('Email address is invalid');
            } else {
                return false;
            }
        }

        return $email;
    }
}

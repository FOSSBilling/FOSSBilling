<?php

declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;

class Tools
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
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

            throw new \RuntimeException(sprintf('Could not write to %s: %s', $target, substr($error['message'], strpos($error['message'], ':') + 2)));
        }
    }

    /**
     * Return site url.
     *
     * @return string
     */
    public function url($link = null)
    {
        $link = trim($link, '/');

        return SYSTEM_URL . $link;
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
            throw new Exception('Service class :class was not found in :path', [':class' => $class, ':path' => $file]);
        }
        require_once $file;

        return new $class();
    }

    public function checkPerms($path, $perm = '0777')
    {
        clearstatcache();
        $configmod = substr(sprintf('%o', fileperms($path)), -4);
        $int = (int) $configmod;
        if ($configmod == $perm) {
            return true;
        }

        if ((int) $configmod < (int) $perm) {
            return true;
        }

        return false;
    }

    public function emptyFolder($folder)
    {
        /* Original source for this lovely code snippet: https://stackoverflow.com/a/24563703
         * With modification suggested from KeineMaster (replaced $file with$file->getRealPath())
         */
        if (file_exists($folder)) {
            $di = new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS);
            $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($ri as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
        }
    }

    /**
     * Generates random password.
     *
     * @param int $length
     * @param int $strength
     *
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
            // lowercase + uppercase + numeric
            case 3:
                $lower = random_int(1, $length - 2);
                $upper = random_int(1, $length - $lower - 1);
                $numeric = $length - $lower - $upper;

                break;
                // lowercase + uppercase + numeric + symbols
            case 4:
            default:
                $lower = random_int(1, $length - 3);
                $upper = random_int(1, $length - $lower - 2);
                $numeric = random_int(1, $length - $lower - $upper - 1);
                $other = $length - $lower - $upper - $numeric;

                break;
        }

        $passOrder = [];

        for ($i = 0; $i < $upper; ++$i) {
            $passOrder[] = $upper_letters[random_int(0, mt_getrandmax()) % strlen($upper_letters)];
        }
        for ($i = 0; $i < $lower; ++$i) {
            $passOrder[] = $lower_letters[random_int(0, mt_getrandmax()) % strlen($lower_letters)];
        }
        for ($i = 0; $i < $numeric; ++$i) {
            $passOrder[] = $numbers[random_int(0, mt_getrandmax()) % strlen($numbers)];
        }
        for ($i = 0; $i < $other; ++$i) {
            $passOrder[] = $symbols[random_int(0, mt_getrandmax()) % strlen($symbols)];
        }

        shuffle($passOrder);

        return implode('', $passOrder);
    }

    public function autoLinkText($text)
    {
        $pattern = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';
        $callback = function ($matches) {
            $url = array_shift($matches);
            $url_parts = parse_url($url);
            if (!isset($url_parts['scheme'])) {
                $url = 'http://' . $url;
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
        $str = preg_replace('/-+/', '-', $str);
        $str = trim($str, '-');

        return $str;
    }

    public function escape($string)
    {
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        return stripslashes($string);
    }

    public function to_camel_case($str, $capitalize_first_char = false)
    {
        if ($capitalize_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        $func = fn ($c) => strtoupper($c[1]);

        return preg_replace_callback('/-([a-z])/', $func, $str);
    }

    public function from_camel_case($str)
    {
        $str[0] = strtolower($str[0]);
        $func = fn ($c) => '-' . strtolower($c[1]);

        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    public function decodeJ($json_str)
    {
        if (isset($json_str) && is_string($json_str)) {
            $config = json_decode($json_str, true);

            return is_array($config) ? $config : [];
        } else {
            return [];
        }
    }

    public function sortByOneKey(array $array, $key, $asc = true)
    {
        $result = [];

        $values = [];
        foreach ($array as $id => $value) {
            $values[$id] = $value[$key] ?? '';
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

    public function getTable($type)
    {
        $class = 'Model_' . ucfirst($type) . 'Table';
        $file = PATH_LIBRARY . '/Model/' . $type . 'Table.php';
        if (!file_exists($file)) {
            throw new Exception('Service class :class was not found in :path', [':class' => $class, ':path' => $file]);
        }
        require_once $file;

        return new $class();
    }

    public function getPairsForTableByIds($table, $ids)
    {
        if (empty($ids)) {
            return [];
        }

        $slots = (is_countable($ids) ? count($ids) : 0) ? implode(',', array_fill(0, is_countable($ids) ? count($ids) : 0, '?')) : ''; // same as RedBean genSlots() method

        $rows = $this->di['db']->getAll('SELECT id, title FROM ' . $table . ' WHERE id in (' . $slots . ')', $ids);

        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    /**
     * Checks if a given email address is valid.
     * In a production environment, this will both check that the email address matches RFC standards as well as validating the domain.
     * In a testing / development environment it will only check the RFC standards.
     */
    public function validateAndSanitizeEmail(string $email, bool $throw = true, bool $checkDNS = true)
    {
        $email = htmlspecialchars($email);

        $validator = new EmailValidator();
        if (Environment::isProduction() && $checkDNS) {
            $validations = new MultipleValidationWithAnd([
                new RFCValidation(),
                new DNSCheckValidation(),
            ]);
        } else {
            $validations = new RFCValidation();
        }

        if (!$validator->isValid($email, $validations)) {
            if ($throw) {
                $friendlyName = ucfirst(__trans('Email address'));

                throw new Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
            } else {
                return false;
            }
        }

        return $email;
    }

    public static function isHTTPS(): bool
    {
        $protocol = $_SERVER['HTTPS'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? '';

        // $_SERVER['HTTPS'] will be set to `on` to indicate HTTPS and the other to will be set to `https`, so either one means we are connected via HTTPS.
        return strcasecmp($protocol, 'on') === 0 || strcasecmp($protocol, 'https') === 0;
    }
}

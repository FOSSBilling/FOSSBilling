<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Tools implements InjectionAwareInterface
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

    public function slug($str)
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', "-", $str);
        $str = trim($str, '-');
        return $str;
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
        $func = fn ($c) => "-" . strtolower($c[1]);
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    public function sortByOneKey(array $array, $key, $asc = true)
    {
        $result = array();

        $values = array();
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
            throw new Exception('Service class :class was not found in :path', array(':class' => $class, ':path' => $file));
        }
        require_once $file;
        return new $class();
    }

    public function getPairsForTableByIds($table, $ids)
    {
        if (empty($ids)) {
            return array();
        }

        $slots = (is_countable($ids) ? count($ids) : 0) ? implode(',', array_fill(0, is_countable($ids) ? count($ids) : 0, '?')) : ''; //same as RedBean genSlots() method

        $rows = $this->di['db']->getAll('SELECT id, title FROM ' . $table . ' WHERE id in (' . $slots . ')', $ids);

        $result = array();
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    public static function isHTTPS(): bool
    {
        $protocol = $_SERVER['HTTPS'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? '';

        // $_SERVER['HTTPS'] will be set to `on` to indicate HTTPS and the other to will be set to `https`, so either one means we are connected via HTTPS.
        return (strcasecmp($protocol, 'on') === 0 || strcasecmp($protocol, 'https') === 0);
    }
}

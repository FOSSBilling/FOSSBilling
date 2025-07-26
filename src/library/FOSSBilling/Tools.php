<?php
declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;

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

    public function slug($str)
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', '-', $str);

        return trim($str, '-');
    }

    public function to_camel_case($str, $capitalize_first_char = false)
    {
        if ($capitalize_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        $func = fn ($c): string => strtoupper($c[1]);

        return preg_replace_callback('/-([a-z])/', $func, $str);
    }

    public function from_camel_case($str)
    {
        $str[0] = strtolower($str[0]);
        $func = fn ($c): string => '-' . strtolower($c[1]);

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
            throw new Exception('Service class :class was not found in :path', [':class' => $class, ':path' => $file]);
        }
        require_once $file;

        return new $class();
    }

    /**
     * @return mixed[]
     */
    public function getPairsForTableByIds($table, $ids): array
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

    public static function isHTTPS(): bool
    {
        $protocol = $_SERVER['HTTPS'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? '';

        // $_SERVER['HTTPS'] will be set to `on` to indicate HTTPS and the others to will be set to `https`, so either one means we are connected via HTTPS.
        return strcasecmp($protocol, 'on') === 0 || strcasecmp($protocol, 'https') === 0;
    }

    /**
     * Tries to fetch a list of possible interfaces (IPs) to bind to when making requests.
     * Attempts to make external requests for each interface & only works with IPv4.
     */
    public static function listHttpInterfaces(): array
    {
        // Fetch a list of IP addresses for local interfaces
        $validatedIps = [];

        try {
            $ips = gethostbynamel(gethostname());
        } catch (\Exception) {
            $ips = [];
        }

        if (!$ips) {
            return [];
        }

        // For each of the found IPs, attempt a generic network request. If the request produces no errors, consider it valid
        foreach ($ips as $ip) {
            try {
                self::getExternalIP(true, $ip);
                $validatedIps[] = $ip;
            } catch (\Exception) {
            }
        }

        return $validatedIps;
    }

    /**
     * Returns the currently configured default network interface.
     * If a custom interface IP address is entered, no validation is performed.
     * However, if we are using an interface IP address that was selected from a given list, we will validate that the IP address is still in the list of known IP address interfaces.
     *
     * @return string|int either the IP address of the interface to use (string) or 0 if there's none set / the set one is invalid
     */
    public static function getDefaultInterface(): string|int
    {
        $customInterface = Config::getProperty('custom_interface_ip', '');
        if (!empty($customInterface)) {
            return $customInterface;
        }

        $interface = Config::getProperty('interface_ip', '0');

        try {
            $knownInterfaces = gethostbynamel(gethostname());
        } catch (\Exception) {
            $knownInterfaces = [];
        }

        if ($interface && $interface !== '0' && in_array($interface, $knownInterfaces)) {
            return $interface;
        }

        return 0;
    }

    /**
     * Returns the public IP address of the current FOSSBilling instance.
     * Will try multiple services in order if they time out.
     * Try order: ipify.org, ifconfig.io, ip.hestiacp.com.
     *
     * @param bool    $throw if the function should throw an exception on an error
     * @param ?string $bind  overrides the default network interface bind. Set to `null` to disable this behavior.
     *
     * @return ?string `null` if there was an error, otherwise an IP address will be returned
     */
    public static function getExternalIP(bool $throw = true, ?string $bind = null): ?string
    {
        $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
        $bind ??= BIND_TO;

        try {
            $client = new RetryableHttpClient(HttpClient::create(['bindto' => $bind]));
            $response = $client->request('GET', '', [
                'base_uri' => $services,
                'timeout' => 2,
            ]);
            $ip = filter_var($response->getContent(), FILTER_VALIDATE_IP);
            if ($ip) {
                return $ip;
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            if ($throw) {
                throw $e;
            }
        }

        return null;
    }

    /**
     * Converts bytes to a human-readable format (B, KB, MB, GB and TB).
     */
    public static function humanReadableBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        return round($bytes / 1099511627776, 2) . ' TB';
    }
}

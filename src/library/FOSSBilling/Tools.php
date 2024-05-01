<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
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
use Symfony\Component\HttpClient\HttpClient;

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
     * Generates a password of a set length and complexity.
     *
     * @param int      $length         the length of the password to generate
     * @param bool|int $includeSpecial If special characters should be included. If 4 is passed, that's considered to be true (added for backwards compatibility).
     *
     * @throws InformationException if it failed to generate a password meeting the requirements within 50 iterations
     */
    public function generatePassword(int $length = 8, bool|int $includeSpecial = false): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialCharacters = '!@#$%&?()+-_';

        // Backwards compatibility with previous behavior
        if (is_int($includeSpecial)) {
            $includeSpecial = $includeSpecial === 4;
        }

        $charSet = $characters . strtoupper($characters) . $numbers;
        if ($includeSpecial) {
            $charSet .= $specialCharacters;
        }

        $charSetLength = strlen($charSet);

        // Loop flow-control
        $valid = false;
        $iterations = 0;

        // Password requirements validation
        $hasLowercase = false;
        $hasUppercase = false;
        $hasNumber = false;
        $hasSpecial = false;

        $password = '';
        while (!$valid && $iterations < 100) {
            // Add a random character to the password from the provided list of acceptable.
            $character = substr($charSet, random_int(0, $charSetLength - 1), 1);
            $password .= $character;

            // Handle validations
            $hasLowercase = $hasLowercase || str_contains($characters, $character);
            $hasUppercase = $hasUppercase || str_contains(strtoupper($characters), $character);
            $hasNumber = $hasNumber || str_contains($numbers, $character);
            $hasSpecial = !$includeSpecial || $hasSpecial || str_contains($specialCharacters, $character);

            // Once we reach the required length, check if the password is valid
            if (strlen($password) === $length) {
                $valid = $hasLowercase && $hasUppercase && $hasNumber && $hasSpecial;
                if (!$valid) {
                    ++$iterations;
                    $password = '';
                    $hasLowercase = false;
                    $hasUppercase = false;
                    $hasNumber = false;
                    $hasSpecial = false;
                }
            }
        }

        if ($valid) {
            return $password;
        } else {
            throw new InformationException('We were unable to generate a password with the required parameters');
        }
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

        return trim($str, '-');
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
        $func = fn ($c): string => strtoupper($c[1]);

        return preg_replace_callback('/-([a-z])/', $func, $str);
    }

    public function from_camel_case($str)
    {
        $str[0] = strtolower($str[0]);
        $func = fn ($c): string => '-' . strtolower($c[1]);

        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    /**
     * @return mixed[]
     */
    public function sortByOneKey(array $array, $key, $asc = true): array
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

                throw new InformationException(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
            } else {
                return false;
            }
        }

        return $email;
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
    public static function getExternalIP(bool $throw = true, string $bind = null): ?string
    {
        $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
        $bind ??= BIND_TO;
        foreach ($services as $service) {
            try {
                $client = HttpClient::create(['bindto' => $bind]);
                $response = $client->request('GET', $service, [
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
        }

        return null;
    }
}

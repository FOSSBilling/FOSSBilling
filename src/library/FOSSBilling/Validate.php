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

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;

class Validate
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
     * Check if second level domain (SLD) is valid.
     */
    public function isSldValid(string $sld): bool
    {
        $sld = ltrim($sld, '.');
        $sld = idn_to_ascii($sld);
        if ($sld === false) {
            return false;
        }
        $sld = strtolower($sld);

        // allow punnycode
        if (str_starts_with($sld, 'xn--')) {
            return true;
        }

        if (preg_match('/^[a-z0-9]+[a-z0-9\-]*[a-z0-9]+$/i', $sld) && strlen($sld) < 64 && substr($sld, 2, 2) != '--') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Validates a given TLD, comparing against the official TLD list by the IANA.
     * In the event that fetching the valid list doesn't work, some very simple validation is performed instead.
     */
    public function isTldValid(string $tld): bool
    {
        $tld = ltrim($tld, '.');
        $tld = idn_to_ascii($tld);
        if ($tld === false) {
            return false;
        }
        $tld = strtolower($tld);

        $validTlds = $this->di['cache']->get('validTlds', function (ItemInterface $item): array {
            $item->expiresAfter(86400);

            $client = HttpClient::create(['bindto' => BIND_TO]);
            $response = $client->request('GET', 'https://publicsuffix.org/list/public_suffix_list.dat');
            $dbPath = PATH_CACHE . DIRECTORY_SEPARATOR . 'tlds.txt';

            if ($response->getStatusCode() === 200) {
                @file_put_contents($dbPath, $response->getContent());
            } else {
                $item->expiresAfter(3600);

                return [];
            }

            @$database = file($dbPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            @unlink($dbPath);
            if (!$database) {
                $item->expiresAfter(3600);

                return [];
            }

            $validTlds = array_filter($database, fn ($tld): bool => !str_starts_with($tld, '/'));

            $result = [];
            foreach ($validTlds as $tld) {
                if (str_contains($tld, 'END ICANN DOMAINS')) {
                    break;
                }
                $tld = idn_to_ascii($tld);
                if ($tld !== false) {
                    $result[$tld] = true;
                }
            }

            // Sanity check we've created the list correctly
            if (!($result['com'] ?? false) || !($result['net'] ?? false) || !($result['org'] ?? false)) {
                $item->expiresAfter(3600);

                return [];
            }

            return $result;
        });

        if (!$validTlds) {
            // Fallback behavior if we fail to get a valid list
            if (str_starts_with($tld, 'xn--') || preg_match('/^[a-z]+$/', $tld)) {
                return true;
            } else {
                return false;
            }
        } else {
            return $validTlds[$tld] ?? false;
        }
    }

    public function isPasswordStrong($pwd): bool
    {
        if (strlen($pwd) < 8) {
            throw new InformationException('Minimum password length is 8 characters.');
        }

        if (strlen($pwd) > 256) {
            throw new InformationException('Maximum password length is 256 characters.');
        }

        if (!preg_match('#[0-9]+#', $pwd)) {
            throw new InformationException('Password must include at least one number.');
        }

        if (!preg_match('#[a-z]+#', $pwd)) {
            throw new InformationException('Password must include at least one lowercase letter.');
        }

        if (!preg_match('#[A-Z]+#', $pwd)) {
            throw new InformationException('Password must include at least one uppercase letter.');
        }

        /*
        if( !preg_match("#\W+#", $pwd) ) {
            $msg = "Password must include at least one symbol!";
        }
        */
        return true;
    }

    /**
     * @param array $required  - Array with required keys and messages to show if the key is not found
     * @param array $data      - Array to search for keys
     * @param array $variables - Array of variables for message placeholders (:placeholder)
     * @param int   $code      - Exception code
     *
     * @throws InformationException
     */
    public function checkRequiredParamsForArray(array $required, array $data, array $variables = null, $code = 0)
    {
        foreach ($required as $key => $msg) {
            if (!isset($data[$key])) {
                throw new InformationException($msg, $variables, $code);
            }

            if (is_string($data[$key]) && strlen(trim($data[$key])) === 0) {
                throw new InformationException($msg, $variables, $code);
            }

            if (!is_numeric($data[$key]) && empty($data[$key])) {
                throw new InformationException($msg, $variables, $code);
            }
        }
    }

    public function isBirthdayValid($birthday = '')
    {
        if (strlen(trim($birthday)) > 0 && strtotime($birthday) === false) {
            $friendlyName = ucfirst(__trans('Birthdate'));

            throw new Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
        }

        return true;
    }
}

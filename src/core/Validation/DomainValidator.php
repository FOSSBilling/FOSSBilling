<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Validation;

use FOSSBilling\Core\Container\InjectionAwareInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class DomainValidator implements InjectionAwareInterface
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

    public function isSldValid(string $sld): bool
    {
        $sld = ltrim($sld, '.');
        if ($sld === '') {
            return false;
        }
        $sld = idn_to_ascii($sld);
        if ($sld === false) {
            return false;
        }
        $sld = strtolower($sld);

        // allow punnycode, subject to the same single-label and length limits
        if (str_starts_with($sld, 'xn--')) {
            return !str_contains($sld, '.') && strlen($sld) < 64;
        }

        return preg_match('/^[a-z0-9]+[a-z0-9\-]*[a-z0-9]+$/i', $sld) === 1
            && strlen($sld) < 64
            && substr($sld, 2, 2) !== '--';
    }

    public function isTldValid(string $tld): bool
    {
        $tld = ltrim($tld, '.');
        if ($tld === '') {
            return false;
        }
        $tld = idn_to_ascii($tld);
        if ($tld === false) {
            return false;
        }
        $tld = strtolower($tld);

        $validTlds = $this->di['cache']->get('validTlds', function (ItemInterface $item): array {
            $item->expiresAfter(86400);

            $httpClient = $this->di['http_client'];

            try {
                $response = $httpClient->request('GET', 'https://publicsuffix.org/list/public_suffix_list.dat');
                $content = $response->getStatusCode() === 200 ? $response->getContent() : null;
            } catch (ExceptionInterface) {
                // Network/transport failure (DNS, TLS, connection refused, unsupported address family, etc.)
                // Fall back below instead of letting this bubble up and break the calling flow (e.g. checkout).
                $content = null;
            }

            if ($content === null) {
                $item->expiresAfter(3600);

                return [];
            }

            $database = preg_split('/\R/', $content, -1, PREG_SPLIT_NO_EMPTY);

            if (!$database) {
                $item->expiresAfter(3600);

                return [];
            }

            $result = [];
            foreach ($database as $tld) {
                $tld = trim($tld);
                if (str_contains($tld, 'END ICANN DOMAINS')) {
                    break;
                }
                if ($tld === '' || str_starts_with($tld, '//')) {
                    continue;
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
            return str_starts_with($tld, 'xn--') || preg_match('/^[a-z]+$/', $tld) === 1;
        }

        return $validTlds[$tld] ?? false;
    }
}

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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class DomainValidator implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
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

        if (str_starts_with($sld, 'xn--')) {
            return true;
        }

        if (preg_match('/^[a-z0-9]+[a-z0-9\-]*[a-z0-9]+$/i', $sld) && strlen($sld) < 64 && substr($sld, 2, 2) != '--') {
            return true;
        }

        return false;
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
            $dbPath = Path::join(PATH_CACHE, 'tlds.txt');

            try {
                $response = $httpClient->request('GET', 'https://publicsuffix.org/list/public_suffix_list.dat');
                $content = $response->getStatusCode() === 200 ? $response->getContent() : null;
            } catch (ExceptionInterface) {
                // Network/transport failure (DNS, TLS, connection refused, unsupported address family, etc.)
                // Fall back below instead of letting this bubble up and break the calling flow (e.g. checkout).
                $content = null;
            }

            if ($content !== null) {
                $this->filesystem->dumpFile($dbPath, $content);
            } else {
                $item->expiresAfter(3600);

                return [];
            }

            @$database = file($dbPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $this->filesystem->remove($dbPath);
            if (!$database) {
                $item->expiresAfter(3600);

                return [];
            }

            $validTlds = array_filter($database, fn (string $tld): bool => !str_starts_with($tld, '/'));

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

            if (!($result['com'] ?? false) || !($result['net'] ?? false) || !($result['org'] ?? false)) {
                $item->expiresAfter(3600);

                return [];
            }

            return $result;
        });

        if (!$validTlds) {
            if (str_starts_with($tld, 'xn--') || preg_match('/^[a-z]+$/', $tld)) {
                return true;
            }

            return false;
        }

        return $validTlds[$tld] ?? false;
    }
}

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

class Requirements
{
    private bool $isOk = true;

    public array $php_reqs = [
        'required_extensions' => [
            'intl',
            'openssl',
            'pdo_mysql',
            'xml',
            'dom',
            'iconv',
            'json',
            'zlib'
        ],
        'suggested_extensions' => [
            'curl',
            'mbstring',
            'opcache',
            'imagick',
            'gd',
            'bz2',
            'simplexml',
            'xml'
        ],
        'min_version' => '8.1',
    ];

    public array $writable = [
        'folders' => [
            PATH_ROOT . '/data/cache',
            PATH_ROOT . '/data/log',
            PATH_ROOT . '/data/uploads',
        ],
        'files' => [
            PATH_ROOT . '/config.php',
        ],
    ];

    public function isPhpVersionOk(): bool
    {
        $required = $this->php_reqs['min_version'];
        $ok = version_compare(PHP_VERSION, $required, '>=');
        if (!$ok) {
            $this->isOk = false;
        }
        return $ok;
    }

    public function checkFile(string $path): bool
    {
        $writable = false;
        if (is_writable($path)) {
            $writable = true;
        } else if (!file_exists($path)) {
            $written = @file_put_contents($path, 'Test?');
            if ($written) {
                $writable = true;
            } else {
                $this->isOk = false;
            }
            @unlink($path);
        } else {
            $this->isOk = false;
        }
        return $writable;
    }

    public function checkFolder(string $path): bool
    {
        $writable = false;
        if (is_writable($path)) {
            $writable = true;
        } else {
            $this->isOk = false;
        }
        return $writable;
    }

    public function checkCompat(): array
    {
        $result = [];

        foreach ($this->writable['folders'] as $path) {
            $result['folders'][$path] = $this->checkFolder($path);
        }

        foreach ($this->writable['files'] as $path) {
            $result['files'][$path] = $this->checkFile($path);
        }

        foreach ($this->php_reqs['required_extensions'] as $ext) {
            $loaded = extension_loaded($ext);
            $result['required_extensions'][$ext] = $loaded;
            if (!$loaded) {
                $this->isOk = false;
            }
        }

        foreach ($this->php_reqs['suggested_extensions'] as $ext) {
            if ($ext === 'opcache') {
                if (!function_exists('opcache_get_status')) {
                    $result['suggested_extensions'][$ext] = false;
                }
                $status = opcache_get_status();
                $result['suggested_extensions'][$ext] = is_array($status) && $status['opcache_enabled'];
            } else {
                $result['suggested_extensions'][$ext] = extension_loaded($ext);
            }
        }

        $result['php_version'] = [
            'isOk' => $this->isPhpVersionOk(),
            'version' => PHP_VERSION,
            'min_version' => $this->php_reqs['min_version'],
        ];

        $result['can_install'] = $this->isOk;
        return $result;
    }
}

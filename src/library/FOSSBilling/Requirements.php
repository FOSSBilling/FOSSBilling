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

class Requirements
{
    private bool $_all_ok = true;
    private array $_options = array();
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->_options = [
            'php'   =>  [
                'extensions' => array(
                    'pdo_mysql',
                    'zlib',
                    'openssl',
                    'dom',
                    'xml',
                 ),
                'version'       =>  PHP_VERSION,
                'min_version'   =>  '8.1',
                'safe_mode'     =>  ini_get('safe_mode'),
            ),
            'writable_folders' => [
                PATH_CACHE,
                PATH_LOG,
                PATH_UPLOADS
            ],
            'writable_files' => array(
                PATH_CONFIG,
            ),
        );

        $this->filesystem = new Filesystem();
    }

    public function getOptions(): array
    {
        return $this->_options;
    }

    public function getInfo(): array
    {
        $config = include PATH_CONFIG;

        $data = array();
        $data['ip']             = $_SERVER['SERVER_ADDR'] ?? null;
        $data['PHP_OS']         = PHP_OS;
        $data['PHP_VERSION']    = PHP_VERSION;

        $data['FOSSBilling']    = array(
            'locale'        =>  $config['i18n']['locale'],
            'version'       =>  \FOSSBilling\Version::VERSION,
        );

        $data['ini']    = array(
            'allow_url_fopen'   =>  ini_get('allow_url_fopen'),
            'safe_mode'         =>  ini_get('safe_mode'),
            'memory_limit'      =>  ini_get('memory_limit'),
        );

        $data['permissions']    = array(
            PATH_UPLOADS     =>  substr(sprintf('%o', fileperms(PATH_UPLOADS)), -4),
            PATH_DATA        =>  substr(sprintf('%o', fileperms(PATH_DATA)), -4),
            PATH_CACHE       =>  substr(sprintf('%o', fileperms(PATH_CACHE)), -4),
            PATH_LOG         =>  substr(sprintf('%o', fileperms(PATH_LOG)), -4),
        );

        $data['extensions']    = array(
            'apc'           => extension_loaded('apc'),
            'pdo_mysql'     => extension_loaded('pdo_mysql'),
            'zlib'          => extension_loaded('zlib'),
            'mbstring'      => extension_loaded('mbstring'),
            'openssl'        => extension_loaded('openssl'),
        );

        //determine php username
        if(function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $data['posix_getpwuid'] = posix_getpwuid(posix_geteuid());
        }
        return $data;
    }

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
        } elseif (!file_exists($path)) {
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

        foreach ($this->php_reqs['suggested_extensions'] as $ext => $message) {
            if ($ext === 'opcache') {
                if (!function_exists('opcache_get_status')) {
                    $result['suggested_extensions'][$ext] = [
                        'loaded' => false,
                        'message' => $message,
                    ];

    /**
     * Files that must be writable
     */
    public function files(): array
    {
        $files = $this->_options['writable_files'];
        $result = array();

        foreach($files as $file) {
            if ($this->checkPerms($file)) {
                $result[$file] = true;
            } else if (is_writable($file)) {
            	$result[$file] = true;
            } else if (!$this->filesystem->exists($file)){
                $written = @file_put_contents($file, 'Test?');
                if($written){
                    $result[$file] = true;
                } else {
                    $result[$file] = false;
                    $this->_all_ok = false;
                }
                $status = opcache_get_status();
                $result['suggested_extensions'][$ext] = [
                    'loaded' => is_array($status) && $status['opcache_enabled'],
                    'message' => $message,
                ];
            } else {
                $result['suggested_extensions'][$ext] = [
                    'loaded' => extension_loaded($ext),
                    'message' => $message,
                ];
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

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

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Tools
{
    private readonly Filesystem $filesystem;
    protected ?\Pimple\Container $di = null;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function url($link = null): string
    {
        return SYSTEM_URL . trim((string) $link, '/');
    }

    public function generatePassword(int $length = 8, bool|int $includeSpecial = false): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialCharacters = '!@#$%&?()+-_';

        if (is_int($includeSpecial)) {
            $includeSpecial = $includeSpecial === 4;
        }

        $charSet = $characters . strtoupper($characters) . $numbers;
        if ($includeSpecial) {
            $charSet .= $specialCharacters;
        }

        $charSetLength = strlen($charSet);

        $minRequiredLength = $includeSpecial ? 4 : 3;
        if ($length < $minRequiredLength) {
            throw new InformationException('Password length must be at least ' . $minRequiredLength . ' characters to meet complexity requirements');
        }

        $password = '';
        $password .= $characters[random_int(0, strlen($characters) - 1)];
        $password .= strtoupper($characters)[random_int(0, strlen($characters) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        if ($includeSpecial) {
            $password .= $specialCharacters[random_int(0, strlen($specialCharacters) - 1)];
        }

        for ($i = strlen($password); $i < $length; ++$i) {
            $password .= $charSet[random_int(0, $charSetLength - 1)];
        }

        return str_shuffle($password);
    }

    public function slug($str): string
    {
        $str = strtolower(trim((string) $str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', '-', (string) $str);

        return trim((string) $str, '-');
    }

    public function to_camel_case($str, $capitalize_first_char = false): ?string
    {
        if ($capitalize_first_char) {
            $str[0] = strtoupper((string) $str[0]);
        }
        $func = fn ($c): string => strtoupper((string) $c[1]);

        return preg_replace_callback('/-([a-z])/', $func, (string) $str);
    }

    public function from_camel_case($str): ?string
    {
        $str[0] = strtolower((string) $str[0]);
        $func = fn ($c): string => '-' . strtolower((string) $c[1]);

        return preg_replace_callback('/([A-Z])/', $func, (string) $str);
    }

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
        $class = 'Model_' . ucfirst((string) $type) . 'Table';
        $file = Path::join(PATH_LIBRARY, 'Model', "{$type}Table.php");
        if (!$this->filesystem->exists($file)) {
            throw new Exception('Service class :class was not found in :path', [':class' => $class, ':path' => $file]);
        }
        require_once $file;

        return new $class();
    }

    public function getPairsForTableByIds($table, $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $count = self::safeCount($ids);
        $slots = $count ? implode(',', array_fill(0, $count, '?')) : '';

        $rows = $this->di['db']->getAll('SELECT id, title FROM ' . $table . ' WHERE id in (' . $slots . ')', $ids);

        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

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
            }

            return false;
        }

        return $email;
    }

    public static function safeCount(mixed $value): int
    {
        return is_countable($value) ? count($value) : 0;
    }

    public static function normalizeBoolean(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $normalized ?? $default;
        }

        return $default;
    }

    public static function normalizePort(mixed $value, ?int $default = null): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return $default;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $port = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);

        return $port === false ? $default : $port;
    }

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

    // --- Session tokens ---

    public static function createSessionRestoreToken(string $sessionId): string
    {
        $expiry = time() + 3600;
        $payload = $sessionId . '|' . $expiry;
        $signature = hash_hmac('sha256', $payload, (string) Config::getProperty('info.salt'));

        return base64_encode($payload . '|' . $signature);
    }

    public static function validateSessionRestoreToken(string $token): ?string
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        [$sessionId, $expiry, $signature] = $parts;

        if (time() > (int) $expiry) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $sessionId . '|' . $expiry, (string) Config::getProperty('info.salt'));
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        return $sessionId;
    }
}

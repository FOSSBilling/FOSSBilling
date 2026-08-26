<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

use FOSSBilling\Exception\InformationException;
use FOSSBilling\System\Config;

final class Credential
{
    public static function generatePassword(int $length = 8, bool|int $includeSpecial = false): string
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

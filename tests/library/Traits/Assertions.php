<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Traits;

trait DomainAssertions
{
    public function assertValidDomain(string $domain, string $message = ''): void
    {
        $this->assertNotEmpty($domain, $message ?: 'Domain should not be empty');
        $this->assertIsString($domain, 'Domain should be a string');
    }

    public function assertValidEmail(string $email, string $message = ''): void
    {
        $this->assertNotEmpty($email, $message ?: 'Email should not be empty');
        $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, $message ?: 'Invalid email format');
    }

    public function assertValidUrl(string $url, string $message = ''): void
    {
        $this->assertNotEmpty($url, $message ?: 'URL should not be empty');
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false, $message ?: 'Invalid URL format');
    }

    public function assertValidIp(string $ip, string $message = ''): void
    {
        $this->assertNotEmpty($ip, $message ?: 'IP address should not be empty');
        $this->assertTrue(filter_var($ip, FILTER_VALIDATE_IP) !== false, $message ?: 'Invalid IP address format');
    }
}

trait ArrayAssertions
{
    public function assertArrayHasIntKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertArrayHasKey($key, $array, $message);
        $this->assertIsInt($array[$key], $message ?: "Value for key '{$key}' should be an integer");
    }

    public function assertArrayHasStringKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertArrayHasKey($key, $array, $message);
        $this->assertIsString($array[$key], $message ?: "Value for key '{$key}' should be a string");
    }

    public function assertArrayHasBoolKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertArrayHasKey($key, $array, $message);
        $this->assertIsBool($array[$key], $message ?: "Value for key '{$key}' should be a boolean");
    }

    public function assertArrayNotHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertArrayNotHasKey($key, $array, $message ?: "Array should not have key '{$key}'");
    }

    public function assertArrayIsAssociative(array $array, string $message = ''): void
    {
        $this->assertNotEmpty($array, $message ?: 'Array should not be empty');
        $keys = array_keys($array);
        $this->assertTrue(
            array_keys($keys) !== $keys,
            $message ?: 'Array should be associative'
        );
    }

    public function assertArrayIsSequential(array $array, string $message = ''): void
    {
        $this->assertNotEmpty($array, $message ?: 'Array should not be empty');
        $keys = array_keys($array);
        $this->assertTrue(
            array_keys($keys) === $keys,
            $message ?: 'Array should be sequential'
        );
    }
}

trait NumericAssertions
{
    public function assertPositiveNumber(int|float $number, string $message = ''): void
    {
        $this->assertGreaterThan(0, $number, $message ?: 'Number should be positive');
    }

    public function assertNonNegativeNumber(int|float $number, string $message = ''): void
    {
        $this->assertGreaterThanOrEqual(0, $number, $message ?: 'Number should be non-negative');
    }

    public function assertNegativeNumber(int|float $number, string $message = ''): void
    {
        $this->assertLessThan(0, $number, $message ?: 'Number should be negative');
    }

    public function assertValidPrice(int|float $price, string $message = ''): void
    {
        $this->assertIsNumeric($price, $message ?: 'Price should be numeric');
        $this->assertGreaterThanOrEqual(0, $price, $message ?: 'Price should not be negative');
        $this->assertLessThanOrEqual(9999999, $price, $message ?: 'Price should be within reasonable bounds');
    }

    public function assertValidPercentage(int|float $percentage, string $message = ''): void
    {
        $this->assertIsNumeric($percentage, $message ?: 'Percentage should be numeric');
        $this->assertGreaterThanOrEqual(0, $percentage, $message ?: 'Percentage should not be negative');
        $this->assertLessThanOrEqual(100, $percentage, $message ?: 'Percentage should not exceed 100');
    }
}

trait StringAssertions
{
    public function assertNotEmptyString(string $string, string $message = ''): void
    {
        $this->assertNotEmpty($string, $message ?: 'String should not be empty');
        $this->assertIsString($string, 'Value should be a string');
    }

    public function assertValidSlug(string $slug, string $message = ''): void
    {
        $this->assertNotEmptyString($slug, $message);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug, $message ?: 'Slug should only contain lowercase letters, numbers, and hyphens');
    }

    public function assertValidUuid(string $uuid, string $message = ''): void
    {
        $this->assertNotEmptyString($uuid, $message);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid, $message ?: 'Invalid UUID format');
    }
}

trait DateTimeAssertions
{
    public function assertValidDateTime(string $datetime, string $message = ''): void
    {
        $this->assertNotEmpty($datetime, $message ?: 'DateTime should not be empty');
        $parsed = date_parse($datetime);
        $this->assertTrue($parsed['error_count'] === 0, $message ?: 'Invalid date/time format: ' . ($parsed['errors'][0] ?? 'Unknown error'));
    }

    public function assertValidDate(string $date, string $message = ''): void
    {
        $this->assertNotEmpty($date, $message ?: 'Date should not be empty');
        $parsed = date_parse($date);
        $this->assertTrue($parsed['error_count'] === 0 && $parsed['hour'] === false, $message ?: 'Invalid date format');
    }

    public function assertValidTime(string $time, string $message = ''): void
    {
        $this->assertNotEmpty($time, $message ?: 'Time should not be empty');
        $parsed = date_parse($time);
        $this->assertTrue($parsed['error_count'] === 0 && $parsed['year'] === false, $message ?: 'Invalid time format');
    }

    public function assertDateTimeInRange(string $datetime, string $start, string $end, string $message = ''): void
    {
        $this->assertTrue($datetime >= $start && $datetime <= $end, $message ?: 'DateTime should be within the specified range');
    }
}

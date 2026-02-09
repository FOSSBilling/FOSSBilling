<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Datasets;

/**
 * Email validation test data.
 *
 * @return array<int, array{0: string, 1: bool}>
 */
function emailProvider(): array
{
    return [
        // Valid emails
        ['test@example.com', true],
        ['test.user@example.com', true],
        ['test+tag@example.com', true],
        ['test@subdomain.example.com', true],
        ['test@example.co.uk', true],
        ['user@domain.org', true],
        ['user123@domain.com', true],
        ['first.last@domain.io', true],
        // Invalid emails
        ['invalid', false],
        ['test@', false],
        ['@example.com', false],
        ['test example.com', false],
        ['test@@example.com', false],
        ['test@.com', false],
        ['', false],
    ];
}

/**
 * Second-level domain validation test data.
 *
 * @return array<int, array{0: string, 1: bool}>
 */
function domainProvider(): array
{
    return [
        // Valid SLDs
        ['google', true],
        ['example-domain', true],
        ['a1', true],
        ['123', true],
        ['xn--bcher-kva', true],  // Internationalized domain
        ['subdomain', true],
        ['my-domain', true],
        // Invalid SLDs
        ['qqq45%%%', false],
        ['()1google', false],
        ['//asdasd()()', false],
        ['--asdasd()()', false],
        ['', false],
        ['sub.domain.example', false], // SLD cannot contain dots
        ['-invalid', false],
        ['invalid-', false],
    ];
}

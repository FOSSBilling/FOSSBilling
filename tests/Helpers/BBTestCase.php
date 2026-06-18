<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * Compatibility base test case for ported PHPUnit-style module tests.
 *
 * Provides the getDi() helper that the legacy test classes rely on, plus
 * a bare DummyBean alias so older assertions that reference a global
 * DummyBean continue to resolve.
 */
abstract class BBTestCase extends TestCase
{
    protected function getDi(): Container
    {
        return container();
    }
}

if (!class_exists('DummyBean')) {
    class_alias(DummyBean::class, 'DummyBean');
}

if (!class_exists('BBTestCase')) {
    class_alias(BBTestCase::class, 'BBTestCase');
}

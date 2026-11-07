<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . '/TranslationFixture.php';

class TranslationTestCase extends PHPUnit\Framework\TestCase
{
    public TranslationFixture $fixture;
}

pest()->extend(TranslationTestCase::class)->in(__DIR__)
    ->beforeEach(function (): void { $this->fixture = new TranslationFixture(); })
    ->afterEach(function (): void { $this->fixture->remove(); });

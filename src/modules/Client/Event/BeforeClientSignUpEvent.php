<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Client\Event;

use FOSSBilling\Events\Event;

/** Signup input excluding the password fields, with the request IP included. */
final class BeforeClientSignUpEvent extends Event
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly array $input,
    ) {
    }
}

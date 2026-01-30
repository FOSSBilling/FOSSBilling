<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired before a client signs up.
 */
final class BeforeClientSignUpEvent extends Event
{
    public function __construct(
        public readonly string $email,
        public readonly string $ip,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $password = null,
    ) {
        parent::__construct();
    }
}

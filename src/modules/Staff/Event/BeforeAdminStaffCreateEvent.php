<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Staff\Event;

use FOSSBilling\Events\Event;

/** Staff account creation input, excluding credentials and unknown request fields. */
final class BeforeAdminStaffCreateEvent extends Event
{
    /** @param array{email?: mixed, name?: mixed, status?: mixed, signature?: mixed, timezone?: mixed, group_id?: mixed} $input */
    public function __construct(
        public readonly array $input,
    ) {
    }
}

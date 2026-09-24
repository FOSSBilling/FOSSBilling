<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support\Event;

enum TicketActorRole: string
{
    case ADMIN = 'admin';
    case CLIENT = 'client';
    case GUEST = 'guest';
}

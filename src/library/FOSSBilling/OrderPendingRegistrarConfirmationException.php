<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

/**
 * Thrown by a service module's action_activate() to signal that the order was accepted
 * for provisioning but isn't confirmed yet (e.g. a domain register/transfer accepted by
 * the registrar but still processing). Order/Service::createFromOrder() catches this
 * separately from a hard failure and parks the order in STATUS_PENDING_REGISTRAR instead
 * of STATUS_ACTIVE or STATUS_FAILED_SETUP.
 */
class OrderPendingRegistrarConfirmationException extends Exception
{
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Thrown when a registrar adapter confirms a domain is not present in the account
 * anymore, as distinct from a transient API/auth failure. Callers should treat this
 * as a real, permanent signal (e.g. the domain was transferred away), not something
 * to retry.
 */
class Registrar_Exception_DomainNotFound extends Registrar_Exception
{
}

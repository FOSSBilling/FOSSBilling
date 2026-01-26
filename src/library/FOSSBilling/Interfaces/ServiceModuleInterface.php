<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Interfaces;

/**
 * Interface for service modules that handle order lifecycle actions.
 *
 * Service modules (Servicehosting, Servicedomain, etc.) implement this interface
 * to handle the creation, activation, renewal, suspension, and deletion of services
 * associated with client orders.
 */
interface ServiceModuleInterface
{
    /**
     * Create a service record in the database when an order is placed.
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return \RedBeanPHP\SimpleModel the created service model
     */
    public function action_create(\Model_ClientOrder $order): \RedBeanPHP\SimpleModel;

    /**
     * Activate the service (e.g., provision hosting, register domain, generate license).
     *
     * The return type is `mixed` because service modules may optionally return
     * an array of data to be passed to event listeners via the `onAfterAdminOrderActivate`
     * event. For example, Servicehosting returns `['username' => ..., 'password' => ...]`
     * to allow event listeners to access the newly created credentials.
     *
     * If no data needs to be passed to events, simply return `true`.
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return mixed true on success, or an array of data to pass to event listeners
     */
    public function action_activate(\Model_ClientOrder $order): mixed;

    /**
     * Renew the service for another period.
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return bool true on success
     */
    public function action_renew(\Model_ClientOrder $order): bool;

    /**
     * Suspend the service (temporary deactivation).
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return bool true on success
     */
    public function action_suspend(\Model_ClientOrder $order): bool;

    /**
     * Unsuspend a previously suspended service.
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return bool true on success
     */
    public function action_unsuspend(\Model_ClientOrder $order): bool;

    /**
     * Cancel the service (permanent deactivation).
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return bool true on success
     */
    public function action_cancel(\Model_ClientOrder $order): bool;

    /**
     * Uncancel a previously cancelled service.
     *
     * @param \Model_ClientOrder $order the client order
     *
     * @return bool true on success
     */
    public function action_uncancel(\Model_ClientOrder $order): bool;

    /**
     * Delete the service and clean up associated resources.
     *
     * @param \Model_ClientOrder $order the client order
     */
    public function action_delete(\Model_ClientOrder $order): void;
}

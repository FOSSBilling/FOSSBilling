<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Redirect\Api;

use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Redirects management.
 */
class Admin extends \Api_Abstract
{
    /**
     * Get list of redirects.
     *
     * @return array - list
     */
    public function get_list()
    {
        return $this->getService()->getRedirects();
    }

    /**
     * Get redirect by id.
     */
    #[RequiredParams(['id' => 'Redirect ID was not passed'])]
    public function get($data): array
    {
        return $this->getService()->toApiArray($this->getService()->get((int) $data['id']));
    }

    /**
     * Create new redirect.
     *
     * @return int redirect id
     */
    #[RequiredParams(['path' => 'Redirect path was not passed', 'target' => 'Redirect target was not passed'])]
    public function create($data): int
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('redirect', 'create_and_edit');

        $id = $this->getService()->create(
            (string) $data['path'],
            (string) $data['target']
        );

        $this->di['logger']->info('Created new redirect #%s', $id);

        return $id;
    }

    /**
     * Update redirect.
     *
     * @optional string $path - redirect path
     * @optional string $target - redirect target
     *
     * @return true
     */
    #[RequiredParams(['id' => 'Redirect ID was not passed'])]
    public function update($data): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('redirect', 'create_and_edit');

        $this->getService()->update($this->getService()->get((int) $data['id']), $data);

        $this->di['logger']->info('Updated redirect #%s', $data['id']);

        return true;
    }

    /**
     * Delete redirect.
     *
     * @return true
     */
    #[RequiredParams(['id' => 'Redirect ID was not passed'])]
    public function delete($data): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('redirect', 'delete');

        $this->getService()->delete($this->getService()->get((int) $data['id']));

        $this->di['logger']->info('Removed redirect #%s', $data['id']);

        return true;
    }
}

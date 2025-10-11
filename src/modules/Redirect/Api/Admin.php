<?php

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
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Redirect ID was not passed'])]
    public function get($data)
    {
        $bean = $this->_getRedirect($data['id']);

        return [
            'id' => $bean->id,
            'path' => $bean->meta_key,
            'target' => $bean->meta_value,
        ];
    }

    /**
     * Create new redirect.
     *
     * @return int redirect id
     */
    #[RequiredParams(['path' => 'Redirect path was not passed', 'target' => 'Redirect target was not passed'])]
    public function create($data)
    {
        $bean = $this->di['db']->dispense('extension_meta');
        $bean->extension = 'mod_redirect';
        $bean->meta_key = trim(htmlspecialchars($data['path'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/');
        $bean->meta_value = trim(htmlspecialchars($data['target'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/');
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($bean);

        $id = $bean->id;

        $this->di['logger']->info('Created new redirect #%s', $id);

        return (int) $id;
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
    public function update($data)
    {
        $bean = $this->_getRedirect($data['id']);

        $bean->meta_key = trim(htmlspecialchars($data['path'] ?? $bean->meta_key, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/');
        $bean->meta_value = trim(htmlspecialchars($data['target'] ?? $bean->meta_value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/');
        $bean->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($bean);

        $this->di['logger']->info('Updated redirect #%s', $data['id']);

        return true;
    }

    /**
     * Delete redirect.
     *
     * @return true
     */
    #[RequiredParams(['id' => 'Redirect ID was not passed'])]
    public function delete($data)
    {
        $bean = $this->_getRedirect($data['id']);
        $this->di['db']->trash($bean);

        $this->di['logger']->info('Removed redirect #%s', $data['id']);

        return true;
    }

    private function _getRedirect($id)
    {
        $sql = " extension = 'mod_redirect' AND id = :id";
        $values = ['id' => $id];
        $bean = $this->di['db']->findOne('extension_meta', $sql, $values);

        if (!$bean) {
            throw new \FOSSBilling\Exception('Redirect not found');
        }

        return $bean;
    }
}

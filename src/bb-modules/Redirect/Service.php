<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Redirect;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getRedirects()
    {
        $sql = '
            SELECT id, meta_key as path, meta_value as target
            FROM extension_meta
            WHERE extension = "mod_redirect"
            ORDER BY id ASC
        ';

        return $this->di['db']->getAll($sql);
    }

    public function getRedirectByPath($path)
    {
        $sql = '
            SELECT meta_value
            FROM extension_meta
            WHERE extension = "mod_redirect"
            AND meta_key = :path
            LIMIT 1
        ';

        return $this->di['db']->getCell($sql, ['path' => $path]);
    }
}
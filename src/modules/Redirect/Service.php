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

namespace FOSSBilling\Module\Redirect;

use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;

class Service implements InjectionAwareInterface
{
    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
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

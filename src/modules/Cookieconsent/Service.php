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

namespace FOSSBilling\Module\Cookieconsent;

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

    public function getMessage(): string
    {
        $config = $this->di['mod_config']('cookieconsent');

        return $config['message'] ?? 'This website uses cookies. By continuing to use this website, you consent to our use of these cookies.';
    }
}

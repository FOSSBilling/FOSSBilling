<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency\Api;

use Box\Mod\Currency\Entity\Currency;

class Guest extends \Api_Abstract
{
    /**
     * Get a list of available currencies.
     */
    public function get_pairs(): array
    {
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        return $repo->getPairs();
    }

    /**
     * Get a currency by code.
     */
    public function get(array $data): array
    {
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        if (isset($data['code']) && !empty($data['code'])) {
            $model = $repo->findOneByCode($data['code']);
        } else {
            $model = $repo->findDefault();
        }

        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found.');
        }

        return $model->toApiArray();
    }
}

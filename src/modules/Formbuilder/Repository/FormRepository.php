<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Formbuilder\Repository;

use Box\Mod\Formbuilder\Entity\Form;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\InformationException;

class FormRepository extends EntityRepository
{
    public function findOneByIdOrFail(int $id): Form
    {
        $form = $this->find($id);
        if (!$form instanceof Form) {
            throw new InformationException('Form was not found');
        }

        return $form;
    }
}

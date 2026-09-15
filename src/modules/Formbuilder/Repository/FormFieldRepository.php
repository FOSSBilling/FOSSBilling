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
use Box\Mod\Formbuilder\Entity\FormField;
use Doctrine\ORM\EntityRepository;

class FormFieldRepository extends EntityRepository
{
    /**
     * @return list<FormField>
     */
    public function findByForm(Form $form): array
    {
        return $this->findBy(['form' => $form], ['id' => 'ASC']);
    }
}

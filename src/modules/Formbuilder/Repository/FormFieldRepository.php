<?php

declare(strict_types=1);

namespace Box\Mod\Formbuilder\Repository;

use Box\Mod\Formbuilder\Entity\FormField;
use Doctrine\ORM\EntityRepository;

class FormFieldRepository extends EntityRepository
{
    /**
     * @return FormField[]
     */
    public function findByFormId(int $formId): array
    {
        return $this->findBy(['formId' => $formId], ['id' => 'ASC']);
    }
}

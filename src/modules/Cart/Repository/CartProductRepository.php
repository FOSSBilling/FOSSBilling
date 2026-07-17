<?php

declare(strict_types=1);

namespace Box\Mod\Cart\Repository;

use Box\Mod\Cart\Entity\CartProduct;
use Doctrine\ORM\EntityRepository;

class CartProductRepository extends EntityRepository
{
    /**
     * @return CartProduct[]
     */
    public function findByCartId(int $cartId): array
    {
        return $this->findBy(['cartId' => $cartId], ['id' => 'ASC']);
    }

    public function findOneByCartAndId(int $cartId, int $id): ?CartProduct
    {
        $cartProduct = $this->findOneBy(['cartId' => $cartId, 'id' => $id]);

        return $cartProduct instanceof CartProduct ? $cartProduct : null;
    }

    public function deleteByCartId(int $cartId): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM cart_product WHERE cart_id = :cart_id',
            ['cart_id' => $cartId]
        );
    }
}

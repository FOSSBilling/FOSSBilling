<?php

declare(strict_types=1);

namespace Box\Mod\Cart\Repository;

use Box\Mod\Cart\Entity\Cart;
use Doctrine\ORM\EntityRepository;

class CartRepository extends EntityRepository
{
    public function findBySessionId(string $sessionId): ?Cart
    {
        $cart = $this->findOneBy(['sessionId' => $sessionId]);

        return $cart instanceof Cart ? $cart : null;
    }

    public function getSearchQueryBuilder(array $data): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('cart')
            ->leftJoin('Box\Mod\Currency\Entity\Currency', 'currency', 'WITH', 'currency.id = cart.currencyId')
            ->leftJoin('Box\Mod\Product\Entity\Promo', 'promo', 'WITH', 'promo.id = cart.promoId');
    }
}

<?php

declare(strict_types=1);

namespace Box\Mod\Cart\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Cart\Repository\CartProductRepository::class)]
#[ORM\Table(name: 'cart_product')]
#[ORM\Index(name: 'cart_id_idx', columns: ['cart_id'])]
#[ORM\Index(name: 'product_id_idx', columns: ['product_id'])]
class CartProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'cart_id', type: Types::BIGINT, nullable: true)]
    private ?int $cartId = null;

    #[ORM\Column(name: 'product_id', type: Types::BIGINT, nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $config = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCartId(): ?int
    {
        return $this->cartId;
    }

    public function setCartId(?int $cartId): void
    {
        $this->cartId = $cartId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): void
    {
        $this->productId = $productId;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
    }
}

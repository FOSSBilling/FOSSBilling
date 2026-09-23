<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Cart\Entity\CartProduct;
use Box\Mod\Cart\Event\AfterProductAddedToCartEvent;
use Box\Mod\Cart\Event\BeforeProductAddedToCartEvent;
use Box\Mod\Cart\Repository\CartProductRepository;
use Box\Mod\Cart\Repository\CartRepository;
use Box\Mod\Cart\Service;
use Box\Mod\Client\Entity\Client;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\PromoRedemption;
use Box\Mod\Product\Service as ProductService;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function createProductEntity(?int $id = null, ?string $type = null, ?string $config = null): Product
{
    $product = new Product();
    if ($id !== null) {
        $reflection = new ReflectionProperty($product, 'id');
        $reflection->setValue($product, $id);
    }
    if ($type !== null) {
        $product->setType($type);
    }
    if ($config !== null) {
        $product->setConfig($config);
    }

    return $product;
}

function createPromoEntity(int $id): Promo
{
    $promo = new Promo();
    $reflection = new ReflectionProperty($promo, 'id');
    $reflection->setValue($promo, $id);

    return $promo;
}

/** @return array{SymfonyEventDispatcher, ArrayObject} */
function cartAddItemEventDispatcher(): array
{
    $events = new ArrayObject();
    $dispatcher = new SymfonyEventDispatcher();

    foreach ([BeforeProductAddedToCartEvent::class, AfterProductAddedToCartEvent::class] as $eventClass) {
        $dispatcher->addListener($eventClass, static function (object $event) use ($events): void {
            $events->append($event);
        });
    }

    return [$dispatcher, $events];
}

test('gets dependency injection container', function (): void {
    $service = new Service();

    $di = container();
    $service->setDi($di);
    $result = $service->getDi();
    expect($result)->toEqual($di);
});

test('gets search query', function (): void {
    $service = new Service();
    $result = $service->getSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect(strpos((string) $result[0], 'SELECT cart.id FROM cart'))->not->toBeFalse();
});

test('gets search query applies allowlisted sort', function (): void {
    $service = new Service();

    [$query] = $service->getSearchQuery(['sort' => 'id', 'direction' => 'desc']);
    expect($query)->toContain('ORDER BY cart.id DESC');
    expect($query)->not->toContain('cart.id DESC, cart.id DESC');

    [$defaultQuery] = $service->getSearchQuery([]);
    expect($defaultQuery)->toContain('ORDER BY cart.id ASC');

    [$invalidQuery] = $service->getSearchQuery(['sort' => 'session_id']);
    expect($invalidQuery)->toContain('ORDER BY cart.id ASC');
});

test('getSessionCart returns existing cart', function (): void {
    $service = new Service();

    $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';

    $cart = new Cart();
    $reflection = new ReflectionProperty($cart, 'id');
    $reflection->setValue($cart, 1);
    $cart->setSessionId($session_id);

    $cartRepo = Mockery::mock(CartRepository::class);
    $cartRepo->shouldReceive('findBySessionId')->atLeast()->once()->with($session_id)->andReturn($cart);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Cart::class)->andReturn($cartRepo);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();
    $sessionMock->shouldReceive('getId')->atLeast()->once()->andReturn($session_id);

    $di = container();
    $di['em'] = $emMock;
    $di['session'] = $sessionMock;
    $service->setDi($di);

    $result = $service->getSessionCart();

    expect($result)->toBeInstanceOf(Cart::class);
    expect($result->getSessionId())->toEqual($session_id);
});

test('getSessionCart creates a new cart when one does not exist', function (?int $sessionGetWillReturn, string $getCurrencyByClientIdExpects, string $getDefaultExpects): void {
    $service = new Service();

    $currencyModel = Mockery::mock(Currency::class)->shouldIgnoreMissing();
    $currencyId = random_int(0, 1000);
    $currencyModel->shouldReceive('getId')->andReturn($currencyId);

    $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';

    $cartRepo = Mockery::mock(CartRepository::class);
    $cartRepo->shouldReceive('findBySessionId')->atLeast()->once()->with($session_id)->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Cart::class)->andReturn($cartRepo);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();
    $sessionMock->shouldReceive('getId')->atLeast()->once()->andReturn($session_id);
    $sessionMock->shouldReceive('get')->atLeast()->once()->andReturn($sessionGetWillReturn);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    if ($sessionGetWillReturn === null) {
        $currencyRepositoryMock->shouldReceive('findDefault')->atLeast()->once()->andReturn($currencyModel);
    } else {
        $currencyRepositoryMock->shouldNotReceive('findDefault');
    }

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial();
    if ($getCurrencyByClientIdExpects === 'atLeastOnce') {
        $currencyServiceMock->shouldReceive('getCurrencyByClientId')->atLeast()->once()->andReturn($currencyModel);
    } else {
        $currencyServiceMock->shouldNotReceive('getCurrencyByClientId');
    }
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['em'] = $emMock;
    $di['session'] = $sessionMock;
    $di['mod_service'] = $di->protect(fn () => $currencyServiceMock);
    $service->setDi($di);

    $result = $service->getSessionCart();

    expect($result)->toBeInstanceOf(Cart::class);
    expect($result->getSessionId())->toEqual($session_id);
    expect($result->getCurrencyId())->toEqual($currencyId);
})->with([
    [100, 'atLeastOnce', 'never'],
    [null, 'never', 'atLeastOnce'],
]);

test('getSessionCart reloads the existing cart after a concurrent insert wins', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();

    $sessionId = 'rrcpqo7tkjh14d2vmf0car64k7';
    $currency = createEntity(Currency::class, ['id' => 1]);
    $winningCart = createEntity(Cart::class, ['id' => 2, 'session_id' => $sessionId]);

    $initialRepository = Mockery::mock(CartRepository::class);
    $initialRepository->shouldReceive('findBySessionId')->once()->with($sessionId)->andReturn(null);

    $winningRepository = Mockery::mock(CartRepository::class);
    $winningRepository->shouldReceive('findBySessionId')->once()->with($sessionId)->andReturn($winningCart);

    $driverException = new class extends Exception implements Doctrine\DBAL\Driver\Exception {
        public function getSQLState(): ?string
        {
            return '23000';
        }
    };
    $duplicateKeyException = new Doctrine\DBAL\Exception\UniqueConstraintViolationException($driverException, null);

    $initialEntityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $initialEntityManager->shouldReceive('getRepository')->once()->with(Cart::class)->andReturn($initialRepository);
    $initialEntityManager->shouldReceive('persist')->once();
    $initialEntityManager->shouldReceive('flush')->once()->andThrow($duplicateKeyException);

    $replacementEntityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $replacementEntityManager->shouldReceive('getRepository')->once()->with(Cart::class)->andReturn($winningRepository);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('findDefault')->once()->andReturn($currency);
    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $session = Mockery::mock(FOSSBilling\Session::class);
    $session->shouldReceive('getId')->once()->andReturn($sessionId);
    $session->shouldReceive('get')->once()->with('client_id')->andReturn(null);

    $di = container();
    $di['em'] = $initialEntityManager;
    $di['session'] = $session;
    $di['mod_service'] = $di->protect(fn () => $currencyService);

    $serviceMock->shouldReceive('resetEntityManager')->once()->andReturnUsing(function () use ($di, $replacementEntityManager): void {
        $di['em'] = $replacementEntityManager;
    });

    $serviceMock->setDi($di);

    expect($serviceMock->getSessionCart())->toBe($winningCart);
});

test('isStockAvailable returns false when product out of stock', function (): void {
    $product = createProductEntity();
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isStockAvailable')->once()->with($product, 6)->andReturn(false);

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);
    $result = $service->isStockAvailable($product, 6);
    expect($result)->toBeFalse();
});

test('isStockAvailable returns true when product in stock', function (): void {
    $product = createProductEntity();
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isStockAvailable')->once()->with($product, 6)->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);
    $result = $service->isStockAvailable($product, 6);
    expect($result)->toBeTrue();
});

test('isRecurrentPricing returns true', function (): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isRecurrentProductPricing')->once()->with(Mockery::type(Product::class))->andReturn(true);

    $productModelMock = createProductEntity();

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isRecurrentPricing($productModelMock);

    expect($result)->toBeTrue();
});

test('isPeriodEnabledForProduct returns false', function (): void {
    $enabled = false;
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isProductPeriodEnabled')->once()->with(Mockery::type(Product::class), 'monthly')->andReturn($enabled);

    $productModelMock = createProductEntity();

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isPeriodEnabledForProduct($productModelMock, 'monthly');

    expect($result)->toBeBool();
    expect($result)->toEqual($enabled);
});

test('isPeriodEnabledForProduct returns true', function (): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isProductPeriodEnabled')->once()->with(Mockery::type(Product::class), 'monthly')->andReturn(true);

    $productModelMock = createProductEntity();

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isPeriodEnabledForProduct($productModelMock, 'monthly');

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('removeProduct returns true', function (): void {
    $cartProduct = new CartProduct();
    $cartProductId = 1;
    $cartId = 10;
    $reflection = new ReflectionProperty($cartProduct, 'id');
    $reflection->setValue($cartProduct, $cartProductId);

    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, $cartId);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findOneByCartAndId')->atLeast()->once()->with($cartId, $cartProductId)->andReturn($cartProduct);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->with($cartId)->andReturn([$cartProduct]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->removeProduct($cart, $cartProductId);
    expect($result)->toBeTrue();
});

test('removeProduct throws exception when cart product not found', function (): void {
    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findOneByCartAndId')->atLeast()->once()->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->removeProduct($cart, 1))->toThrow(FOSSBilling\Exception::class);
});

test('removeProduct removes matching addons', function (): void {
    $cartId = 10;
    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, $cartId);

    $mainProductId = 7;
    $main = new CartProduct();
    $main->setProductId($mainProductId);
    $main->setConfig(json_encode(['domain_name' => 'example.com']));

    $addon = new CartProduct();
    $addon->setConfig(json_encode(['parent_id' => $mainProductId, 'domain_name' => 'example.com']));

    $unrelated = new CartProduct();
    $unrelated->setConfig(json_encode(['parent_id' => 999, 'domain_name' => 'other.com']));

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findOneByCartAndId')->once()->with($cartId, 1)->andReturn($main);
    $cartProductRepo->shouldReceive('findByCartId')->once()->with($cartId)->andReturn([$addon, $unrelated]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);
    $emMock->shouldReceive('remove')->once()->with($addon);
    $emMock->shouldReceive('remove')->once()->with($main);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    expect($service->removeProduct($cart, 1))->toBeTrue();
});

test('changeCartCurrency returns true', function (): void {
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $cart = new Cart();
    $reflection = new ReflectionProperty($cart, 'id');
    $reflection->setValue($cart, 1);

    $currency = Mockery::mock(Currency::class)->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->changeCartCurrency($cart, $currency);
    expect($result)->toBeTrue();
});

test('resetCart returns true', function (): void {
    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->with(1)->andReturn([new CartProduct(), new CartProduct()]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->resetCart($cart);
    expect($result)->toBeTrue();
});

test('removePromo returns true', function (): void {
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $cart = new Cart();
    $reflection = new ReflectionProperty($cart, 'id');
    $reflection->setValue($cart, 1);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->removePromo($cart);
    expect($result)->toBeTrue();
});

test('applyPromo returns true', function (): void {
    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);
    $cart->setPromoId(1);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->with(1)->andReturn([new CartProduct(), new CartProduct()]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $promo = createPromoEntity(2);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->applyPromo($cart, $promo);
    expect($result)->toBeTrue();
});

test('applyPromo returns true when already applied', function (): void {
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldNotReceive('persist');

    $promo = createPromoEntity(5);

    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);
    $cart->setPromoId(5);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->applyPromo($cart, $promo);
    expect($result)->toBeTrue();
});

test('applyPromo throws exception when cart is empty', function (): void {
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldNotReceive('persist');

    $promo = createPromoEntity(2);

    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);
    $cart->setPromoId(1);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->with(1)->andReturn([]);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->applyPromo($cart, $promo))->toThrow(FOSSBilling\Exception::class);
});

test('rm returns true', function (): void {
    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->with(1)->andReturn([new CartProduct()]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->rm($cart);
    expect($result)->toBeTrue();
});

test('isClientAbleToUsePromo returns false when client cannot use promo', function (): void {
    $promo = createPromoEntity(1)
        ->setOncePerClient(true);

    $client = createEntity(Client::class);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(false);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = new Service();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
    expect($result)->toBeFalse();
});

test('clientHadUsedPromo returns true', function (): void {
    $promo = createPromoEntity(1);

    $client = createEntity(Client::class);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('clientHasActivePromoApplication')->once()->with($client, $promo)->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $reflection = new ReflectionObject($service);
    $method = $reflection->getMethod('clientHadUsedPromo');
    $result = $method->invoke($service, $client, $promo);

    expect($result)->toBeTrue();
});

test('isClientAbleToUsePromo returns true once per client', function (): void {
    $promo = createPromoEntity(1);

    $client = createEntity(Client::class);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = new Service();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
    expect($result)->toBeTrue();
});

test('isClientAbleToUsePromo returns false when promo cannot be applied', function (): void {
    $promo = createPromoEntity(1);

    $client = createEntity(Client::class);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(false);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = new Service();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
    expect($result)->toBeFalse();
});

test('promoCanBeApplied returns expected result', function (Promo $promo, bool $expectedResult): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('promoCanBeApplied')->once()->with($promo)->andReturn($expectedResult);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->promoCanBeApplied($promo);
    expect($result)->toEqual($expectedResult);
})->with([
    [createPromoEntity(1)->setActive(false), false],
    [createPromoEntity(2)->setActive(true)->setMaxUses(5)->setUsed(5), false],
    [createPromoEntity(3)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('tomorrow')), false],
    [createPromoEntity(4)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('yesterday'))->setEndAt(new DateTime('yesterday')), false],
    [createPromoEntity(5)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('yesterday'))->setEndAt(new DateTime('tomorrow')), true],
]);

test('getCartProducts returns array of cart products', function (): void {
    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->with(1)->andReturn([new CartProduct()]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $di = container();
    $di['em'] = $emMock;
    $service = new Service();
    $service->setDi($di);

    $result = $service->getCartProducts($cart);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(CartProduct::class);
});

test('checkoutCart returns array with expected keys', function (): void {
    $cart = createEntity(Cart::class);
    $cart->promo_id = 1;

    $order = new Order();
    $orderIdReflection = new ReflectionProperty($order, 'id');
    $orderIdReflection->setValue($order, 99);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('createFromCart')->atLeast()->once()->andReturn([$order, 1, [1]]);
    $serviceMock->shouldReceive('isClientAbleToUsePromo')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('rm')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()->andReturn(true);

    $events = new ArrayObject();
    $dispatcher = new readonly class($events) {
        public function __construct(private ArrayObject $events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events->append($event);

            return $event;
        }
    };

    $invoice = createEntity(Invoice::class);

    $invoice->hash = sha1('str');

    $promo = new Promo();

    $client = createEntity(Client::class);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promo);

    $di = container();
    $di['event_dispatcher'] = $dispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '192.0.2.1']);
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock->setDi($di);
    $result = $serviceMock->checkoutCart($cart, $client);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('gateway_id');
    expect($result)->toHaveKey('invoice_hash');
    expect($result)->toHaveKey('order_id');
    expect($result)->toHaveKey('orders');
    expect($events->getArrayCopy())->toEqual([
        new Box\Mod\Cart\Event\BeforeClientCheckoutEvent((int) $cart->getId(), (int) $client->getId(), '192.0.2.1'),
        new Box\Mod\Order\Event\AfterClientOrderCreateEvent(99, (int) $client->getId(), '192.0.2.1'),
    ]);
});

test('checkoutCart throws exception when client is not able to use promo', function (): void {
    $cart = createEntity(Cart::class);
    $cart->promo_id = 1;

    $order = createEntity(Order::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isClientAbleToUsePromo')->atLeast()->once()->andReturn(false);
    $promo = new Promo();
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promo);

    $client = createEntity(Client::class);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->checkoutCart($cart, $client))->toThrow(FOSSBilling\Exception::class);
});

test('usePromo returns null', function (): void {
    $promo = createPromoEntity(1);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('usePromo')->once()->with($promo);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->usePromo($promo);

    expect($result)->toBeNull();
});

test('createFromCart uses database transaction', function (): void {
    $cart = createEntity(Cart::class);
    $cart->currency_id = 2;

    $client = createEntity(Client::class);
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);
    $currencyRepository->shouldNotReceive('findDefault');

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $order = new Order();
    $orderIdReflection = new ReflectionProperty($order, 'id');
    $orderIdReflection->setValue($order, 99);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturn([$order, null, [99]]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $clientService) {
        if ($serviceName === 'currency') {
            return $currencyService;
        }
        if ($serviceName === 'client') {
            return $clientService;
        }
    });

    $serviceMock->setDi($di);
    $result = $serviceMock->createFromCart($client);

    expect($result)->toBe([$order, null, [99]]);
});

test('createFromCart with promo entity uses product promo service', function (): void {
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;
    $cart->promo_id = 7;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $promo = new Promo();
    $promo->setCode('PROMO');
    $promoIdReflection = new ReflectionProperty($promo, 'id');
    $promoIdReflection->setValue($promo, 7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);
    $productService->shouldReceive('clientHasActivePromoApplicationForUpdate')->once()->with($client, $promo)->andReturn(false);
    $productService->shouldReceive('reserveStockForOrder')->once()->with(Mockery::type(Order::class));
    $productService->shouldReceive('reservePromosForOrder')->once()->with([$promo], Mockery::type(Order::class));
    $productService->shouldReceive('createCheckoutPromoRedemptionsForPromos')->once()->with(
        [$promo],
        $client,
        Mockery::on(fn (array $orders): bool => count($orders) === 1 && $orders[0] instanceof Order),
        null,
        PromoRedemption::STATUS_COMMITTED,
        [0 => [7 => 0.0]]
    );

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup('manual');

    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 13;

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order Created');
    $orderService->shouldReceive('toApiArray')->once()->with(Mockery::type(Order::class), false, $client)->andReturn([
        'product_id' => 5,
        'total' => 0,
        'discount' => 0,
    ]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct, $cart, [$cartProduct], [$promo])->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('getItemPromoDiscountShares')->once()->with($cartProduct, [$promo], $cart, [$cartProduct])->andReturn([7 => 0.0]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $productService->shouldReceive('findProductById')->twice()->with(5)->andReturn($product);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $serviceMock->setDi($di);
    $result = $serviceMock->createFromCart($client);

    expect($result[0])->toBeInstanceOf(Order::class);
    expect($result[1])->toBeNull();
    expect($result[2])->toBeArray();
    expect(count($result[2]))->toBe(1);
});

test('createFromCart aborts inside the transaction when the once-per-client guard trips', function (): void {
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;
    $cart->promo_id = 7;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $promo = new Promo();
    $promo->setCode('PROMO');
    $promoIdReflection = new ReflectionProperty($promo, 'id');
    $promoIdReflection->setValue($promo, 7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);
    // A concurrent checkout committed first: the in-transaction guard sees it.
    $productService->shouldReceive('clientHasActivePromoApplicationForUpdate')->once()->with($client, $promo)->andReturn(true);
    $productService->shouldNotReceive('reservePromosForOrder');
    $productService->shouldNotReceive('createCheckoutPromoRedemptionsForPromos');

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    // The guard runs first inside the transaction: no order is ever created.
    $serviceMock->shouldNotReceive('getCartProducts');

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        default => null,
    });

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createFromCart($client))
        ->toThrow(FOSSBilling\InformationException::class, 'You have already used this promo code. Please remove the promo code and checkout again.');
});

test('createFromCart sets the unpaid invoice id on orders when checkout produces an unpaid invoice', function (): void {
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup('manual');

    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 13;

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->twice()->with(5)->andReturn($product);
    $productService->shouldReceive('reserveStockForOrder')->once()->with(Mockery::type(Order::class));

    // Regression test for GH-4246: prepareInvoice()/approveInvoice() must hand back a
    // Doctrine Invoice entity whose getId() is strictly ?int, matching what
    // Order::setUnpaidInvoiceId() declares. Before the Invoice module was migrated to
    // Doctrine, this was a RedBean bean whose ->id was a string, which made every
    // gateway-backed checkout that left an invoice unpaid crash with a TypeError.
    $invoice = createEntity(Invoice::class, ['id' => 555]);
    expect($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID);

    $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceService->shouldReceive('prepareInvoice')->once()->with($client, Mockery::type('array'))->andReturn($invoice);
    $invoiceService->shouldReceive('approveInvoice')->once()->with($invoice, Mockery::type('array'))->andReturn(true);

    $clientBalanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $clientBalanceService->shouldReceive('getClientBalance')->once()->with($client)->andReturn(0.0);

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order Created');
    $orderService->shouldReceive('toApiArray')->once()->with(Mockery::type(Order::class), false, $client)->andReturn([
        'product_id' => 5,
        'total' => 0,
        'discount' => 0,
    ]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 100,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct, $cart, [$cartProduct], [])->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 100,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName . $sub) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        'Invoice' => $invoiceService,
        'ClientBalance' => $clientBalanceService,
        default => null,
    });

    $serviceMock->setDi($di);
    [$masterOrder, $invoiceModel, $ids] = $serviceMock->createFromCart($client);

    expect($invoiceModel)->toBe($invoice);
    expect($masterOrder->getUnpaidInvoiceId())->toBe(555);
    expect($ids)->toBeArray()->toHaveCount(1);
});

test('createFromCart keeps every non-addon order visible in legacy multi-item carts (GH-4333)', function (): void {
    // Carts built before family stamping carry no __cart_family token: each
    // standalone item becomes its own family (own group_id, group master),
    // while an addon row rejoins its parent product's family.
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->atLeast()->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $newProduct = function (int $id, bool $isAddon): Product {
        $product = new Product();
        $reflection = new ReflectionProperty($product, 'id');
        $reflection->setValue($product, $id);
        $product->setStatus('enabled');
        $product->setType('service');
        $product->setSetup('manual');
        $product->setIsAddon($isAddon);

        return $product;
    };
    $firstProduct = $newProduct(5, false);
    $secondProduct = $newProduct(6, false);
    $addonProduct = $newProduct(7, true);

    $cartProductOne = createEntity(CartProduct::class);
    $cartProductOne->id = 13;
    $cartProductTwo = createEntity(CartProduct::class);
    $cartProductTwo->id = 14;
    $cartProductThree = createEntity(CartProduct::class);
    $cartProductThree->id = 15;

    $itemFor = fn (int $productId, string $title, array $extra = []): array => array_merge([
        'product_id' => $productId,
        'form_id' => null,
        'title' => $title,
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ], $extra);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->atLeast()->once()->andReturnUsing(
        fn (int $id): Product => match ($id) {
            5 => $firstProduct,
            6 => $secondProduct,
            7 => $addonProduct,
            default => throw new RuntimeException('Unexpected product id ' . $id),
        }
    );
    $productService->shouldReceive('reserveStockForOrder')->times(3)->with(Mockery::type(Order::class));

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->times(3)->with(Mockery::type(Order::class), 'Order Created');
    $orderService->shouldReceive('toApiArray')
        ->times(3)
        ->with(Mockery::type(Order::class), false, $client)
        ->andReturnUsing(fn (Order $order): array => [
            'product_id' => $order->getProductId(),
            'total' => 0,
            'discount' => 0,
        ]);

    $createdOrders = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once()->with(Mockery::on(function ($entity) use (&$createdOrders): bool {
        if ($entity instanceof Order) {
            $createdOrders[] = $entity;
        }

        return true;
    }));
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1], ['id' => 2], ['id' => 3]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProductOne, $cartProductTwo, $cartProductThree]);
    $serviceMock->shouldReceive('cartProductToApiArray')->times(3)->andReturnUsing(
        fn (CartProduct $cartProduct): array => match ($cartProduct->getId()) {
            13 => $itemFor(5, 'First product'),
            14 => $itemFor(6, 'Second product'),
            // Legacy addon rows still carry the parent product id stamped by
            // Product\Service::getSelectedAddonsForCart().
            15 => $itemFor(7, 'Addon product', ['parent_id' => 5]),
            default => throw new RuntimeException('Unexpected cart product'),
        }
    );
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $serviceMock->setDi($di);
    [$masterOrder, $invoiceModel, $ids] = $serviceMock->createFromCart($client);

    expect($createdOrders)->toHaveCount(3);
    expect(array_map(fn (Order $order): bool => $order->isGroupMaster(), $createdOrders))->toBe([true, true, false]);
    expect(array_map(fn (Order $order): ?string => $order->getGroupId(), $createdOrders))->toBe(['3_1', '3_2', '3_1']);
    expect($masterOrder)->toBe($createdOrders[0]);
    expect($invoiceModel)->toBeNull();
    expect($ids)->toBeArray()->toHaveCount(3);
});

test('createFromCart groups stamped cart families under shared group ids', function (): void {
    // One add-to-cart action (parent + bundled domain/addons) shares a
    // __cart_family token and must end up in one group with a single master,
    // while a second family gets its own group.
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->atLeast()->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $newProduct = function (int $id, bool $isAddon): Product {
        $product = new Product();
        $reflection = new ReflectionProperty($product, 'id');
        $reflection->setValue($product, $id);
        $product->setStatus('enabled');
        $product->setType('service');
        $product->setSetup('manual');
        $product->setIsAddon($isAddon);

        return $product;
    };

    $productsById = [
        5 => $newProduct(5, false),
        6 => $newProduct(6, false),
        7 => $newProduct(7, true),
    ];

    $cartProductOne = createEntity(CartProduct::class);
    $cartProductOne->id = 13;
    $cartProductTwo = createEntity(CartProduct::class);
    $cartProductTwo->id = 14;
    $cartProductThree = createEntity(CartProduct::class);
    $cartProductThree->id = 15;

    $itemFor = fn (int $productId, string $title, string $family, array $extra = []): array => array_merge([
        'product_id' => $productId,
        'form_id' => null,
        'title' => $title,
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
        Service::CART_FAMILY_KEY => $family,
    ], $extra);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->atLeast()->once()->andReturnUsing(
        fn (int $id): Product => $productsById[$id] ?? throw new RuntimeException('Unexpected product id ' . $id)
    );
    $productService->shouldReceive('reserveStockForOrder')->times(3)->with(Mockery::type(Order::class));

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->times(3)->with(Mockery::type(Order::class), 'Order Created');
    $orderService->shouldReceive('toApiArray')
        ->times(3)
        ->with(Mockery::type(Order::class), false, $client)
        ->andReturnUsing(fn (Order $order): array => [
            'product_id' => $order->getProductId(),
            'total' => 0,
            'discount' => 0,
        ]);

    $createdOrders = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once()->with(Mockery::on(function ($entity) use (&$createdOrders): bool {
        if ($entity instanceof Order) {
            $createdOrders[] = $entity;
        }

        return true;
    }));
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1], ['id' => 2], ['id' => 3]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProductOne, $cartProductTwo, $cartProductThree]);
    $serviceMock->shouldReceive('cartProductToApiArray')->times(3)->andReturnUsing(
        fn (CartProduct $cartProduct): array => match ($cartProduct->getId()) {
            13 => $itemFor(5, 'First product', 'family-a'),
            14 => $itemFor(7, 'Addon product', 'family-a', ['parent_id' => 5]),
            15 => $itemFor(6, 'Second product', 'family-b'),
            default => throw new RuntimeException('Unexpected cart product'),
        }
    );
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $serviceMock->setDi($di);
    [$masterOrder, $invoiceModel, $ids] = $serviceMock->createFromCart($client);

    expect($createdOrders)->toHaveCount(3);
    expect(array_map(fn (Order $order): bool => $order->isGroupMaster(), $createdOrders))->toBe([true, false, true]);
    expect(array_map(fn (Order $order): ?string => $order->getGroupId(), $createdOrders))->toBe(['3_1', '3_1', '3_2']);
    // Family bookkeeping must not leak into the stored order config.
    foreach ($createdOrders as $order) {
        expect(json_decode((string) $order->getConfig(), true))->not->toHaveKey(Service::CART_FAMILY_KEY);
    }
    expect($masterOrder)->toBe($createdOrders[0]);
    expect($invoiceModel)->toBeNull();
    expect($ids)->toBeArray()->toHaveCount(3);
});

test('createFromCart compensates promo usage on transaction failure', function (): void {
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;
    $cart->promo_id = 7;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $promo = new Promo();
    $promo->setCode('PROMO');
    $promoIdReflection = new ReflectionProperty($promo, 'id');
    $promoIdReflection->setValue($promo, 7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);
    $productService->shouldReceive('clientHasActivePromoApplicationForUpdate')->once()->with($client, $promo)->andReturn(false);
    $productService->shouldReceive('reserveStockForOrder')->once()->with(Mockery::type(Order::class));
    $productService->shouldReceive('reservePromosForOrder')->once()->with([$promo], Mockery::type(Order::class));
    $productService->shouldReceive('createCheckoutPromoRedemptionsForPromos')
        ->andThrow(new RuntimeException('Doctrine flush failed'));
    $productService->shouldReceive('compensateCheckoutPromoFailure')
        ->once()
        ->with($promo, Mockery::any(), Mockery::any());
    $productService->shouldReceive('releaseReservedStockForOrder')
        ->once()
        ->with(Mockery::type(Order::class), 'checkout_failed');

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup('manual');

    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 13;

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order Created');

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct, $cart, [$cartProduct], [$promo])->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('getItemPromoDiscountShares')->once()->with($cartProduct, [$promo], $cart, [$cartProduct])->andReturn([7 => 0.0]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $productService->shouldReceive('findProductById')->once()->with(5)->andReturn($product);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createFromCart($client))
        ->toThrow(RuntimeException::class, 'Doctrine flush failed');
});

test('createFromCart releases reserved stock on transaction failure', function (): void {
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;
    $cart->promo_id = 7;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $promo = new Promo();
    $promo->setCode('PROMO');
    $promoIdReflection = new ReflectionProperty($promo, 'id');
    $promoIdReflection->setValue($promo, 7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);
    $productService->shouldReceive('clientHasActivePromoApplicationForUpdate')->once()->with($client, $promo)->andReturn(false);
    $productService->shouldReceive('reserveStockForOrder')->once()->with(Mockery::type(Order::class));
    $productService->shouldReceive('reservePromosForOrder')->once()->with([$promo], Mockery::type(Order::class));

    // Simulate Doctrine-side failure during redemption creation.
    $productService->shouldReceive('createCheckoutPromoRedemptionsForPromos')
        ->andThrow(new RuntimeException('Doctrine flush failed'));

    $productService->shouldReceive('releaseReservedStockForOrder')
        ->once()
        ->with(Mockery::type(Order::class), 'checkout_failed');

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup('manual');

    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 13;

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order Created');

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct, $cart, [$cartProduct], [$promo])->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('getItemPromoDiscountShares')->once()->with($cartProduct, [$promo], $cart, [$cartProduct])->andReturn([7 => 0.0]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $productService->shouldReceive('findProductById')->once()->with(5)->andReturn($product);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createFromCart($client))
        ->toThrow(RuntimeException::class, 'Doctrine flush failed');
});

test('createFromCart does not roll back order creation when synchronous activation fails', function (): void {
    // A $0-total order is activated synchronously, inside the same
    // transaction that creates it. The recovery path used to record the
    // failure with the literal status 'error', which isn't a valid Order
    // status - InformationException would escape and roll back the whole
    // checkout, not just fail this one order.
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup(ProductService::SETUP_AFTER_PAYMENT);

    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 13;

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order Created');
    $orderService->shouldReceive('toApiArray')->once()->with(Mockery::type(Order::class), false, $client)->andReturn([
        'product_id' => 5,
        'total' => 0,
        'discount' => 0,
    ]);
    // An \Error (not an \Exception) to prove the catch was widened to
    // \Throwable - a narrower catch (\Exception) would miss this and let it
    // escape wrapInTransaction(), rolling back the whole checkout.
    $orderService->shouldReceive('activateOrder')->once()->andThrow(new Error('Simulated provisioning failure'));

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct, $cart, [$cartProduct], [])->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->twice()->with(5)->andReturn($product);
    $productService->shouldReceive('reserveStockForOrder')->once()->with(Mockery::type(Order::class));

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn ($serviceName, $sub = '') => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $orderService->setDi($di);
    $serviceMock->setDi($di);

    $result = $serviceMock->createFromCart($client);

    expect($result[0])->toBeInstanceOf(Order::class);
});

test('usePromo throws exception when limit reached', function (): void {
    $promo = createPromoEntity(1);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('usePromo')->once()->with($promo)->andThrow(new FOSSBilling\InformationException('This promo code has reached its maximum number of uses.'));

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    expect(fn () => $service->usePromo($promo))->toThrow(FOSSBilling\InformationException::class);
});

test('findActivePromoByCode returns promo', function (): void {
    $promo = new Promo();

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findActivePromoByCode')->once()->with('CODE')->andReturn($promo);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->findActivePromoByCode('CODE');

    expect($result)->toBeInstanceOf(Promo::class);
});

test('addItem throws exception when recurring payment period param missing', function (): void {
    $cartModel = createEntity(Cart::class);

    $productModel = createProductEntity(type: 'Custom');

    $data = [];

    $eventDispatcher = new SymfonyEventDispatcher();
    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(true);

    $productService = new ProductService();
    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->andThrow(new FOSSBilling\Exception('Period parameter not passed'));
    $di['validator'] = $validatorMock;
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Period parameter not passed');
});

test('addItem throws exception when recurring payment period is not enabled', function (): void {
    $cartModel = createEntity(Cart::class);

    $productModel = createProductEntity(type: 'hosting');

    $data = ['period' => '1W'];

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('isPeriodEnabledForProduct')->atLeast()->once()->andReturn(false);

    $productService = new ProductService();
    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();
    $di['validator'] = $validatorMock;
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Selected billing period is invalid');
});

test('addItem throws exception when out of stock', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $productModel = createProductEntity(type: 'hosting');

    $data = [];

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(false);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });

    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, $data))
        ->toThrow(FOSSBilling\Exception::class, 'This item is currently out of stock');
});

test('addItem rejects cumulative stock overflow', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 10);

    $productModel = createProductEntity(id: 7, type: 'hosting');
    $productModel->setStockControl(true);
    $productModel->setQuantityInStock(1);

    $existingCartProduct = createEntity(CartProduct::class);
    $existingCartProduct->product_id = 7;
    $existingCartProduct->config = json_encode(['quantity' => 1]);

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();
    $productServiceMock = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productServiceMock->shouldReceive('isStockAvailable')->once()->with($productModel, 2)->andReturn(false);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([$existingCartProduct]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productServiceMock) {
        if ($name === 'Product') {
            return $productServiceMock;
        }

        return $serviceHostingServiceMock;
    });

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, ['quantity' => 1]))
        ->toThrow(FOSSBilling\Exception::class, 'This item is currently out of stock');
});

test('addItem rejects duplicate domain register', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $productModel = createProductEntity(type: 'domain');

    // An existing cart item already holds example.com via register keys.
    $existingCartProduct = createEntity(CartProduct::class);
    $existingCartProduct->config = json_encode(['register_sld' => 'example', 'register_tld' => '.com']);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([$existingCartProduct]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, ['register_sld' => 'example', 'register_tld' => '.com']))
        ->toThrow(FOSSBilling\InformationException::class, 'This domain is already in the cart.');
});

test('addItem rejects duplicate domain transfer', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 2);

    $productModel = createProductEntity(type: 'domain');

    // An existing cart item holds example.net via transfer keys.
    $existingCartProduct = createEntity(CartProduct::class);
    $existingCartProduct->config = json_encode(['transfer_sld' => 'example', 'transfer_tld' => '.net']);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([$existingCartProduct]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, ['transfer_sld' => 'example', 'transfer_tld' => '.net']))
        ->toThrow(FOSSBilling\InformationException::class, 'This domain is already in the cart.');
});

test('addItem rejects duplicate domain nested', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 3);

    $productModel = createProductEntity(type: 'hosting');

    // An existing hosting cart item stores the domain under the nested 'domain' key.
    $existingCartProduct = createEntity(CartProduct::class);
    $existingCartProduct->config = json_encode([
        'domain' => ['register_sld' => 'mysite', 'register_tld' => '.org'],
    ]);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([$existingCartProduct]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, [
        'domain' => ['register_sld' => 'mysite', 'register_tld' => '.org'],
    ]))->toThrow(FOSSBilling\InformationException::class, 'This domain is already in the cart.');
});

test('addItem for hosting type returns true', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $productModel = createProductEntity(type: 'hosting');

    $data = [];

    $eventDispatcher = new SymfonyEventDispatcher();

    $productDomainModel = createProductEntity(type: 'domain');
    $domainProduct = ['config' => [], 'product' => $productDomainModel];

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();
    $serviceHostingServiceMock->shouldReceive('getDomainProductFromConfig')->atLeast()->once()->andReturn($domainProduct);
    $serviceHostingServiceMock->shouldReceive('attachOrderConfig')->atLeast()->once()->andReturn([]);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')->atLeast()->once();

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $di['logger'] = new FOSSBilling\Logger();

    $productService->setDi($di);
    $serviceMock->setDi($di);
    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('addItem for license type returns true', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $productModel = createProductEntity(type: 'license');

    $data = [];

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceLicenseServiceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class)->shouldIgnoreMissing();
    $serviceLicenseServiceMock->shouldReceive('attachOrderConfig')->atLeast()->once()->andReturn([]);
    $serviceLicenseServiceMock->shouldReceive('validateOrderData')->atLeast()->once()->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')->atLeast()->once();

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceLicenseServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceLicenseServiceMock;
    });
    $di['logger'] = new FOSSBilling\Logger();

    $productService->setDi($di);
    $serviceMock->setDi($di);
    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('addItem for custom type returns true', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $productModel = createProductEntity(type: 'custom');

    $data = [];

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceCustomServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $serviceCustomServiceMock->shouldReceive('validateCustomForm')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $productService = new ProductService();
    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceCustomServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceCustomServiceMock;
    });
    $di['logger'] = new FOSSBilling\Logger();

    $productService->setDi($di);
    $serviceMock->setDi($di);
    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('toApiArray returns expected structure', function (): void {
    $cartModel = createEntity(Cart::class);

    $cartProductModel = createEntity(CartProduct::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCartProducts')->atLeast()->once()->andReturn([$cartProductModel]);
    $cartProductApiArray = [
        'total' => 1,
        'setup_price' => 0,
        'discount' => 0,
        'period' => '1M',
    ];
    $serviceMock->shouldReceive('cartProductToApiArray')
        ->once()
        ->with($cartProductModel, $cartModel, [$cartProductModel], [])
        ->andReturn($cartProductApiArray);

    $currencyService = Mockery::mock(CurrencyService::class)->shouldIgnoreMissing();
    $currencyModel = Mockery::mock(Currency::class)->shouldIgnoreMissing();
    $currencyModel->shouldReceive('toApiArray')->andReturn([]);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    $currencyRepositoryMock->shouldReceive('find')->atLeast()->once()->andReturn($currencyModel);

    $currencyService->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = new Pimple\Container();
    $di['mod_service'] = $di->protect(fn () => $currencyService);

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($cartModel);

    $expected = [
        'promocode' => null,
        'promo_source' => null,
        'auto_promos' => [],
        'discount' => 0,
        'total' => 1,
        'items' => [$cartProductApiArray],
        'currency' => [],
        'subtotal' => 1,
        'subscribable' => true,
    ];
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('cart is not subscribable when items use different billing periods', function (): void {
    $cartModel = createEntity(Cart::class);

    $cartProducts = [createEntity(CartProduct::class), createEntity(CartProduct::class)];
    foreach ($cartProducts as $cartProduct) {
    }

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCartProducts')->once()->andReturn($cartProducts);
    $serviceMock->shouldReceive('cartProductToApiArray')->andReturn(
        ['total' => 10, 'setup_price' => 0, 'discount' => 0, 'period' => '1M'],
        ['total' => 10, 'setup_price' => 0, 'discount' => 0, 'period' => '1Y'],
    );

    $currency = Mockery::mock(Currency::class);
    $currency->shouldReceive('toApiArray')->once()->andReturn([]);
    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->andReturn($currency);
    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $currencyService);
    $serviceMock->setDi($di);

    expect($serviceMock->toApiArray($cartModel)['subscribable'])->toBeFalse();
});

test('getProductDiscount returns discount array', function (): void {
    $cartProductModel = new CartProduct();
    $cpReflection = new ReflectionProperty($cartProductModel, 'id');
    $cpReflection->setValue($cartProductModel, 1);

    $modelCart = new Cart();
    $cartReflection = new ReflectionProperty($modelCart, 'id');
    $cartReflection->setValue($modelCart, 1);
    $modelCart->setPromoId(1);
    $cartProductModel->setCart($modelCart);

    $promoModel = new Promo();

    $discountPrice = 25;

    $di = container();
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promoModel);
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getItemPromoDiscountShares')->atLeast()->once()->andReturn([0 => $discountPrice]);

    $serviceMock->setDi($di);
    $setupPrice = 0;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual($discountPrice);
    $discountSetup = 0;
    expect($result[1])->toEqual($discountSetup);
});

test('getProductDiscount returns zeros when no promo', function (): void {
    $cartProductModel = new CartProduct();
    $cpReflection = new ReflectionProperty($cartProductModel, 'id');
    $cpReflection->setValue($cartProductModel, 1);

    $modelCart = new Cart();
    $cartReflection = new ReflectionProperty($modelCart, 'id');
    $cartReflection->setValue($modelCart, 1);
    $cartProductModel->setCart($modelCart);

    $di = container();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')->atLeast()->once()->andReturn(0);

    $serviceMock->setDi($di);
    $setupPrice = 0;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual(0);
    expect($result[1])->toEqual(0);
});

test('getProductDiscount returns free setup discount', function (): void {
    $cartProductModel = new CartProduct();
    $cpReflection = new ReflectionProperty($cartProductModel, 'id');
    $cpReflection->setValue($cartProductModel, 1);

    $modelCart = new Cart();
    $cartReflection = new ReflectionProperty($modelCart, 'id');
    $cartReflection->setValue($modelCart, 1);
    $modelCart->setPromoId(1);
    $cartProductModel->setCart($modelCart);

    $promoModel = new Promo();
    $promoModel->setFreeSetup(true);

    $discountPrice = 25;

    $di = container();
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promoModel);
    $productService->shouldReceive('isPromoApplicableToProductById')->atLeast()->once()->andReturn(true);
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getItemPromoDiscountShares')->atLeast()->once()->andReturn([0 => $discountPrice]);

    $serviceMock->setDi($di);
    $setupPrice = 25;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual($discountPrice);
    $discountSetup = $setupPrice;
    expect($result[1])->toEqual($discountSetup);
});

test('getProductDiscount does not waive setup fee for a product the promo is not applicable to', function (): void {
    $cartProductModel = new CartProduct();
    $cpReflection = new ReflectionProperty($cartProductModel, 'id');
    $cpReflection->setValue($cartProductModel, 1);

    $modelCart = new Cart();
    $cartReflection = new ReflectionProperty($modelCart, 'id');
    $cartReflection->setValue($modelCart, 1);
    $modelCart->setPromoId(1);
    $cartProductModel->setCart($modelCart);

    $promoModel = new Promo();
    $promoModel->setFreeSetup(true);

    $di = container();
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promoModel);
    // Promo is restricted to a different product/period, so it does not apply here.
    $productService->shouldReceive('isPromoApplicableToProductById')->atLeast()->once()->andReturn(false);
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getItemPromoDiscountShares')->atLeast()->once()->andReturn([0 => 0]);

    $serviceMock->setDi($di);
    $setupPrice = 25;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual(0);
    expect($result[1])->toEqual(0);
});

test('isPromoAvailableForClientGroup returns expected result', function (Promo $promo, ?Client $client, bool $expectedResult): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isPromoAvailableForClientGroup')->once()->with($promo, null)->andReturn($expectedResult);

    $di = container();
    $di['loggedin_client'] = $client;
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isPromoAvailableForClientGroup($promo);

    expect($result)->toEqual($expectedResult);
})->with(fn (): array => [
    [createPromoEntity(1)->setClientGroups(json_encode([])), createEntity(Client::class), true],
    [createPromoEntity(2)->setClientGroups(json_encode([1, 2])), createEntity(Client::class, ['clientGroup' => null]), false],
    [createPromoEntity(3)->setClientGroups(json_encode([1, 2])), createEntity(Client::class, ['clientGroup' => null]), false],
    [createPromoEntity(4)->setClientGroups(json_encode([1, 2])), createEntity(Client::class, ['clientGroup' => null]), true],
    [createPromoEntity(5)->setClientGroups(json_encode([])), null, true],
    [createPromoEntity(6)->setClientGroups(json_encode([1, 2])), null, false],
]);

test('addItem strips client-injected hosting_plan_id', function (): void {
    // A client must not be able to override the admin-configured hosting_plan_id
    // by injecting it into the cart/add_item request. We assert this at the
    // attachOrderConfig boundary: by the time the payload reaches
    // Servicehosting::attachOrderConfig, the central filter in
    // Product\Service::prepareCartProductConfig must have stripped the
    // client-injected hosting_plan_id (and server_id).
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    // Admin-configured product bound to hosting plan id 1.
    $productModel = createProductEntity(
        type: 'hosting',
        config: json_encode([
            'hosting_plan_id' => 1,
            'server_id' => 1,
            'allow_domain_own' => true,
        ]),
    );

    // Attacker payload injects premium hosting_plan_id (2), plus a
    // legitimate-looking domain action and billing period.
    $attackerPayload = [
        'period' => '1M',
        'domain' => [
            'action' => 'owndomain',
            'owndomain_sld' => 'evil',
            'owndomain_tld' => '.com',
        ],
        'hosting_plan_id' => 2,   // the CVE injection
        'server_id' => 99,        // additional injected admin field
        'multiple' => 1,
    ];

    $eventDispatcher = new SymfonyEventDispatcher();

    $productDomainModel = createProductEntity(type: 'domain');
    $domainProduct = ['config' => [], 'product' => $productDomainModel];

    // Partial mock of Servicehosting with the REAL clientSettableConfigKeys
    // (so the central filter actually runs), but stubbed attachOrderConfig to
    // capture the filtered payload it would receive.
    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->makePartial();
    $serviceHostingServiceMock->shouldReceive('getDomainProductFromConfig')->atLeast()->once()->andReturn($domainProduct);
    $serviceHostingServiceMock->shouldReceive('validateOrderData')->atLeast()->once()->andReturn(null);

    $capturedAttachInputs = [];
    $serviceHostingServiceMock->shouldReceive('attachOrderConfig')
        ->atLeast()->once()
        ->andReturnUsing(function (Product $product, array $data) use (&$capturedAttachInputs): array {
            $capturedAttachInputs[] = $data;

            return $data;
        });

    $productService = new ProductService();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')->atLeast()->once();

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $di['logger'] = new FOSSBilling\Logger();

    $productService->setDi($di);
    $serviceHostingServiceMock->setDi($di);
    $serviceMock->setDi($di);

    $result = $serviceMock->addItem($cartModel, $productModel, $attackerPayload);
    expect($result)->toBeTrue();

    // The filter must have run (the call must have reached attachOrderConfig
    // at least once for the hosting product).
    expect($capturedAttachInputs)->not->toBeEmpty('attachOrderConfig was not called; test setup is broken');

    // attachOrderConfig is called once per item in the cart-add $list
    // (hosting product + domain product). Identify the hosting call by the
    // presence of the legitimate client payload keys (period / domain).
    $hostingCall = null;
    foreach ($capturedAttachInputs as $capturedAttachInput) {
        if (isset($capturedAttachInput['domain']) || isset($capturedAttachInput['period'])) {
            $hostingCall = $capturedAttachInput;

            break;
        }
    }
    expect($hostingCall)->not->toBeNull('hosting-item attachOrderConfig call not captured; test setup is broken');

    // The attacker-injected admin-controlled keys must NOT have survived the
    // filter. The admin-configured values for these keys will be merged back
    // inside attachOrderConfig itself (Servicehosting does array_merge with
    // the product config there); we are testing the filter boundary, so we
    // assert they were dropped from the client payload before that merge.
    expect($hostingCall)->not->toHaveKey('hosting_plan_id');
    expect($hostingCall)->not->toHaveKey('server_id');

    // Legitimate client-settable keys survive the filter and reach
    // attachOrderConfig intact.
    expect($hostingCall)->toHaveKey('period');
    expect($hostingCall)->toHaveKey('domain');
    expect($hostingCall)->toHaveKey('multiple');
    expect($hostingCall['domain'])->toHaveKey('action', 'owndomain');
});

test('addItem stamps one shared cart family across parent and addons', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $parentModel = createProductEntity(id: 5, type: 'custom');
    $addonModel = createProductEntity(id: 9, type: 'custom');

    [$eventDispatcher, $events] = cartAddItemEventDispatcher();

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $productServiceMock = Mockery::mock(ProductService::class);
    $productServiceMock->shouldReceive('getProductModuleService')->atLeast()->once()->andReturn(new stdClass());
    $productServiceMock->shouldReceive('prepareCartProductConfig')->atLeast()->once()->andReturnUsing(fn (Product $product, array $config): array => $config);
    $productServiceMock->shouldReceive('getSelectedAddonsForCart')
        ->once()
        ->with($parentModel, ['9' => ['selected' => true]])
        ->andReturn([['product' => $addonModel, 'config' => ['selected' => true]]]);

    $storedConfigs = [];
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')
        ->atLeast()->once()
        ->with(Mockery::type(Cart::class), Mockery::type(Product::class), Mockery::type('array'))
        ->andReturnUsing(function (Cart $cart, Product $product, array $config) use (&$storedConfigs): bool {
            $storedConfigs[] = $config;

            return true;
        });

    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(fn ($name) => $name === 'Product' ? $productServiceMock : new stdClass());
    $di['logger'] = new FOSSBilling\Logger();
    $serviceMock->setDi($di);

    $addData = [
        'addons' => ['9' => ['selected' => true]],
        'domain' => [
            'action' => 'transfer',
            'transfer_sld' => 'example',
            'transfer_tld' => '.com',
            'transfer_code' => 'secret-epp-code',
        ],
        'hosting_password' => 'secret-hosting-password',
        'custom_field' => 'public-value',
    ];

    expect($serviceMock->addItem($cartModel, $parentModel, $addData))->toBeTrue();
    expect($storedConfigs)->toHaveCount(2);

    $firstFamily = $storedConfigs[0][Service::CART_FAMILY_KEY] ?? null;
    expect($firstFamily)->toBeString();
    expect($firstFamily)->not->toBe('');
    expect($storedConfigs[1][Service::CART_FAMILY_KEY] ?? null)->toBe($firstFamily);

    // A separate add-to-cart action starts a new family.
    expect($serviceMock->addItem($cartModel, $parentModel, []))->toBeTrue();
    expect($storedConfigs)->toHaveCount(3);
    $secondFamily = $storedConfigs[2][Service::CART_FAMILY_KEY] ?? null;
    expect($secondFamily)->toBeString();
    expect($secondFamily)->not->toBe('');
    expect($secondFamily)->not->toBe($firstFamily);

    expect(array_map(static fn (object $event): string => $event::class, $events->getArrayCopy()))
        ->toBe([
            BeforeProductAddedToCartEvent::class,
            AfterProductAddedToCartEvent::class,
            BeforeProductAddedToCartEvent::class,
            AfterProductAddedToCartEvent::class,
        ]);
    expect($events[0]->cartId)->toBe(1);
    expect($events[0]->productId)->toBe(5);
    expect(array_keys(get_object_vars($events[0])))->toBe(['cartId', 'productId']);
    expect($events[1]->cartId)->toBe(1);
    expect($events[1]->productId)->toBe(5);
    expect(array_keys(get_object_vars($events[1])))->toBe(['cartId', 'productId']);
    expect($events[2]->cartId)->toBe(1);
    expect($events[2]->productId)->toBe(5);
    expect($events[3]->cartId)->toBe(1);
    expect($events[3]->productId)->toBe(5);
});

test('getEffectiveCartPromos returns the manual promo when a code is set', function (): void {
    $cart = createEntity(Cart::class);
    $cart->promo_id = 7;

    $promo = createPromoEntity(7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $service = new Service();
    $service->setDi($di);

    $result = $service->getEffectiveCartPromos($cart);

    expect($result['source'])->toBe('manual');
    expect($result['promos'])->toBe([$promo]);
});

test('getEffectiveCartPromos resolves automatic promos for a logged-in client', function (): void {
    $cart = createEntity(Cart::class);

    $client = createEntity(Client::class);
    $cartProduct = createEntity(CartProduct::class);
    $product = createProductEntity(5);
    $promo = createPromoEntity(7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->once()->andReturn($product);
    $productService->shouldReceive('resolveAutoPromosForLines')
        ->once()
        ->with($client, Mockery::type('array'))
        ->andReturn([$promo]);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $di['loggedin_client'] = $client;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->setDi($di);

    $result = $serviceMock->getEffectiveCartPromos($cart);

    expect($result['source'])->toBe('auto');
    expect($result['promos'])->toBe([$promo]);
});

test('getEffectiveCartPromos returns empty for guests without a manual code', function (): void {
    $cart = createEntity(Cart::class);

    $service = new Service();
    $service->setDi(container());

    $result = $service->getEffectiveCartPromos($cart);

    expect($result)->toBe(['source' => null, 'promos' => []]);
});

test('getItemPromoDiscountShares splits the capped total across promos', function (): void {
    $cartProduct = new CartProduct();
    $cart = new Cart();
    $cartProduct->setCart($cart);

    $first = createPromoEntity(7);
    $second = createPromoEntity(8);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getCartProductViewData')
        ->once()
        ->andReturn(['price' => 100.0, 'quantity' => 1]);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getItemPromoDiscount')
        ->twice()
        ->andReturn(60.0, 40.0);
    $serviceMock->setDi($di);

    $shares = $serviceMock->getItemPromoDiscountShares($cartProduct, [$first, $second], $cart);

    expect($shares[7])->toEqual(60.0);
    expect($shares[8])->toEqual(40.0);
});

test('getItemPromoDiscountShares scales shares down to the item subtotal', function (): void {
    $cartProduct = new CartProduct();
    $cart = new Cart();
    $cartProduct->setCart($cart);

    $first = createPromoEntity(7);
    $second = createPromoEntity(8);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getCartProductViewData')
        ->once()
        ->andReturn(['price' => 100.0, 'quantity' => 1]);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getItemPromoDiscount')
        ->twice()
        ->andReturn(90.0, 80.0);
    $serviceMock->setDi($di);

    $shares = $serviceMock->getItemPromoDiscountShares($cartProduct, [$first, $second], $cart);

    // 90:80 scaled to a 100.00 total stays reconciled to the cent.
    expect(array_sum($shares))->toEqual(100.0);
    expect($shares[7])->toBeGreaterThan($shares[8]);
});

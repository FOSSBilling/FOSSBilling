<?php

class BBTestCase extends PHPUnit\Framework\TestCase
{
    protected function getDi(): Pimple\Container
    {
        $di = new Pimple\Container();
        $di['validator'] = (fn (): FOSSBilling\Validate => new FOSSBilling\Validate());
        $di['tools'] = (fn (): FOSSBilling\Tools => new FOSSBilling\Tools());
        $di['config'] = [
            'salt' => 'test_salt',
            'url' => 'http://localhost/',
        ];

        $di['logger'] = $this->createMock(\Box_Log::class);

        $di['em'] = $this->createMock(\Doctrine\ORM\EntityManager::class);

        $di['product_type_registry'] = function () use ($di) {
            $registry = new FOSSBilling\ProductTypeRegistry();
            $registry->setDi($di);
            $registry->loadFromFilesystem(PATH_ROOT . '/src/extensions/products');

            return $registry;
        };

        return $di;
    }

    /**
     * Helper method to validate required parameters for API methods with RequiredParams attribute.
     * This simulates the validation that happens in Api_Handler when methods are called through it.
     *
     * @param Api_Abstract $api        The API instance
     * @param string       $methodName The method name to validate
     * @param array        $data       The data to validate
     *
     * @throws FOSSBilling\InformationException if validation fails
     */
    protected function validateRequiredParams(Api_Abstract $api, string $methodName, array $data): void
    {
        $handler = new Api_Handler(new Model_Admin());
        $handler->validateRequiredParams($api, $methodName, $data);
    }

    /**
     * Helper method to get a DI container with a mocked EntityManager that expects persist/flush calls.
     * Use this for tests that verify database operations through Doctrine.
     *
     * @return Pimple\Container
     */
    protected function getDiWithMockEntityManager(): Pimple\Container
    {
        $di = $this->getDi();

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di['em'] = $emMock;

        return $di;
    }

    /**
     * Helper method to get a DI container with a mocked EntityManager that expects remove/flush calls.
     * Use this for tests that verify entity deletion through Doctrine.
     *
     * @return Pimple\Container
     */
    protected function getDiWithMockEntityManagerForDelete(): Pimple\Container
    {
        $di = $this->getDi();

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di['em'] = $emMock;

        return $di;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $refl = new ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && !str_starts_with($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setValue($this, null);
            }
        }
    }
}

<?php

class BBTestCaseContainer extends Pimple\Container
{
    public function __construct(private readonly Box\Mod\Staff\Service $staffService, array $values = [])
    {
        parent::__construct($values);
    }

    private function isStaffLikeService(mixed $service): bool
    {
        if (!is_object($service)) {
            return false;
        }

        foreach (['checkPermissionsAndThrowException', 'hasPermission', 'getCronAdmin'] as $method) {
            if (method_exists($service, $method)) {
                return true;
            }
        }

        return false;
    }

    #[ReturnTypeWillChange]
    #[Override]
    public function offsetSet($id, $value): void
    {
        if ($id === 'mod_service' && is_object($value) && method_exists($value, '__invoke')) {
            $value = $this->protect(function (...$args) use ($value) {
                $serviceName = strtolower((string) ($args[0] ?? ''));
                $resolvedService = null;
                $resolutionError = null;

                try {
                    $resolvedService = $value(...$args);
                } catch (Throwable $resolutionError) {
                }

                if ($serviceName === 'staff' && !$this->isStaffLikeService($resolvedService)) {
                    return $this->staffService;
                }

                if ($resolutionError instanceof Throwable) {
                    throw $resolutionError;
                }

                return $resolvedService;
            });
        }

        parent::offsetSet($id, $value);
    }
}

class BBTestCase extends PHPUnit\Framework\TestCase
{
    protected function createStaffServiceMock(): Box\Mod\Staff\Service
    {
        $staffService = $this->createMock(Box\Mod\Staff\Service::class);
        $staffService->expects($this->any())
            ->method('checkPermissionsAndThrowException');

        return $staffService;
    }

    protected function getDi(): Pimple\Container
    {
        $staffService = $this->createStaffServiceMock();
        $di = new BBTestCaseContainer($staffService);

        $di['validator'] = (fn (): FOSSBilling\Validate => new FOSSBilling\Validate());
        $di['tools'] = (fn (): FOSSBilling\Tools => new FOSSBilling\Tools());
        $di['mod_service'] = $di->protect(
            fn (string $service): Box\Mod\Staff\Service => match (strtolower($service)) {
                'staff' => $staffService,
                default => throw new Pimple\Exception\UnknownIdentifierException(sprintf('Identifier "%s" is not defined.', $service)),
            }
        );
        $di['config'] = [
            'salt' => 'test_salt',
            'url' => 'http://localhost/',
        ];

        return $di;
    }

    protected function createAdminApi(string $className): object
    {
        $api = new $className();
        if ($api instanceof Api_Abstract) {
            $api->setDi($this->getDi());
        }

        return $api;
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

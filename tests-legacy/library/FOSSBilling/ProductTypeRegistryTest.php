<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ProductTypeRegistryTest extends \BBTestCase
{
    private $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/fossbilling_product_types_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0o755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        $this->tempDir = '';
        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }
        rmdir($path);
    }

    private function createManifest(string $code, array $overrides = []): string
    {
        $manifest = array_merge([
            'code' => $code,
            'label' => ucfirst($code),
            'handler_class' => 'FOSSBilling\\ProductType\\' . ucfirst($code) . '\\' . ucfirst($code) . 'Handler',
            'handler_file' => ucfirst($code) . 'Handler.php',
            'templates' => [
                'manage' => 'ext_product_' . $code . '_manage.html.twig',
                'config' => 'ext_product_' . $code . '_config.html.twig',
            ],
            'source' => 'core',
        ], $overrides);

        return json_encode($manifest, JSON_PRETTY_PRINT);
    }

    public function testAssertHasDefinitionsThrowsWhenEmpty(): void
    {
        $emptyDir = sys_get_temp_dir() . '/fossbilling_empty_' . uniqid();
        if (!is_dir($emptyDir)) {
            mkdir($emptyDir, 0o755, true);
        }

        try {
            $registry = new ProductTypeRegistry();
            $registry->setDi($this->getDi());

            $this->expectException(Exception::class);
            $this->expectExceptionMessage($emptyDir);
            $registry->assertHasDefinitions($emptyDir);
        } finally {
            if (is_dir($emptyDir)) {
                rmdir($emptyDir);
            }
        }
    }

    public function testLoadFromFilesystemWithValidManifests(): void
    {
        $productDir = $this->tempDir . '/testproduct';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', $this->createManifest('testproduct'));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $this->assertTrue($registry->has('testproduct'));
        $this->assertCount(1, $registry->getDefinitions());
    }

    public function testLoadFromFilesystemSkipsMissingManifest(): void
    {
        $productDir = $this->tempDir . '/nomanifest';
        mkdir($productDir, 0o755, true);

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $this->assertFalse($registry->has('nomanifest'));
    }

    public function testLoadFromFilesystemHandlesMalformedJson(): void
    {
        $productDir = $this->tempDir . '/badjson';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', '{ invalid json }');

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $errors = $registry->getLoadErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('badjson', $errors[0]['name']);
    }

    public function testLoadFromFilesystemHandlesInvalidManifest(): void
    {
        $productDir = $this->tempDir . '/invalid';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode(['invalid' => 'manifest']));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $errors = $registry->getLoadErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('invalid', $errors[0]['name']);
    }

    public function testRegisterDefinitionValid(): void
    {
        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'handler_file' => 'TestHandler.php',
            'templates' => [
                'manage' => 'ext_product_test_manage.html.twig',
            ],
        ]);

        $this->assertTrue($registry->has('test'));
        $definition = $registry->getDefinition('test');
        $this->assertSame('test', $definition['code']);
        $this->assertSame('Test', $definition['label']);
    }

    public function testRegisterDefinitionRequiresCode(): void
    {
        $registry = new ProductTypeRegistry();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('code');
        $registry->registerDefinition([]);
    }

    public function testRegisterDefinitionRequiresHandlerOrHandlerClass(): void
    {
        $registry = new ProductTypeRegistry();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('handler_class');
        $registry->registerDefinition([
            'code' => 'test',
            'templates' => ['manage' => 'test.html.twig'],
        ]);
    }

    public function testRegisterDefinitionRequiresTemplates(): void
    {
        $registry = new ProductTypeRegistry();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('templates');
        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
        ]);
    }

    public function testRegisterDefinitionDuplicateOverwrites(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'label' => 'First',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'handler_file' => 'TestHandler.php',
            'templates' => ['manage' => 'first.html.twig'],
        ]);

        $registry->registerDefinition([
            'code' => 'test',
            'label' => 'Second',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'handler_file' => 'TestHandler.php',
            'templates' => ['manage' => 'second.html.twig'],
        ]);

        $definition = $registry->getDefinition('test');
        $this->assertSame('Second', $definition['label']);
    }

    public function testGetDefinitionThrowsForUnregisteredType(): void
    {
        $registry = new ProductTypeRegistry();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not registered');
        $registry->getDefinition('nonexistent');
    }

    public function testGetDefinitionIncludesAvailableTypesInError(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'existing',
            'label' => 'Existing',
            'handler_class' => 'FOSSBilling\\ProductType\\Existing\\ExistingHandler',
            'handler_file' => 'ExistingHandler.php',
            'templates' => ['manage' => 'existing.html.twig'],
        ]);

        try {
            $registry->getDefinition('nonexistent');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('is not registered', $message);
            $this->assertStringContainsString('Available types', $message);
        }
    }

    public function testGetHandlerReturnsCachedInstance(): void
    {
        $mockHandler = $this->createMock(Interfaces\ProductTypeHandlerInterface::class);

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler' => $mockHandler,
            'templates' => ['manage' => 'test.html.twig'],
        ]);

        $handler1 = $registry->getHandler('test');
        $handler2 = $registry->getHandler('test');

        $this->assertSame($mockHandler, $handler1);
        $this->assertSame($handler1, $handler2);
    }

    public function testGetHandlerThrowsForMissingClass(): void
    {
        $productDir = $this->tempDir . '/missingclass';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'missingclass',
            'handler_class' => 'NonExistent\\Handler\\Class',
            'handler_file' => 'NonExistent.php',
            'templates' => ['manage' => 'test.html.twig'],
        ]));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('was not found');
        $registry->getHandler('missingclass');
    }

    public function testGetHandlerThrowsForFileOutsideBasePath(): void
    {
        $productDir = $this->tempDir . '/pathcheck';
        mkdir($productDir, 0o755, true);

        $outsideDir = $this->tempDir . '/outside';
        mkdir($outsideDir, 0o755, true);
        file_put_contents($outsideDir . '/EvilHandler.php', '<?php class EvilHandler {}');

        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'pathcheck',
            'handler_class' => 'EvilHandler',
            'handler_file' => '../outside/EvilHandler.php',
            'templates' => ['manage' => 'test.html.twig'],
        ]));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not within allowed directory');
        $registry->getHandler('pathcheck');

        $this->removeDirectory($outsideDir);
    }

    public function testGetHandlerValidatesInterfaceImplementation(): void
    {
        $productDir = $this->tempDir . '/nointerface';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/NoInterfaceHandler.php', '<?php class NoInterfaceHandler {}');
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'nointerface',
            'handler_class' => 'NoInterfaceHandler',
            'handler_file' => 'NoInterfaceHandler.php',
            'templates' => ['manage' => 'test.html.twig'],
        ]));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ProductTypeHandlerInterface');
        $registry->getHandler('nointerface');
    }

    public function testGetTemplateReturnsConfiguredTemplate(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'handler_file' => 'TestHandler.php',
            'templates' => [
                'manage' => 'custom_manage.html.twig',
                'config' => 'custom_config.html.twig',
            ],
        ]);

        $this->assertSame('custom_manage.html.twig', $registry->getTemplate('test', 'manage'));
        $this->assertSame('custom_config.html.twig', $registry->getTemplate('test', 'config'));
    }

    public function testGetTemplateThrowsForMissingTemplate(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'handler_file' => 'TestHandler.php',
            'templates' => ['manage' => 'manage.html.twig'],
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not define template');
        $registry->getTemplate('test', 'nonexistent');
    }

    public function testGetTemplateThrowsForUnregisteredProductType(): void
    {
        $registry = new ProductTypeRegistry();

        $this->expectException(Exception::class);
        $registry->getTemplate('nonexistent', 'manage');
    }

    public function testGetPairsReturnsCodeLabelMapping(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'producta',
            'label' => 'Product A',
            'handler_class' => 'FOSSBilling\\ProductType\\Producta\\ProductaHandler',
            'handler_file' => 'ProductaHandler.php',
            'templates' => ['manage' => 'a.html.twig'],
        ]);

        $registry->registerDefinition([
            'code' => 'productb',
            'handler_class' => 'FOSSBilling\\ProductType\\Productb\\ProductbHandler',
            'handler_file' => 'ProductbHandler.php',
            'templates' => ['manage' => 'b.html.twig'],
        ]);

        $pairs = $registry->getPairs();
        $this->assertSame('Product A', $pairs['producta']);
        $this->assertSame('Productb', $pairs['productb']);
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'handler_file' => 'TestHandler.php',
            'templates' => ['manage' => 'test.html.twig'],
        ]);

        $this->assertTrue($registry->has('test'));
        $this->assertTrue($registry->has('TEST'));
        $this->assertTrue($registry->has('Test'));
    }

    public function testHasReturnsFalseForUnregistered(): void
    {
        $registry = new ProductTypeRegistry();
        $this->assertFalse($registry->has('nonexistent'));
    }

    public function testGetPermissionKeyFormat(): void
    {
        $registry = new ProductTypeRegistry();
        $this->assertSame('product_custom', $registry->getPermissionKey('custom'));
    }

    public function testGetLoadErrorsIncludesManifestErrors(): void
    {
        $productDir = $this->tempDir . '/errorproduct';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', '{bad json}');

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $errors = $registry->getLoadErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('errorproduct', $errors[0]['name']);
    }

    public function testAssertHasDefinitionsPassesWhenTypesLoaded(): void
    {
        $productDir = $this->tempDir . '/withtypes';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', $this->createManifest('withtypes'));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $registry->assertHasDefinitions($this->tempDir);
        $this->assertTrue(true);
    }

    public function testInvokeProductTypeActionCallsHandlerMethod(): void
    {
        $mockHandler = $this->createMock(Interfaces\ProductTypeHandlerInterface::class);
        $mockHandler->expects($this->once())
            ->method('activate')
            ->willReturn('activated');

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler' => $mockHandler,
            'templates' => ['manage' => 'test.html.twig'],
        ]);

        $order = new \Model_ClientOrder();
        $result = $registry->invokeProductTypeAction('test', 'activate', $order);
        $this->assertSame('activated', $result);
    }

    public function testInvokeProductTypeActionThrowsForMissingMethod(): void
    {
        $mockHandler = $this->createMock(Interfaces\ProductTypeHandlerInterface::class);
        $mockHandler->method('activate')->willReturn('activated');

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler' => $mockHandler,
            'templates' => ['manage' => 'test.html.twig'],
        ]);

        $order = new \Model_ClientOrder();

        $this->expectException(ProductTypeActionNotSupportedException::class);
        $this->expectExceptionMessage('does not support action');
        $registry->invokeProductTypeAction('test', 'nonexistent', $order);
    }

    public function testHandlerReceivesDiInjection(): void
    {
        $mockHandler = $this->createMock(Interfaces\ProductTypeHandlerInterface::class);
        $mockHandler->expects($this->once())
            ->method('setDi')
            ->with($this->isInstanceOf(\Pimple\Container::class));

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler' => $mockHandler,
            'templates' => ['manage' => 'test.html.twig'],
        ]);

        $registry->getHandler('test');
    }
}

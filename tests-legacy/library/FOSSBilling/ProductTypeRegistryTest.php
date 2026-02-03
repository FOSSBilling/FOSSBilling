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
            'label' => ucfirst($code),
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
        file_put_contents($productDir . '/manifest.json', '{ invalid json }');

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
        ]);
    }

    public function testRegisterDefinitionDoesNotRequireTemplates(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
        ]);

        $this->assertTrue($registry->has('test'));
        $definition = $registry->getDefinition('test');
        $this->assertSame('test', $definition['code']);
        $this->assertSame('Test', $definition['label']);
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
        ]);

        $registry->registerDefinition([
            'code' => 'test',
            'label' => 'Second',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
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
        $productDir = $this->tempDir . '/cached';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'cached',
            'handler_class' => 'FOSSBilling\\ProductType\\ApiKey\\ApiKeyHandler',
        ]));

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);
        $registry->loadFromFilesystem($this->tempDir);

        $handler1 = $registry->getHandler('cached');
        $handler2 = $registry->getHandler('cached');

        $this->assertSame($handler1, $handler2);
    }

    public function testGetHandlerThrowsForMissingClass(): void
    {
        $productDir = $this->tempDir . '/missingclass';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'missingclass',
            'handler_class' => 'NonExistent\\Handler\\Class',
        ]));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('was not found');
        $registry->getHandler('missingclass');
    }

    public function testGetHandlerValidatesInterfaceImplementation(): void
    {
        $productDir = $this->tempDir . '/nointerface';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'nointerface',
            'handler_class' => 'FOSSBilling\\ProductType\\ApiKey\\ApiKeyHandler',
        ]));

        $registry = new ProductTypeRegistry();
        $registry->setDi($this->getDi());
        $registry->loadFromFilesystem($this->tempDir);

        $handler = $registry->getHandler('nointerface');
        $this->assertInstanceOf(Interfaces\ProductTypeHandlerInterface::class, $handler);
    }

    public function testGetTemplateReturnsConventionNameForMissingTemplate(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
        ]);

        $this->assertSame('ext_product_test_nonexistent.html.twig', $registry->getTemplate('test', 'nonexistent'));
    }

    public function testGetTemplateReturnsCustomTemplateWhenConfigured(): void
    {
        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);

        $registry->registerDefinition([
            'code' => 'test',
            'handler_class' => 'FOSSBilling\\ProductType\\Test\\TestHandler',
            'templates' => [
                'manage' => 'custom_manage.html.twig',
                'config' => 'custom_config.html.twig',
            ],
        ]);

        $this->assertSame('custom_manage.html.twig', $registry->getTemplate('test', 'manage'));
        $this->assertSame('custom_config.html.twig', $registry->getTemplate('test', 'config'));
        $this->assertSame('ext_product_test_order.html.twig', $registry->getTemplate('test', 'order'));
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
        ]);

        $registry->registerDefinition([
            'code' => 'productb',
            'handler_class' => 'FOSSBilling\\ProductType\\Productb\\ProductbHandler',
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
        $productDir = $this->tempDir . '/actiontest';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'actiontest',
            'handler_class' => 'FOSSBilling\\ProductType\\Download\\DownloadHandler',
        ]));

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);
        $registry->loadFromFilesystem($this->tempDir);

        $handler = $registry->getHandler('actiontest');
        $this->assertInstanceOf(Interfaces\ProductTypeHandlerInterface::class, $handler);

        $order = new \Model_ClientOrder();
        try {
            $registry->invokeProductTypeAction('actiontest', 'create', $order);
        } catch (Exception $e) {
            $this->assertStringContainsString('config is missing', $e->getMessage());
        }
    }

    public function testInvokeProductTypeActionThrowsForMissingMethod(): void
    {
        $productDir = $this->tempDir . '/nomethod';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'nomethod',
            'handler_class' => 'FOSSBilling\\ProductType\\Download\\DownloadHandler',
        ]));

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);
        $registry->loadFromFilesystem($this->tempDir);

        $order = new \Model_ClientOrder();

        $this->expectException(ProductTypeActionNotSupportedException::class);
        $this->expectExceptionMessage('does not support action');
        $registry->invokeProductTypeAction('nomethod', 'nonexistent', $order);
    }

    public function testHandlerReceivesDiInjection(): void
    {
        $productDir = $this->tempDir . '/diinjection';
        mkdir($productDir, 0o755, true);
        file_put_contents($productDir . '/manifest.json', json_encode([
            'code' => 'diinjection',
            'handler_class' => 'FOSSBilling\\ProductType\\Custom\\CustomHandler',
        ]));

        $registry = new ProductTypeRegistry();
        $di = $this->getDi();
        $registry->setDi($di);
        $registry->loadFromFilesystem($this->tempDir);

        $handler = $registry->getHandler('diinjection');
        $this->assertInstanceOf(Interfaces\ProductTypeHandlerInterface::class, $handler);
    }
}

<?php

declare(strict_types=1);

namespace Box\Mod\Formbuilder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetFormFieldsTypes(): void
    {
        $expected = [
            'text' => 'Text input',
            'url' => 'URL input',
            'select' => 'Dropdown',
            'radio' => 'Radio select',
            'checkbox' => 'Checkbox',
            'textarea' => 'Text area',
        ];

        $this->assertSame($expected, $this->service->getFormFieldsTypes());
    }

    public static function typeFieldValidationData(): array
    {
        return [
            ['select', true],
            ['custom', false],
            ['url', true],
        ];
    }

    #[DataProvider('typeFieldValidationData')]
    public function testIsValidFieldType(string $type, bool $expected): void
    {
        $this->assertSame($expected, $this->service->isValidFieldType($type));
    }

    public static function urlValidationData(): array
    {
        return [
            ['', true],
            ['https://example.com', true],
            ['http://example.org', true],
            ['https://subdomain.example.co.uk', true],
            ['example', false],
            ['example.com', false],
            ['https://example', false],
            ['not-a-url', false],
            ['ftp://files.example.com', true],
        ];
    }

    #[DataProvider('urlValidationData')]
    public function testValidateUrlField(string $url, bool $expected): void
    {
        $this->assertSame($expected, $this->service->validateUrlField($url));
    }

    public static function isArrayUniqueData(): array
    {
        return [
            [['sameValue', 'sameValue'], false],
            [['sameValue', 'DiffValue'], true],
            [[], true],
        ];
    }

    #[DataProvider('isArrayUniqueData')]
    public function testIsArrayUnique(array $data, bool $expected): void
    {
        $this->assertSame($expected, $this->service->isArrayUnique($data));
    }

    public function testAddNewFormAndGetForm(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm([
            'name' => 'Support Form',
            'type' => 'default',
            'show_title' => '1',
        ]);

        $form = $this->service->getForm($formId);

        $this->assertSame('Support Form', $form['name']);
        $this->assertSame(['type' => 'default', 'show_title' => '1'], $form['style']);
        $this->assertSame([], $form['fields']);
    }

    public function testAddNewFieldAndGetField(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Signup Form']);
        $fieldId = $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'select',
        ]);

        $field = $this->service->getField($fieldId);

        $this->assertSame($formId, (int) $field['form_id']);
        $this->assertSame('select', $field['type']);
        $this->assertIsObject($field['options']);
    }

    public function testUpdateFieldUpdatesStructuredOptions(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Order Form']);
        $fieldId = $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'select',
            'name' => 'server_location',
        ]);

        $updatedId = $this->service->updateField([
            'id' => $fieldId,
            'name' => 'server_location',
            'label' => 'Server location',
            'type' => 'select',
            'values' => ['eu', 'us'],
            'labels' => ['Europe', 'United States'],
            'default_value' => 'eu',
        ]);

        $field = $this->service->getField($updatedId);

        $this->assertSame($fieldId, $updatedId);
        $this->assertSame('Server location', $field['label']);
        $this->assertEquals((object) ['Europe' => 'eu', 'United States' => 'us'], $field['options']);
    }

    public function testUpdateFieldRejectsDuplicateFieldNames(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Order Form']);
        $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'text',
            'name' => 'hostname',
        ]);
        $fieldId = $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'text',
            'name' => 'username',
        ]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(7628);

        $this->service->updateField([
            'id' => $fieldId,
            'name' => 'hostname',
            'type' => 'text',
        ]);
    }

    public function testUpdateFieldRejectsDuplicateSelectValues(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Order Form']);
        $fieldId = $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'select',
            'name' => 'server_location',
        ]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(1597);

        $this->service->updateField([
            'id' => $fieldId,
            'name' => 'server_location',
            'type' => 'select',
            'values' => ['eu', 'eu'],
            'labels' => ['Europe', 'Duplicate'],
        ]);
    }

    public function testRemoveFormClearsRelatedRows(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Provisioning Form']);
        $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'text',
            'name' => 'hostname',
        ]);

        $dbal->insert('product', ['id' => 5, 'form_id' => $formId]);
        $dbal->insert('client_order', ['id' => 8, 'form_id' => $formId]);

        $this->assertTrue($this->service->removeForm($formId));
        $this->assertSame(0, (int) $dbal->executeQuery('SELECT COUNT(*) FROM form WHERE id = ?', [$formId])->fetchOne());
        $this->assertSame(0, (int) $dbal->executeQuery('SELECT COUNT(*) FROM form_field WHERE form_id = ?', [$formId])->fetchOne());
        $this->assertNull($dbal->executeQuery('SELECT form_id FROM product WHERE id = 5')->fetchOne());
        $this->assertNull($dbal->executeQuery('SELECT form_id FROM client_order WHERE id = 8')->fetchOne());
    }

    public function testDuplicateFormCopiesFields(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Original']);
        $this->service->addNewField([
            'form_id' => $formId,
            'type' => 'text',
            'name' => 'hostname',
        ]);

        $duplicateId = $this->service->duplicateForm([
            'form_id' => $formId,
            'name' => 'Copy',
        ]);

        $this->assertSame('Copy', $this->service->getForm($duplicateId)['name']);
        $this->assertCount(1, $this->service->getFormFields($duplicateId));
    }

    public function testUpdateFormSettings(): void
    {
        $dbal = $this->createDbalConnection();
        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $formId = $this->service->addNewForm(['name' => 'Original']);

        $this->assertTrue($this->service->updateFormSettings([
            'form_id' => $formId,
            'form_name' => 'Renamed',
            'type' => 'default',
            'show_title' => '1',
        ]));

        $form = $this->service->getForm($formId);
        $this->assertSame('Renamed', $form['name']);
        $this->assertSame(['type' => 'default', 'show_title' => '1'], $form['style']);
    }

    private function createDi(Connection $dbal): \Pimple\Container
    {
        $di = $this->getDi();
        $di['dbal'] = $dbal;
        $di['logger'] = new \Box_Log();

        $validator = $this->getMockBuilder(\FOSSBilling\Validate::class)
            ->disableOriginalConstructor()
            ->getMock();
        $validator->method('checkRequiredParamsForArray');
        $di['validator'] = $validator;

        return $di;
    }

    private function createDbalConnection(): Connection
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $connection->executeStatement('CREATE TABLE form (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, style TEXT, created_at TEXT, updated_at TEXT)');
        $connection->executeStatement('CREATE TABLE form_field (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER,
            name TEXT,
            label TEXT,
            hide_label INTEGER,
            description TEXT,
            type TEXT,
            default_value TEXT,
            required INTEGER,
            hidden INTEGER,
            readonly INTEGER,
            is_unique INTEGER,
            prefix TEXT,
            suffix TEXT,
            options TEXT,
            show_initial TEXT,
            show_middle TEXT,
            show_prefix TEXT,
            show_suffix TEXT,
            text_size INTEGER,
            created_at TEXT,
            updated_at TEXT
        )');
        $connection->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, form_id INTEGER)');
        $connection->executeStatement('CREATE TABLE client_order (id INTEGER PRIMARY KEY, form_id INTEGER)');

        return $connection;
    }
}

<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Formbuilder;

use Doctrine\DBAL\Connection;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    private function getDbal(): Connection
    {
        return $this->di['dbal'];
    }

    public function getFormFieldsTypes(): array
    {
        return [
            'text' => 'Text input',
            'url' => 'URL input',
            'select' => 'Dropdown',
            'radio' => 'Radio select',
            'checkbox' => 'Checkbox',
            'textarea' => 'Text area',
        ];
    }

    public function isValidFieldType(string $type): bool
    {
        return array_key_exists($type, $this->getFormFieldsTypes());
    }

    /**
     * @deprecated Use isValidFieldType() instead.
     */
    public function typeValidation($type): bool
    {
        return $this->isValidFieldType((string) $type);
    }

    public function validateUrlField(string $value): bool
    {
        if (empty($value)) {
            return true;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if ($host === null) {
            $host = $value;
        }

        return (bool) preg_match('/\.[a-zA-Z]{2,}$/', $host);
    }

    public function isArrayUnique($array): bool
    {
        $unique = array_unique($array);

        return (is_countable($array) ? count($array) : 0) === count($unique);
    }

    public function addNewForm($data): int
    {
        $style = [
            'type' => $data['type'] ?? 'horizontal',
            'show_title' => $data['show_title'] ?? '0',
        ];

        $this->getDbal()->insert('form', [
            'name' => $data['name'],
            'style' => json_encode($style, JSON_FORCE_OBJECT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $formId = (int) $this->getDbal()->lastInsertId();
        $this->di['logger']->info('Created new form %s with id %s', $data['name'], $formId);

        return $formId;
    }

    public function addNewField($field): int
    {
        $fieldNumber = $this->getFormFieldsCount($field['form_id']) + 1;
        $types = $this->getFormFieldsTypes();
        $type = $field['type'];

        $label = $field['label'] ?? $types[$type] . ' ' . $fieldNumber;
        $name = $field['name'] ?? $this->slugify('new_' . $type) . '_' . $fieldNumber;

        if ($type == 'select' || $type == 'checkbox' || $type == 'radio') {
            $field['options'] = '{"First option":"1", "Second option": "2", "Third option":"3"}';
        }

        if ($field['type'] == 'textarea' && !isset($field['options'])) {
            $field['options'] = '{"height":"100", "width": "300"}';
        }

        if (isset($field['default_value'])) {
            $field['default_value'] = is_array($field['default_value']) ? json_encode($field['default_value'], JSON_FORCE_OBJECT) : $field['default_value'];
        }

        if (isset($field['options']) && is_array($field['options'])) {
            $field['options'] = json_encode($field['options'], JSON_FORCE_OBJECT);
        }

        $this->getDbal()->insert('form_field', [
            'form_id' => $field['form_id'],
            'name' => $name,
            'label' => $label,
            'hide_label' => $field['hide_label'] ?? null,
            'description' => $field['description'] ?? null,
            'type' => $field['type'],
            'default_value' => $field['default_value'] ?? null,
            'required' => $field['required'] ?? null,
            'hidden' => $field['hidden'] ?? null,
            'readonly' => $field['readonly'] ?? null,
            'options' => $field['options'] ?? null,
            'prefix' => $field['prefix'] ?? null,
            'suffix' => $field['suffix'] ?? null,
            'show_initial' => $field['show_initial'] ?? null,
            'show_middle' => $field['show_middle'] ?? null,
            'show_prefix' => $field['show_prefix'] ?? null,
            'show_suffix' => $field['show_suffix'] ?? null,
            'text_size' => $field['text_size'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $fieldId = (int) $this->getDbal()->lastInsertId();
        $this->di['logger']->info('Added new field %s to form %s', $fieldId, $field['form_id']);

        return $fieldId;
    }

    private function slugify($text): string
    {
        $text = preg_replace('~[^\\pL\d]+~u', '_', (string) $text);
        $text = trim((string) $text, '_');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^\-\w]+~', '', $text);

        if (is_numeric(substr((string) $text, 0, 1))) {
            throw new \FOSSBilling\InformationException('Field name cannot start with number.', null, 1649);
        }

        if (empty($text)) {
            throw new \FOSSBilling\InformationException('Field name cannot be empty. Please make sure it is not empty and does not contain special characters.', null, 3502);
        }

        return $text;
    }

    public function updateField(array $field): int
    {
        $fieldId = $field['id'];
        $label = $field['label'] ?? 'New field';
        $name = $this->slugify($field['name']);

        $currentField = $this->getField($fieldId);
        $field['form_id'] = $currentField['form_id'] ?? null;

        if ($this->formFieldNameExists([
            'form_id' => $field['form_id'],
            'field_name' => $field['name'],
            'field_id' => $fieldId,
        ])) {
            throw new \FOSSBilling\InformationException('Unfortunately field with this name exists in this form already. Form must have different field names.', null, 7628);
        }

        $field['options'] = isset($field['options']) ? json_encode($field['options']) : '';
        if (isset($field['default_value'])) {
            $field['default_value'] = is_array($field['default_value']) ? json_encode($field['default_value'], JSON_FORCE_OBJECT) : $field['default_value'];
        }

        if (isset($field['type'])) {
            if ($field['type'] == 'checkbox' || $field['type'] == 'radio' || $field['type'] == 'select') {
                if (!$this->isArrayUnique(array_filter($field['values'], fn ($v): bool => strlen((string) $v) > 0))) {
                    throw new \FOSSBilling\InformationException(ucfirst($field['type']) . ' values must be unique', null, 1597);
                }

                if (!$this->isArrayUnique(array_filter($field['labels'], fn ($v): bool => strlen((string) $v) > 0))) {
                    throw new \FOSSBilling\InformationException(ucfirst($field['type']) . ' labels must be unique', null, 1598);
                }

                $field['options'] = array_combine($field['labels'], $field['values']);
                $field['options'] = array_filter($field['options'], fn ($v): bool => strlen((string) $v) > 0);
                $field['options'] = json_encode($field['options'], JSON_FORCE_OBJECT);
            }

            if ($field['type'] == 'textarea') {
                if ((is_countable($field['textarea_size']) ? count($field['textarea_size']) : 0) != count(array_filter($field['textarea_size'], is_numeric(...)))) {
                    throw new \FOSSBilling\InformationException('Textarea size options must be integer values', null, 3510);
                }

                $field['options'] = array_combine($field['textarea_option'], $field['textarea_size']);
                $field['options'] = json_encode($field['options'], JSON_FORCE_OBJECT);
            }
        }

        $this->getDbal()->update('form_field', [
            'name' => $name,
            'label' => $label,
            'hide_label' => $field['hide_label'] ?? null,
            'description' => $field['description'] ?? null,
            'default_value' => $field['default_value'] ?? null,
            'required' => $field['required'] ?? null,
            'hidden' => $field['hidden'] ?? null,
            'readonly' => $field['readonly'] ?? null,
            'options' => $field['options'],
            'prefix' => $field['prefix'] ?? null,
            'suffix' => $field['suffix'] ?? null,
            'show_initial' => $field['show_initial'] ?? null,
            'show_middle' => $field['show_middle'] ?? null,
            'show_prefix' => $field['show_prefix'] ?? null,
            'show_suffix' => $field['show_suffix'] ?? null,
            'text_size' => $field['text_size'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $fieldId]);

        $this->di['logger']->info('Updated custom form %s', $fieldId);

        return $fieldId;
    }

    public function getForm(int $formId): array
    {
        $result = $this->getDbal()->executeQuery(
            'SELECT * FROM form WHERE id = ?',
            [$formId]
        )->fetchAssociative();

        if ($result === false) {
            throw new \FOSSBilling\Exception('Form was not found');
        }

        $result['style'] = json_decode($result['style'] ?? '', true);
        $result['fields'] = $this->fieldsJsonDecode($this->getFormFields($result['id']));

        return $result;
    }

    public function getFormFields($formId)
    {
        return $this->getDbal()->executeQuery(
            'SELECT * FROM form_field WHERE form_id = ? ORDER BY id ASC',
            [$formId]
        )->fetchAllAssociative();
    }

    private function fieldsJsonDecode($fields)
    {
        foreach ($fields as $key => $row) {
            $fields[$key]['options'] = json_decode($row['options'] ?? '', true) ?: [];

            if (!empty($row['default_value'])) {
                $fields[$key]['default_value'] = json_decode($row['default_value'] ?? '', true) ?: $row['default_value'];
            } else {
                $fields[$key]['default_value'] = '';
            }
        }

        return $fields;
    }

    public function getFormFieldsCount($form_id): int
    {
        return (int) $this->getDbal()->executeQuery(
            'SELECT COUNT(*) FROM form_field WHERE form_id = ?',
            [$form_id]
        )->fetchOne();
    }

    /**
     * @return mixed[]
     */
    public function getFormPairs(): array
    {
        $pairs = [];
        $rows = $this->getDbal()->executeQuery('SELECT id, name FROM form')->fetchAllAssociative();
        foreach ($rows as $row) {
            $pairs[$row['id']] = $row['name'];
        }

        return $pairs;
    }

    public function getField($fieldId)
    {
        $result = $this->getDbal()->executeQuery(
            'SELECT * FROM form_field WHERE id = ?',
            [$fieldId]
        )->fetchAssociative();

        if ($result === false) {
            throw new \FOSSBilling\Exception('Field was not found');
        }

        $required = [
            'id' => 'Field was not found',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $result, null, 2575);

        if (str_starts_with((string) $result['options'], '{') || str_starts_with((string) $result['options'], '[')) {
            $result['options'] = json_decode($result['options'] ?? '');
        }

        return $result;
    }

    public function removeForm($form_id): bool
    {
        $this->getDbal()->executeStatement('DELETE FROM form_field WHERE form_id = ?', [$form_id]);
        $this->getDbal()->executeStatement('DELETE FROM form WHERE id = ?', [$form_id]);
        $this->getDbal()->executeStatement('UPDATE product SET form_id = NULL WHERE form_id = ?', [$form_id]);
        $this->getDbal()->executeStatement('UPDATE client_order SET form_id = NULL WHERE form_id = ?', [$form_id]);

        $this->di['logger']->info('Deleted custom form %s', $form_id);

        return true;
    }

    public function removeField($data): bool
    {
        $deleted = $this->getDbal()->executeStatement('DELETE FROM form_field WHERE id = ?', [$data['id']]);
        if ($deleted === 0) {
            throw new \FOSSBilling\Exception('Field was not found');
        }

        $this->di['logger']->info('Deleted custom field %s', $data['id']);

        return true;
    }

    public function formFieldNameExists($data): bool
    {
        return (int) $this->getDbal()->executeQuery(
            'SELECT COUNT(*) FROM form_field WHERE form_id = ? AND name = ? AND id != ?',
            [$data['form_id'], $data['field_name'], $data['field_id']]
        )->fetchOne() > 0;
    }

    public function getForms()
    {
        return $this->getDbal()->executeQuery(
            'SELECT f.id, f.name, COUNT(p.id) AS product_count, COUNT(co.id) AS order_count
            FROM form f
            LEFT JOIN product p ON f.id = p.form_id
            LEFT JOIN client_order co ON f.id = co.form_id
            GROUP BY f.id'
        )->fetchAllAssociative();
    }

    public function duplicateForm($data): int
    {
        $fields = $this->getFormFields($data['form_id']);
        $newFormId = $this->addNewForm([
            'name' => $data['name'],
        ]);

        foreach ($fields as $fieldData) {
            $fieldData['form_id'] = $newFormId;
            $this->addNewField($fieldData);
        }

        $this->di['logger']->info('Copied form with id %s to new form %s with id %s', $data['form_id'], $data['name'], $newFormId);

        return $newFormId;
    }

    public function updateFormSettings($data): bool
    {
        $style = [
            'type' => $data['type'],
            'show_title' => $data['show_title'] ?? '1',
        ];

        $this->getDbal()->update('form', [
            'name' => $data['form_name'],
            'style' => json_encode($style, JSON_FORCE_OBJECT),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $data['form_id']]);

        $this->di['logger']->info('Updated form %s name to %s', $data['form_id'], $data['form_name']);
        $this->di['logger']->info('Updated form %s type to %s', $data['form_id'], $data['type']);

        return true;
    }
}

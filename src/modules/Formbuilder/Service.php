<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Formbuilder;

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

    public function getFormFieldsTypes()
    {
        return [
            'text' => 'Text input',
            'select' => 'Dropdown',
            'radio' => 'Radio select',
            'checkbox' => 'Checkbox',
            'textarea' => 'Text area',
        ];
    }

    public function typeValidation($type)
    {
        return array_key_exists($type, $this->getFormFieldsTypes());
    }

    public function isArrayUnique($data)
    {
        $unique = array_unique($data);

        return (is_countable($data) ? count($data) : 0) === count($unique);
    }

    public function addNewForm($data)
    {
        $data['style']['type'] = $data['type'] ?? 'horizontal';
        $data['style']['show_title'] = $data['show_title'] ?? '0';
        $data['style'] = json_encode($data['style'], JSON_FORCE_OBJECT);

        $bean = $this->di['db']->dispense('Form');
        $bean->name = $data['name'];
        $bean->style = $data['style'];
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = date('Y-m-d H:i:s');

        $form_id = $this->di['db']->store($bean);
        $this->di['logger']->info('Created new form %s with id %s', $data['name'], $form_id);

        return $form_id;
    }

    public function addNewField($field) // TODO server-side required check
    {
        $field_number = (int) $this->getFormFieldsCount($field['form_id']) + 1;

        $formId = $field['form_id'];
        $types = $this->getFormFieldsTypes();
        $type = $field['type'];

        $label = $field['label'] ?? $types[$type] . ' ' . $field_number;
        $name = $field['name'] ?? $this->slugify('new_' . $type) . '_' . $field_number;

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
        $bean = $this->di['db']->dispense('FormField');
        $bean->form_id = $formId;
        $bean->name = $name;
        $bean->label = $label;
        $bean->hide_label = $field['hide_label'] ?? null;
        $bean->description = $field['description'] ?? null;
        $bean->type = $field['type'];
        $bean->default_value = $field['default_value'] ?? null;
        $bean->required = $field['required'] ?? null;
        $bean->hidden = $field['hidden'] ?? null;
        $bean->readonly = $field['readonly'] ?? null;
        $bean->options = $field['options'] ?? null;
        $bean->prefix = $field['prefix'] ?? null;
        $bean->suffix = $field['suffix'] ?? null;
        $bean->show_initial = $field['show_initial'] ?? null;
        $bean->show_middle = $field['show_middle'] ?? null;
        $bean->show_prefix = $field['show_prefix'] ?? null;
        $bean->show_suffix = $field['show_suffix'] ?? null;
        $bean->text_size = $field['text_size'] ?? null;
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = date('Y-m-d H:i:s');

        $fieldId = $this->di['db']->store($bean);
        $this->di['logger']->info('Added new field %s to form %s', $fieldId, $field['form_id']);

        return $fieldId;
    }

    private function slugify($text)
    {
        // replace non letter or digits by _
        $text = preg_replace('~[^\\pL\d]+~u', '_', $text);

        // trim
        $text = trim($text, '_');

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^\-\w]+~', '', $text);

        if (is_numeric(substr($text, 0, 1))) {
            throw new \FOSSBilling\InformationException('Field name cannot start with number.', null, 1649);
        }

        if (empty($text)) {
            throw new \FOSSBilling\InformationException('Field name cannot be empty. Please make sure it is not empty and does not contain special characters.', null, 3502);
        }

        return $text;
    }

    public function updateField($field)
    {
        $fieldId = $field['id'];

        $label = $field['label'] ?? 'New field';
        $name = $this->slugify($field['name']);

        $get_field = $this->getField($field['id']);
        $field['form_id'] = $get_field['form_id'];

        if ($this->formFieldNameExists(['form_id' => $field['form_id'], 'field_name' => $field['name'], 'field_id' => $fieldId])) {
            throw new \FOSSBilling\InformationException('Unfortunately field with this name exists in this form already. Form must have different field names.', null, 7628);
        }

        $field['options'] = isset($field['options']) ? json_encode($field['options']) : '';
        if (isset($field['default_value'])) {
            $field['default_value'] = is_array($field['default_value']) ? json_encode($field['default_value'], JSON_FORCE_OBJECT) : $field['default_value'];
        }

        if (isset($field['type'])) {
            if ($field['type'] == 'checkbox' || $field['type'] == 'radio' || $field['type'] == 'select') {
                if (!$this->isArrayUnique(array_filter($field['values'], 'strlen'))) {
                    throw new \FOSSBilling\InformationException(ucfirst($field['type']) . ' values must be unique', null, 1597);
                }
                if (!$this->isArrayUnique(array_filter($field['labels'], 'strlen'))) {
                    throw new \FOSSBilling\InformationException(ucfirst($field['type']) . ' labels must be unique', null, 1598);
                }
                $field['options'] = array_combine($field['labels'], $field['values']);
                $field['options'] = array_filter($field['options'], 'strlen');
                $field['options'] = json_encode($field['options'], JSON_FORCE_OBJECT);
            }
            if ($field['type'] == 'textarea') {
                if ((is_countable($field['textarea_size']) ? count($field['textarea_size']) : 0) != count(array_filter($field['textarea_size'], 'is_numeric'))) {
                    throw new \FOSSBilling\InformationException('Textarea size options must be integer values', null, 3510);
                }
                $field['options'] = array_combine($field['textarea_option'], $field['textarea_size']);
                $field['options'] = json_encode($field['options'], JSON_FORCE_OBJECT);
            }
        }

        $bean = $this->di['db']->dispense('FormField');
        $bean->id = $fieldId;
        $bean->name = $name;
        $bean->label = $label;
        $bean->hide_label = $field['hide_label'] ?? null;
        $bean->description = $field['description'] ?? null;
        $bean->default_value = $field['default_value'] ?? null;
        $bean->required = $field['required'] ?? null;
        $bean->hidden = $field['hidden'] ?? null;
        $bean->readonly = $field['readonly'] ?? null;
        $bean->options = $field['options'];
        $bean->prefix = $field['prefix'] ?? null;
        $bean->suffix = $field['suffix'] ?? null;
        $bean->show_initial = $field['show_initial'] ?? null;
        $bean->show_middle = $field['show_middle'] ?? null;
        $bean->show_prefix = $field['show_prefix'] ?? null;
        $bean->show_suffix = $field['show_suffix'] ?? null;
        $bean->text_size = $field['text_size'] ?? null;
        $bean->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($bean);
        $this->di['logger']->info('Updated custom form %s', $fieldId);

        return $fieldId;
    }

    public function getForm($formId)
    {
        $formModel = $this->di['db']->getExistingModelById('Form', $formId);
        $result = $this->di['db']->toArray($formModel);

        $result['style'] = json_decode($result['style'], true);
        $result['fields'] = $this->getFormFields($result['id']);
        $result['fields'] = $this->fieldsJsonDecode($result['fields']);

        return $result;
    }

    public function getFormFields($formId)
    {
        $sql = '
        SELECT *
        FROM form_field
        WHERE form_id = :form_id
        ORDER BY ID asc
        ';

        return $this->di['db']->getAll($sql, [':form_id' => $formId]);
    }

    private function fieldsJsonDecode($fields)
    {
        foreach ($fields as $key => $r) {
            if (!empty($r['options'])) {
                $fields[$key]['options'] = json_decode($r['options'], true);
            } else {
                $fields[$key]['options'] = [];
            }

            if (!empty($r['default_value'])) {
                $fields[$key]['default_value'] = (json_decode($r['default_value'])) ? (json_decode($r['default_value'], true)) : $r['default_value'];
            } else {
                $fields[$key]['default_value'] = '';
            }
        }

        return $fields;
    }

    public function getFormFieldsCount($form_id)
    {
        $sql = '
        SELECT COUNT(*)
        FROM form_field
        WHERE form_id = :form_id
        ';

        return $this->di['db']->getCell($sql, [':form_id' => $form_id]);
    }

    public function getFormPairs()
    {
        $sql = '
            SELECT id, name
            FROM form
        ';

        return $this->di['db']->getAssoc($sql);
    }

    public function getField($fieldId)
    {
        $field = $this->di['db']->getExistingModelById('FormField', $fieldId, 'Field was not found');

        $result = $this->di['db']->toArray($field);

        $required = [
            'id' => 'Field was not found',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $result, null, 2575);

        if (str_starts_with($result['options'], '{') || str_starts_with($result['options'], '[')) {
            $result['options'] = json_decode($result['options']);
        }

        return $result;
    }

    public function removeForm($form_id)
    {
        $sql = 'DELETE
            FROM form_field
            WHERE  form_id = ?
        ';
        $this->di['db']->exec($sql, [$form_id]);

        $sql2 = 'DELETE
        FROM form
        WHERE id = ?';
        $this->di['db']->exec($sql2, [$form_id]);

        $sql3 = 'UPDATE product
        SET form_id = NULL
        WHERE form_id = ?
        ';
        $this->di['db']->exec($sql3, [$form_id]);

        $sql4 = 'UPDATE client_order
        SET form_id = NULL
        WHERE form_id = :form_id
        ';
        $this->di['db']->exec($sql4, [':form_id' => $form_id]);

        $this->di['logger']->info('Deleted custom form %s', $form_id);

        return true;
    }

    public function removeField($data)
    {
        $fieldModel = $this->di['db']->getExistingModelById('FormField', $data['id'], 'Field was not found');
        $this->di['db']->trash($fieldModel);
        $this->di['logger']->info('Deleted custom field %s', $data['id']);

        return true;
    }

    public function formFieldNameExists($data)
    {
        $form_id = $data['form_id'];
        $field_name = $data['field_name'];
        $field_id = $data['field_id'];
        $sql = '
        SELECT COUNT( * )
        FROM  `form_field`
        WHERE form_id = :form_id
        AND name =  :field_name
        AND id != :field_id
        ';

        $result = $this->di['db']->findOne('FormField', 'form_id = ? and name = ? and id != ?', [$form_id, $field_name, $field_id]);

        return ($result) ? true : false;
    }

    public function getForms()
    {
        $sql = '
        SELECT f.id, f.name, COUNT( p.id ) as product_count, COUNT( co.id ) as order_count
        FROM  `form` f
        LEFT JOIN product p ON (f.id = p.form_id)
        LEFT JOIN client_order co ON (f.id = co.form_id)
        GROUP BY f.id
        ';

        return $this->di['db']->getAll($sql);
    }

    public function duplicateForm($data)
    {
        $fields = $this->getFormFields($data['form_id']);
        $new_form_id = $this->addNewForm([
            'name' => $data['name'],
        ]);

        if (isset($fields) && is_array($fields)) {
            foreach ($fields as $field_data) {
                $field_data['form_id'] = $new_form_id;
                $this->addNewField($field_data);
            }
        }
        $this->di['logger']->info('Copied form with id %s to new form %s with id %s', $data['form_id'], $data['name'], $new_form_id);

        return $new_form_id;
    }

    public function updateFormSettings($data)
    {
        $show_title = $data['show_title'] ?? '1';
        $type = $data['type'];

        $sql = 'UPDATE `form`
                SET `name` =  :form_name
                WHERE id = :id';
        $this->di['db']->exec($sql, [':form_name' => $data['form_name'], ':id' => $data['form_id']]);

        $this->di['logger']->info('Updated form %s name to %s', $data['form_id'], $data['form_name']);

        $style = [
            'type' => $type,
            'show_title' => $show_title,
        ];

        $sql2 = 'UPDATE `form`
                SET `style` =  :type
                WHERE id = :id';
        $this->di['db']->exec($sql2, [':type' => json_encode($style, JSON_FORCE_OBJECT), ':id' => $data['form_id']]);
        $this->di['logger']->info('Updated form %s type to %s', $data['form_id'], $data['type']);

        return true;
    }
}

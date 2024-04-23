<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Manage custom orders forms.
 */

namespace Box\Mod\Formbuilder\Api;

class Admin extends \Api_Abstract
{
    /**
     * Create custom order form for product.
     *
     * @optional string $style - Style/Type of the form. Default value is "horizontal". Other possible types are "inline", "search", "actions"
     *
     * @return int - ID of the created form
     *
     * @throws \FOSSBilling\Exception
     */
    public function create_form($data)
    {
        $required = [
            'name' => 'Form name was not provided',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (isset($data['type']) && (strtolower($data['type']) != 'horizontal' || strtolower($data['type']) != 'default')) {
            throw new \FOSSBilling\Exception('Form style was not found in predefined list', null, 3657);
        }

        $service = $this->getService();

        return $service->addNewForm($data);
    }

    /**
     * Add new field to form.
     *
     * @optional
     * @optional string $label - Label of the field which will be shown. Default value "Type X" where X is number of fields in form. For example "Checkbox 2"
     * @optional string $name - Name of the field. Default value "new_type_X" where X is number of fields in the form. For example "new_checkbox_2"
     * @optional bool $hide_label - Option either hide label of field or not
     * @optional string $description - Description of a field
     * @optional mixed $default_value - Default value of field. If field is checkbox or radio this variable must be array, otherwise it is string
     * @optional bool $required - Option wither field need to have "required" attribute (Not applicable for checkboxes)
     * @optional bool $hidden - Option either field should be hidden
     * @optional bool $readonly - Option either field needs to be readonly
     * @optional string $prefix - Prefix for "text" type fields
     * @optional string $suffix - Suffix for "text" type fields
     * @optional array $options - Array of options for "select", "checkbox" and "radio" type fields. Key represents label and value will be field's value. Array must be unique.
     * @optional bool $show_initial - Either show initial or not
     * @optional bool $show_middle - Either show middle or not
     * @optional bool $show_prefix - Either to show prefix or not
     * @optional bool $show_suffix - Either to show suffix or not
     * @optional int $text_size - Preferred text size
     *
     * @return int - ID of created field
     *
     * @throws \FOSSBilling\Exception
     */
    public function add_field($data)
    {
        $service = $this->getService();
        if (!isset($data['type']) || !$service->typeValidation($data['type'])) {
            throw new \FOSSBilling\Exception('Form field type is invalid', null, 2684);
        }
        if (isset($data['options']) && is_array($data['options']) && !$service->isArrayUnique($data['options'])) {
            throw new \FOSSBilling\InformationException('This input type must have unique values', null, 3658);
        }
        if (!isset($data['form_id'])) {
            throw new \FOSSBilling\InformationException('Form id was not passed', null, 9846);
        }

        return $service->addNewField($data);
    }

    /**
     * Get form data by its id.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get_form($data)
    {
        $required = [
            'id' => 'Form id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data, null, 2391);

        $service = $this->getService();

        return $service->getForm($data['id']);
    }

    /**
     * Get fields data by form's id.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get_form_fields($data)
    {
        $required = [
            'form_id' => 'Form id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data, null, 1822);

        $service = $this->getService();

        return $service->getFormFields($data['form_id']);
    }

    /**
     * Get field data by field id.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get_field($data)
    {
        $required = [
            'id' => 'Field id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data, null, 3547);

        $service = $this->getService();

        return $service->getField($data['id']);
    }

    /**
     * Get array of forms.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get_forms()
    {
        $service = $this->getService();

        return $service->getForms();
    }

    /**
     * Delete form and its form fields.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete_form($data)
    {
        $required = [
            'id' => 'Form id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data, null, 9958);

        $service = $this->getService();
        $service->removeForm($data['id']);

        return true;
    }

    /**
     * Delete field by id.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete_field($data)
    {
        $required = [
            'id' => 'Field id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data, null, 9959);

        $service = $this->getService();
        $service->removeField($data);

        return true;
    }

    /**
     * Update form field.
     *
     * @param array $data - An array containing the field data
     *
     * @optional string $data['label'] - The label of the field to be displayed. Default is "Type X," where X is the number of fields in the form (e.g., "Checkbox 2").
     * @optional string $data['name'] - The name of the field. Default is "new_type_X," where X is the number of fields in the form (e.g., "new_checkbox_2").
     * @optional bool $data['hide_label'] - Specifies whether to hide the field label.
     * @optional string $data['description'] - The description of the field.
     * @optional mixed $data['default_value'] - The default value of the field. If the field is a checkbox or radio button, this should be an array; otherwise, it should be a string.
     * @optional bool $data['required'] - Specifies whether the field is required (not applicable for checkboxes).
     * @optional bool $data['hidden'] - Specifies whether the field should be hidden.
     * @optional bool $data['readonly'] - Specifies whether the field should be read-only.
     * @optional string $data['prefix'] - The prefix for "text" type fields.
     * @optional string $data['suffix'] - The suffix for "text" type fields.
     * @optional array $data['options'] - An array of options for "select," "checkbox," and "radio" type fields. Keys represent labels, and values represent field values. The array must contain unique values.
     * @optional bool $data['show_initial'] - Specifies whether to show the initial value.
     * @optional bool $data['show_middle'] - Specifies whether to show the middle value.
     * @optional bool $data['show_prefix'] - Specifies whether to show the prefix.
     * @optional bool $data['show_suffix'] - Specifies whether to show the suffix.
     * @optional int $data['text_size'] - The preferred text size.
     *
     * @return int - The ID of the updated field
     *
     * @throws \FOSSBilling\Exception
     */
    public function update_field($data)
    {
        $required = [
            'id' => 'Field id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data, null, 9958);

        $service = $this->getService();
        if (isset($data['options']) && !$service->isArrayUnique($data['options'])) {
            throw new \FOSSBilling\InformationException('This input type must have unique values', null, 3658);
        }

        return $service->updateField($data);
    }

    /**
     * Get form pairs.
     */
    public function get_pairs($data)
    {
        $service = $this->getService();

        return $service->getFormPairs();
    }

    /**
     * Duplicate form with its fields in database.
     *
     * @return int - ID of the new form
     *
     * @throws \FOSSBilling\Exception
     */
    public function copy_form($data)
    {
        if (!isset($data['form_id'])) {
            throw new \FOSSBilling\InformationException('Form id was not passed', null, 9958);
        }
        if (!isset($data['name'])) {
            throw new \FOSSBilling\InformationException('Form name was not passed', null, 9842);
        }

        $service = $this->getService();

        return $service->duplicateForm($data);
    }

    /**
     * Update form name and style.
     *
     * @return bool
     */
    public function update_form_settings($data)
    {
        if (!isset($data['form_id']) || (trim($data['form_id']) == '')) {
            throw new \FOSSBilling\InformationException('Form id was not passed', null, 1654);
        }
        if (!isset($data['form_name'])) {
            throw new \FOSSBilling\InformationException('Form name was not passed', null, 9241);
        }

        if (!isset($data['type'])) {
            throw new \FOSSBilling\InformationException('Form type was not passed', null, 3794);
        }

        if ($data['type'] != 'horizontal' && $data['type'] != 'default') {
            throw new \FOSSBilling\Exception('Field type not supported', null, 3207);
        }

        $service = $this->getService();

        return $service->updateFormSettings($data);
    }
}

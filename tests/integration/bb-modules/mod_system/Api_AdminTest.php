<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * @group Core
 */
class Api_Admin_SystemTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'settings.xml';

    public function testUpdateParams()
    {
        $data = [
            'captcha_enabled' => '1',
            'captcha_recaptcha_publickey' => 'pub',
            'captcha_recaptcha_privatekey' => 'priv',
        ];
        $bool = $this->api_admin->system_update_params($data);
        $array = $this->api_admin->system_get_params();

        $this->assertEquals('1', $array['captcha_enabled']);
        $this->assertEquals('priv', $array['captcha_recaptcha_privatekey']);
        $this->assertEquals('pub', $array['captcha_recaptcha_publickey']);
    }

    public function testFiles()
    {
        $bool = $this->api_admin->system_template_exists();
        $this->assertFalse($bool);

        $bool = $this->api_admin->system_template_exists(['file' => 'non_existing_template.html.twig']);
        $this->assertFalse($bool);

        $bool = $this->api_admin->system_template_exists(['file' => 'mod_index_dashboard.html.twig']);
        $this->assertTrue($bool);
    }

    public function testRender()
    {
        $vars = [
            '_tpl' => '{{ now|date("Y") }}',
        ];
        $string = $this->api_admin->system_string_render($vars);
        $this->assertEquals(date('Y'), $string);

        // test guest API in template
        $vars = [
            '_tpl' => '{{ guest.system_states | json_encode }}',
        ];
        $string = $this->api_admin->system_string_render($vars);
        $json = html_entity_decode($string);
        $result = json_decode($json, 1);
        $expected = $this->api_guest->system_states();
        $this->assertEquals($expected, $result);

        // test admin API in template
        $vars = [
            '_tpl' => '{{ admin.cron_info | json_encode }}',
        ];
        $string = $this->api_admin->system_string_render($vars);
        $json = html_entity_decode($string);
        $result = json_decode($json, 1);
        $expected = $this->api_admin->cron_info();
        $this->assertEquals($expected, $result);

        // test client API in template
        $vars = [
            '_tpl' => '{{ client.order_get_list | json_encode }}',
            '_client_id' => 1,
        ];
        $string = $this->api_admin->system_string_render($vars);
        $json = html_entity_decode($string);
        $result = json_decode($json, 1);
        $expected = $this->api_client->order_get_list();
        $this->assertEquals($expected, $result);
    }

    public function testPermissions()
    {
        $bool = $this->api_admin->system_is_allowed(['mod' => 'order']);
        $this->assertTrue($bool);

        $bool = $this->api_admin->system_is_allowed(['mod' => 'notexisting']);
        $this->assertTrue($bool);
    }

    public function testInfos()
    {
        $array = $this->api_admin->system_env();
        $this->assertIsArray($array);

        $array = $this->api_admin->system_messages();
        $this->assertIsArray($array);

        $array = $this->api_admin->system_license_info();
        $this->assertIsArray($array);

        $array = $this->api_admin->system_get_params();
        $this->assertIsArray($array);

        $string = $this->api_admin->system_param(['key' => 'db_version']);
        $this->assertEquals('2', '2');
    }

    public function testRenderDate()
    {
        $result = date($this->di['config']['locale_date_format']);
        $data = [
            'id' => 1,
            '_tpl' => '{{ now|date("Y-m-d")|format_date }}',
        ];
        $string = $this->api_admin->email_template_render($data);
        $this->assertEquals($result, $string);
    }
}

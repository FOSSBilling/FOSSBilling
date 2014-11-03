<?php
/**
 * @group Core
 */
class Api_Guest_SystemTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'settings.xml';
    
    public function testPhoneCodes()
    {
        $array = $this->api_guest->system_phone_codes();
        $this->assertInternalType('array', $array);
        
        $str = $this->api_guest->system_phone_codes(array('country'=>'US'));
        $this->assertEquals('1', $str);
        
        foreach ($this->api_guest->system_countries() as $code => $name) {
            $this->api_guest->system_phone_codes(array('country'=>$code));
        }
    }
    
    public function testCompany()
    {
        $array = $this->api_guest->system_countries();
        $this->assertInternalType('array', $array);
        
        $array = $this->api_guest->system_countries_eunion();
        $this->assertInternalType('array', $array);

        $array = $this->api_guest->system_states();
        $this->assertInternalType('array', $array);

        $array = $this->api_guest->system_company();
        $this->assertInternalType('array', $array);
    }

    public function testFiles()
    {
        $bool = $this->api_guest->system_template_exists();
        $this->assertFalse($bool);
        
        $bool = $this->api_guest->system_template_exists(array('file'=>'non_existing_template.phtml'));
        $this->assertFalse($bool);
        
        $bool = $this->api_guest->system_template_exists(array('file'=>'mod_index_dashboard.phtml'));
        $this->assertTrue($bool);
    }
    
    public function testSystem()
    {
        $string = $this->api_guest->system_version();
        $this->assertInternalType('string', $string);

        $array = $this->api_guest->system_company();
        $this->assertInternalType('array', $array);
        
        $string = $this->api_guest->system_param(array('key'=>'phpunit'));
        $this->assertInternalType('string', $string);

        $array = $this->api_guest->system_countries();
        $this->assertInternalType('array', $array);

        $array = $this->api_guest->system_periods();
        $this->assertInternalType('array', $array);
        
        $string = $this->api_guest->system_locale();
        $this->assertInternalType('string', $string);
    }
}
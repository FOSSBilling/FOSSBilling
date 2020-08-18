<?php
namespace Box\Tests\Mod\Currency;


class ServiceTest extends \BBTestCase
{

    public function testDi()
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function testGetSearchQuery()
    {
        $service = new \Box\Mod\Currency\Service();
        $result  = $service->getSearchQuery(array());
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals("SELECT * FROM currency WHERE 1", $result[0]);
    }

    public function testGetBaseCurrencyRate()
    {
        $service  = new \Box\Mod\Currency\Service();
        $rate     = 0.6;
        $expected = 1 / $rate;
        $di       = new \Box_Di();
        $db       = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue($rate));

        $di['db'] = $db;
        $service->setDi($di);
        $code   = 'EUR';
        $result = $service->getBaseCurrencyRate($code);
        $this->assertEquals($expected, $result);
    }

    public function testGetBaseCurrencyRateException()
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(0));

        $di['db'] = $db;
        $service->setDi($di);
        $code = 'EUR';
        $this->expectException(\Box_Exception::class);
        $service->getBaseCurrencyRate($code); //Expecting exception
    }


    public function toBaseCurrencyProvider()
    {
        return array(
            array('EUR', 'USD', 100, 0.73, 73), // 100 USD ~ 72.99 EUR
            array('USD', 'EUR', 100, 1.37, 137), // 100 Eur  ~ 136.99 USD
            array('EUR', 'EUR', 100, 0.5, 100), //should return same amount
        );
    }

    /**
     * @dataProvider toBaseCurrencyProvider
     */
    public function testToBaseCurrency($defaultCode, $foreignCode, $amount, $rate, $expected)
    {
        $model      = new \Model_Currency();
        $bean       = new \RedBeanPHP\OODBBean();
        $bean->code = $defaultCode;
        $model->loadBean($bean);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')
            ->setMethods(array('getDefault', 'getBaseCurrencyRate'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getDefault')
            ->will($this->returnValue($model));

        $serviceMock->expects($this->any()) // will not be called when currencies are the same, so using any()
            ->method('getBaseCurrencyRate')
            ->will($this->returnValue($rate));

        $result = $serviceMock->toBaseCurrency($foreignCode, $amount);

        $this->assertEquals($expected, round($result, 2));
    }

    public function getCurrencyByClientIdProvider()
    {
        $model = new \Model_Currency();
        $bean  = new \RedBeanPHP\OODBBean();
        $model->loadBean($bean);

        return array(
            array(
                $model,
                'USD',
                $this->atLeastOnce(),
                $this->never()
            ),
            array(
                $model,
                null,
                $this->never(),
                $this->atLeastOnce()
            ),
        );
    }

    /**
     * @dataProvider getCurrencyByClientIdProvider
     */
    public function testGetCurrencyByClientId($row, $currency, $expectsGetByCode, $getDefaultCalled)
    {
        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue($currency));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getDefault', 'getByCode'))->getMock();

        $serviceMock->expects($getDefaultCalled)
            ->method('getDefault')
            ->will($this->returnValue($row));

        $serviceMock->expects($expectsGetByCode)
            ->method('getByCode')
            ->will($this->returnValue($row));

        $di['db'] = $db;
        $serviceMock->setDi($di);

        $result = $serviceMock->getCurrencyByClientId(1);

        $this->assertEquals($row, $result);
        $this->assertInstanceOf('Model_Currency', $result);
    }

    public function testGetCurrencyByClientIdNotFounfByCode()
    {
        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(new \Model_Currency()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getDefault', 'getByCode'))->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getDefault')
            ->will($this->returnValue(new \Model_Currency()));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->will($this->returnValue(null));

        $di['db'] = $db;
        $serviceMock->setDi($di);

        $result = $serviceMock->getCurrencyByClientId(1);

        $this->assertInstanceOf('Model_Currency', $result);
    }

    public function testgetByCode()
    {
        $di         = new \Box_Di();
        $service    = new \Box\Mod\Currency\Service();
        $bean       = new \RedBeanPHP\OODBBean();
        $bean->code = 'EUR';
        $model      = new \Model_Currency();
        $model->loadBean($bean);

        $currency = 'EUR';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->getByCode($currency);

        $this->assertEquals($model, $result);
        $this->assertInstanceOf('Model_Currency', $result);
        $this->assertEquals($model->code, $currency);
    }

    public function getRateByCodeProvider()
    {
        return array(
            array('EUR', 0.6, 0.6),
            array('GBP', null, 1),
            array('GBP', 'rate', 1),
        );
    }

    /**
     * @dataProvider getRateByCodeProvider
     */
    public function testGetRateByCode($code, $returns, $expected)
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue($returns));

        $di['db'] = $db;
        $service->setDi($di);
        $code   = $code;
        $result = $service->getRateByCode($code);
        $this->assertEquals($expected, $result);

    }

    public function testGetDefault()
    {
        $service = new \Box\Mod\Currency\Service();

        $bean  = new \RedBeanPHP\OODBBean();
        $model = new \Model_Currency();
        $model->loadBean($bean);

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
       $db->expects($this->atLeastOnce())
           ->method('findOne')
           ->willReturn(array());
        $db->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);
        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDefault();

        $this->assertInstanceOf('Model_Currency', $result);
        $this->assertEquals($model, $result);
    }

    public function setAsDefaultProvider()
    {
        $firstModel              = new \Model_Currency();
        $firstModel->loadBean(new \RedBeanPHP\OODBBean());
        $firstModel->code        = 'USD';
        $firstModel->is_default  = 0;
        $secondModel             = new \Model_Currency();
        $secondModel->loadBean(new \RedBeanPHP\OODBBean());
        $secondModel->code       = 'USD';
        $secondModel->is_default = 1;


        return array(
            array($firstModel, $this->atLeastOnce()),
            array($secondModel, $this->never()),
        );
    }

    /**
     * @dataProvider setAsDefaultProvider
     */
    public function testSetAsDefault($model, $expects)
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($expects)
            ->method('exec')
            ->will($this->returnValue(true));

        $di['db']     = $db;
        $di['logger'] = new \Box_Log();
        $service->setDi($di);
        $result = $service->setAsDefault($model);

        $this->assertEquals(true, $result);
    }

    public function testSetAsDefaultException()
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('exec')
            ->will($this->returnValue(true));

        $model             = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->is_default = 0;
        $model->code       = null;

        $di['db'] = $db;
        $service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $service->setAsDefault($model); //Currency code is null, should throw an \Box_Exception
    }

    public function testgetPairs()
    {
        $service = new \Box\Mod\Currency\Service();

        $pairs = array(
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'Pound Sterling'
        );
        $di    = new \Box_Di();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($pairs));

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getPairs();

        $this->assertEquals($result, $pairs);
    }

    public function testGetAvailableCurrencies()
    {
        $service = new \Box\Mod\Currency\Service();

        $availableCurrencies = array(
            'AED' => 'AED - United Arab Emirates dirham',
            'AFN' => 'AFN - Afghan afghani',
            'ALL' => 'ALL - Albanian lek',
            'AMD' => 'AMD - Armenian dram',
            'ANG' => 'ANG - Netherlands Antillean guilder',
            'AOA' => 'AOA - Angolan kwanza',
            'ARS' => 'ARS - Argentine peso',
            'AUD' => 'AUD - Australian dollar',
            'AWG' => 'AWG - Aruban florin',
            'AZN' => 'AZN - Azerbaijani manat',
            'BAM' => 'BAM - Bosnia and Herzegovina convertible mark',
            'BBD' => 'BBD - Barbados dollar',
            'BDT' => 'BDT - Bangladeshi taka',
            'BGN' => 'BGN - Bulgarian lev',
            'BHD' => 'BHD - Bahraini dinar',
            'BIF' => 'BIF - Burundian franc',
            'BMD' => 'BMD - Bermudian dollar',
            'BND' => 'BND - Brunei dollar',
            'BOB' => 'BOB - Boliviano',
            'BRL' => 'BRL - Brazilian real',
            'BSD' => 'BSD - Bahamian dollar',
            'BTN' => 'BTN - Bhutanese ngultrum',
            'BWP' => 'BWP - Botswana pula',
            'BYR' => 'BYR - Belarusian ruble',
            'BZD' => 'BZD - Belize dollar',
            'CAD' => 'CAD - Canadian dollar',
            'CDF' => 'CDF - Congolese franc',
            'CHF' => 'CHF - Swiss franc',
            'CLP' => 'CLP - Chilean peso',
            'CNY' => 'CNY - Chinese yuan',
            'COP' => 'COP - Colombian peso',
            'COU' => 'COU - Unidad de Valor Real',
            'CRC' => 'CRC - Costa Rican colon',
            'CUC' => 'CUC - Cuban convertible peso',
            'CUP' => 'CUP - Cuban peso',
            'CVE' => 'CVE - Cape Verde escudo',
            'CZK' => 'CZK - Czech koruna',
            'DJF' => 'DJF - Djiboutian franc',
            'DKK' => 'DKK - Danish krone',
            'DOP' => 'DOP - Dominican peso',
            'DZD' => 'DZD - Algerian dinar',
            'EGP' => 'EGP - Egyptian pound',
            'ERN' => 'ERN - Eritrean nakfa',
            'ETB' => 'ETB - Ethiopian birr',
            'EUR' => 'EUR - Euro',
            'FJD' => 'FJD - Fiji dollar',
            'FKP' => 'FKP - Falkland Islands pound',
            'GBP' => 'GBP - Pound sterling',
            'GEL' => 'GEL - Georgian lari',
            'GHS' => 'GHS - Ghanaian cedi',
            'GIP' => 'GIP - Gibraltar pound',
            'GMD' => 'GMD - Gambian dalasi',
            'GNF' => 'GNF - Guinean franc',
            'GTQ' => 'GTQ - Guatemalan quetzal',
            'GYD' => 'GYD - Guyanese dollar',
            'HKD' => 'HKD - Hong Kong dollar',
            'HNL' => 'HNL - Honduran lempira',
            'HRK' => 'HRK - Croatian kuna',
            'HTG' => 'HTG - Haitian gourde',
            'HUF' => 'HUF - Hungarian forint',
            'IDR' => 'IDR - Indonesian rupiah',
            'ILS' => 'ILS - Israeli new sheqel',
            'INR' => 'INR - Indian rupee',
            'IQD' => 'IQD - Iraqi dinar',
            'IRR' => 'IRR - Iranian rial',
            'ISK' => 'ISK - Icelandic króna',
            'JMD' => 'JMD - Jamaican dollar',
            'JOD' => 'JOD - Jordanian dinar',
            'JPY' => 'JPY - Japanese yen',
            'KES' => 'KES - Kenyan shilling',
            'KGS' => 'KGS - Kyrgyzstani som',
            'KHR' => 'KHR - Cambodian riel',
            'KMF' => 'KMF - Comoro franc',
            'KPW' => 'KPW - North Korean won',
            'KRW' => 'KRW - South Korean won',
            'KWD' => 'KWD - Kuwaiti dinar',
            'KYD' => 'KYD - Cayman Islands dollar',
            'KZT' => 'KZT - Kazakhstani tenge',
            'LAK' => 'LAK - Lao kip',
            'LBP' => 'LBP - Lebanese pound',
            'LKR' => 'LKR - Sri Lanka rupee',
            'LRD' => 'LRD - Liberian dollar',
            'LSL' => 'LSL - Lesotho loti',
            'LYD' => 'LYD - Libyan dinar',
            'MAD' => 'MAD - Moroccan dirham',
            'MDL' => 'MDL - Moldovan leu',
            'MGA' => 'MGA - Malagasy ariary',
            'MKD' => 'MKD - Macedonian denar',
            'MMK' => 'MMK - Myanma kyat',
            'MNT' => 'MNT - Mongolian tugrik',
            'MOP' => 'MOP - Macanese pataca',
            'MRO' => 'MRO - Mauritanian ouguiya',
            'MUR' => 'MUR - Mauritian rupee',
            'MVR' => 'MVR - Maldivian rufiyaa',
            'MWK' => 'MWK - Malawian kwacha',
            'MXN' => 'MXN - Mexican peso',
            'MYR' => 'MYR - Malaysian ringgit',
            'MZN' => 'MZN - Mozambican metical',
            'NAD' => 'NAD - Namibian dollar',
            'NGN' => 'NGN - Nigerian naira',
            'NIO' => 'NIO - Cordoba oro',
            'NOK' => 'NOK - Norwegian krone',
            'NPR' => 'NPR - Nepalese rupee',
            'NZD' => 'NZD - New Zealand dollar',
            'OMR' => 'OMR - Omani rial',
            'PAB' => 'PAB - Panamanian balboa',
            'PEN' => 'PEN - Peruvian nuevo sol',
            'PGK' => 'PGK - Papua New Guinean kina',
            'PHP' => 'PHP - Philippine peso',
            'PKR' => 'PKR - Pakistani rupee',
            'PLN' => 'PLN - Polish złoty',
            'PYG' => 'PYG - Paraguayan guaraní',
            'QAR' => 'QAR - Qatari rial',
            'RON' => 'RON - Romanian new leu',
            'RSD' => 'RSD - Serbian dinar',
            'RUB' => 'RUB - Russian rouble',
            'RWF' => 'RWF - Rwandan franc',
            'SAR' => 'SAR - Saudi riyal',
            'SBD' => 'SBD - Solomon Islands dollar',
            'SCR' => 'SCR - Seychelles rupee',
            'SDG' => 'SDG - Sudanese pound',
            'SEK' => 'SEK - Swedish krona/kronor',
            'SGD' => 'SGD - Singapore dollar',
            'SHP' => 'SHP - Saint Helena pound',
            'SLL' => 'SLL - Sierra Leonean leone',
            'SOS' => 'SOS - Somali shilling',
            'SRD' => 'SRD - Surinamese dollar',
            'STD' => 'STD - São Tomé and Príncipe dobra',
            'SYP' => 'SYP - Syrian pound',
            'SZL' => 'SZL - Lilangeni',
            'THB' => 'THB - Thai baht',
            'TJS' => 'TJS - Tajikistani somoni',
            'TMT' => 'TMT - Turkmenistani manat',
            'TND' => 'TND - Tunisian dinar',
            'TOP' => 'TOP - Tongan paʻanga',
            'TRY' => 'TRY - Turkish lira',
            'TTD' => 'TTD - Trinidad and Tobago dollar',
            'TWD' => 'TWD - New Taiwan dollar',
            'TZS' => 'TZS - Tanzanian shilling',
            'UAH' => 'UAH - Ukrainian hryvnia',
            'UGX' => 'UGX - Ugandan shilling',
            'USD' => 'USD - United States dollar',
            'UYU' => 'UYU - Uruguayan peso',
            'UZS' => 'UZS - Uzbekistan som',
            'VEF' => 'VEF - Venezuelan bolívar fuerte',
            'VND' => 'VND - Vietnamese đồng',
            'VUV' => 'VUV - Vanuatu vatu',
            'WST' => 'WST - Samoan tala',
            'XOF' => 'XOF - West African CFA franc',
            'YER' => 'YER - Yemeni rial',
            'ZAR' => 'ZAR - South African rand',
            'ZMK' => 'ZMK - Zambian kwacha',
            'ZWL' => 'ZWL - Zimbabwe dollar',
        );

        $result = $service->getAvailableCurrencies();

        $this->assertEquals($result, $availableCurrencies);
    }
    
    public function testRmDefaultCurrencyException()
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('exec')
            ->will($this->returnValue(true));

        $model             = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->code       = 'EUR';
        $model->is_default = 1;

        $di['db'] = $db;
        $service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $service->rm($model); //will throw \Box_Exception because default currency can not be removed
    }


    public function testRm()
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue(true));

        $model             = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->code       = 'EUR';
        $model->is_default = 0;

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->rm($model);

        $this->assertEquals($result, null);
    }
    
    public function testRmMissingCodeException()
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('exec')
            ->will($this->returnValue(true));

        $model             = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->is_default = 0;
        $model->code       = null;

        $di['db'] = $db;
        $service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $service->rm($model); //will throw \Box_Exception because currency code is not set
    }

    public function testToApiArray()
    {
        $service = new \Box\Mod\Currency\Service();

        $model = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $model->code            = 'EUR';
        $model->title           = 'Euro';
        $model->conversion_rate = '3.4528';
        $model->format          = '';
        $model->price_format    = '';
        $model->is_default      = 1;


        $expected = array(
            'code'            => $model->code,
            'title'           => $model->title,
            'conversion_rate' => (float)$model->conversion_rate,
            'format'          => $model->format,
            'price_format'    => $model->price_format,
            'default'         => $model->is_default,
        );

        $result = $service->toApiArray($model);
        $this->assertEquals($result, $expected);
    }

    public function testCreateCurrency()
    {
        $service = new \Box\Mod\Currency\Service();

        $code   = 'EUR';
        $format = '€{{price}}';

        $systemService= $this->getMockBuilder('\Box\Mod\System\Service')->setMethods(array('checkLimits'))->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits')
            ->will($this->returnValue(null));

        $currencyModel = new \Model_Tld();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($currencyModel));


        $di                = new \Box_Di();
        $di['logger']      = new \Box_Log();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });
        $service->setDi($di);

        $result = $service->createCurrency($code, $format, 'Euros', 0.6);

        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 3);
        $this->assertEquals($result, $code);

    }

    public function testUpdateCurrency()
    {
        $code            = 'EUR';
        $format          = '€{{price}}';
        $title           = 'Euros';
        $price_format    = '€{{Price}}';
        $conversion_rate = 0.6;

        $model       = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->code = 'EUR';

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getByCode'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->will($this->returnValue($model));

        $di           = new \Box_Di();
        $db           = $this->getMockBuilder('Box_Database')->getMock();
        $di['logger'] = new \Box_Log();
        $di['db']     = $db;
        $service->setDi($di);

        $result = $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);

    }

    public function testUpdateCurrencyNotFoundException()
    {
        $code            = 'EUR';
        $format          = '€{{price}}';
        $title           = 'Euros';
        $price_format    = '€{{Price}}';
        $conversion_rate = 0.6;

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getByCode'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->will($this->returnValue(false));
            $this->expectException(\Box_Exception::class);
        $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate); //Expecting \Box_Exception every time
    }

    public function testUpdateConversionRateException()
    {
        $code            = 'EUR';
        $format          = '€{{price}}';
        $title           = 'Euros';
        $price_format    = '€{{Price}}';
        $conversion_rate = 0;

        $model       = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getByCode'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->will($this->returnValue($model));

        $this->expectException(\Box_Exception::class);
        $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate); //Expecting \Box_Exception every time
    }

    public function testUpdateCurrencyRates()
    {
        $model       = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->code = 'EUR';

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getDefault', '_getRate'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->will($this->returnValue($model));
        $service->expects($this->atLeastOnce())
            ->method('_getRate')
            ->will($this->returnValue(rand(1, 50) / 10));

        $bean             = new \RedBeanPHP\OODBBean();
        $bean->is_default = 1;
        $bean->code       = 'EUR';

        $bean2             = new \RedBeanPHP\OODBBean();
        $bean2->is_default = 0;
        $bean2->code       = 'USD';


        $beansArray = array(
            $bean, $bean2
        );

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue($beansArray));

        $di['logger'] = new \Box_Log();
        $di['db']     = $db;
        $service->setDi($di);

        $result = $service->updateCurrencyRates(array());

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }


    public function testUpdateCurrencyRatesRateNotNumeric()
    {
        $model       = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->code = 'EUR';

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getDefault', '_getRate'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->will($this->returnValue($model));
        $service->expects($this->atLeastOnce())
            ->method('_getRate')
            ->will($this->returnValue(null));

        $bean             = new \RedBeanPHP\OODBBean();
        $bean->is_default = 0;
        $bean->code       = 'EUR';


        $beansArray = array(
            $bean
        );

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue($beansArray));

        $di['logger'] = new \Box_Log();
        $di['db']     = $db;
        $service->setDi($di);

        $result = $service->updateCurrencyRates(array());

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testDelete()
    {
        $model       = new \Model_Currency();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->code = 'EUR';


        $code = 'EUR';

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getByCode', 'rm'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->will($this->returnValue($model));
        $service->expects($this->atLeastOnce())
            ->method('rm')
            ->will($this->returnValue($model));

        $manager = $this->getMockBuilder('Box_EventManager')->getMock();
        $manager->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));


        $di                   = new \Box_Di();
        $db                   = $this->getMockBuilder('Box_Database')->getMock();
        $di['logger']         = new \Box_Log();
        $di['db']             = $db;
        $di['events_manager'] = $manager;

        $service->setDi($di);

        $result = $service->deleteCurrencyByCode($code);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }
   
    public function testDeleteModelNotFoundException()
    {
        $code = 'EUR';

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getByCode', 'rm'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->will($this->returnValue(null));

        $this->expectException(\Box_Exception::class);
        $result = $service->deleteCurrencyByCode($code);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testValidateCurrencyFormat()
    {
        $service = new \Box\Mod\Currency\Service();

        $service->validateCurrencyFormat('${{price}}');
    }

    public function testValidateCurrencyFormatPriceTagMissing()
    {
        $service = new \Box\Mod\Currency\Service();

        $this->expectException(\Exception::class);
        $service->validateCurrencyFormat('$$$');
    }

}
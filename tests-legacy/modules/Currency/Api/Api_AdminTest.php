<?php

namespace Box\Tests\Mod\Currency\Api;

class Api_AdminTest extends \BBTestCase
{
    public $availableCurrencies = [
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
    ];

    public function testGetList(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $willReturn = [
            'list' => ['id' => 1],
        ];

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $pager = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['pager'] = $pager;

        $adminApi->setDi($di);

        $service = new \Box\Mod\Currency\Service();
        $adminApi->setService($service);

        $result = $adminApi->get_list([]);

        $this->assertIsArray($result);
        $this->assertIsArray($result['list']);
    }

    public function testGetPairs(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getAvailableCurrencies')
            ->willReturn($this->availableCurrencies);

        $adminApi->setService($service);
        $result = $adminApi->get_pairs();
        $this->assertEquals($result, $this->availableCurrencies);
        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();
        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $data = [
            'code' => 'EUR',
        ];
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $result = $adminApi->get($data);
        $this->assertIsArray($result);
    }

    public function testGetDefault(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();
        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';
        $model->title = 'Euro';
        $model->conversion_rate = '3.4528';
        $model->format = '';
        $model->price_format = '';
        $model->is_default = 1;

        $returnArr = [
            'code' => $model->code,
            'title' => $model->title,
            'conversion_rate' => (float) $model->conversion_rate,
            'format' => $model->format,
            'price_format' => $model->price_format,
            'default' => $model->is_default,
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn($model);

        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($returnArr);

        $adminApi->setService($service);
        $result = $adminApi->get_default([]);

        $this->assertIsArray($result);
        $this->assertEquals($result, $returnArr);

        $this->assertEquals($model->code, $returnArr['code']);
        $this->assertEquals($model->title, $returnArr['title']);
        $this->assertIsFloat($returnArr['conversion_rate']);
        $this->assertEquals((float) $model->conversion_rate, $returnArr['conversion_rate']);
        $this->assertEquals($model->format, $returnArr['format']);
        $this->assertEquals($model->price_format, $returnArr['price_format']);
        $this->assertEquals($model->is_default, $returnArr['default']);
    }

    public static function CreateExceptionProvider(): array
    {
        $self = new Api_AdminTest('Api_AdminTest');

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        return [
            [
                [
                    'code' => 'EUR',
                    'format' => '€{{price}}',
                ],
                $self->atLeastOnce(),
                $model, // currency exists already
                $self->never(),
            ],
            [
                [
                    'code' => 'NON', // Non existing currency
                    'format' => '€{{price}}',
                ],
                $self->atLeastOnce(),
                null,
                $self->atLeastOnce(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('CreateExceptionProvider')]
    public function testCreateException(array $data, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $getByCodeCalled, ?\Model_Currency $getByCodeReturn, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $getAvailableCurrenciesCalled): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($getByCodeCalled)
            ->method('getByCode')
            ->willReturn($getByCodeReturn);

        $service->expects($getAvailableCurrenciesCalled)
            ->method('getAvailableCurrencies')
            ->willReturn($this->availableCurrencies);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setService($service);
        $adminApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->create($data); // Expecting \FOSSBilling\Exception every time
    }

    public function testCreate(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $data = [
            'code' => 'EUR',
            'format' => '€{{price}}',
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn(null);
        $service->expects($this->atLeastOnce())
            ->method('getAvailableCurrencies')
            ->willReturn($this->availableCurrencies);
        $service->expects($this->atLeastOnce())
            ->method('createCurrency')
            ->willReturn($data['code']);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setService($service);
        $adminApi->setDi($di);

        $result = $adminApi->create($data);

        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 3);
        $this->assertEquals($result, $data['code']);
    }

    public function testUpdate(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $data = [
            'code' => 'EUR',
            'format' => '€{{price}}',
            'title' => 'Euros',
            'price_format' => '€{{Price}}',
            'conversion_rate' => 0.6,
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('updateCurrency')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->update($data);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testDeleteException(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->never())
            ->method('getByCode')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')->willThrowException(new \FOSSBilling\Exception(''));

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($service);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->delete([]); // Expecting \FOSSBilling\Exception every time
    }

    public function testDelete(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        $data = [
            'code' => 'EUR',
            'format' => '€{{price}}',
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['deleteCurrencyByCode'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('deleteCurrencyByCode')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->delete($data);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public static function SetDefaultExceptionProvider(): array
    {
        $self = new Api_AdminTest('Api_AdminTest');

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        return [
            [
                [
                    'code' => 'EUR', // model is not instance of \Model_Currency
                ],
                $self->atLeastOnce(),
                null,
            ],
        ];
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('SetDefaultExceptionProvider')]
    public function testSetDefaultException(array $data, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $getByCodeCalled, $getByCodeReturn): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($getByCodeCalled)
            ->method('getByCode')
            ->willReturn($getByCodeReturn);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);

        $adminApi->setService($service);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->set_default($data); // Expecting \FOSSBilling\Exception every time
    }

    public function testSetDefault(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        $data = [
            'code' => 'EUR',
            'format' => '€{{price}}',
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('setAsDefault')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $di['db'] = $db;
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->set_default($data);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }
}

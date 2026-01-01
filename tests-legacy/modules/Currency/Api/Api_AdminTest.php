<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Currency\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_AdminTest extends \BBTestCase
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

        $qbMock = $this->getMockBuilder('\Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $pager = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['paginateDoctrineQuery'])
            ->disableOriginalConstructor()
            ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('paginateDoctrineQuery')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $adminApi->setDi($di);
        $adminApi->setService($serviceMock);

        $result = $adminApi->get_list([]);

        $this->assertIsArray($result);
        $this->assertIsArray($result['list']);
    }

    public function testGetPairs(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $service = $this->createMock(\Box\Mod\Currency\Service::class);
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

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([
                'code' => 'EUR',
                'title' => 'Euro',
                'conversion_rate' => 1.0,
                'format' => '€{{price}}',
                'price_format' => '€{{price}}',
            ]);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->with('EUR')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $di = $this->getDi();
        $data = [
            'code' => 'EUR',
        ];
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $result = $adminApi->get($data);
        $this->assertIsArray($result);
        $this->assertEquals($result['code'], 'EUR');
    }

    public function testGetDefault(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $returnArr = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 3.4528,
            'format' => '',
            'price_format' => '',
            'default' => true,
        ];

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($returnArr);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $adminApi->setService($service);
        $result = $adminApi->get_default([]);

        $this->assertIsArray($result);
        $this->assertEquals($result, $returnArr);
        $this->assertEquals('EUR', $returnArr['code']);
        $this->assertEquals('Euro', $returnArr['title']);
        $this->assertIsFloat($returnArr['conversion_rate']);
        $this->assertEquals(3.4528, $returnArr['conversion_rate']);
        $this->assertEquals('', $returnArr['format']);
        $this->assertEquals('', $returnArr['price_format']);
        $this->assertTrue($returnArr['default']);
    }

    public static function CreateExceptionProvider(): array
    {
        return [
            [
                [
                    'code' => 'EUR',
                    'format' => '€{{price}}',
                ],
                'atLeastOnce',
                'currency_exists', // use string flag instead of mock
                'never',
            ],
            [
                [
                    'code' => 'NON', // Non existing currency
                    'format' => '€{{price}}',
                ],
                'atLeastOnce',
                null,
                'atLeastOnce',
            ],
        ];
    }

    #[DataProvider('CreateExceptionProvider')]
    public function testCreateException(array $data, $findOneByCodeCalled, $findOneByCodeReturn, $getAvailableCurrenciesCalled): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Create mock if needed
        if ($findOneByCodeReturn === 'currency_exists') {
            $findOneByCodeReturn = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
                ->disableOriginalConstructor()
                ->getMock();
        }
        
        $repositoryMock->expects($this->$findOneByCodeCalled())
            ->method('findOneByCode')
            ->willReturn($findOneByCodeReturn);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $service->expects($this->$getAvailableCurrenciesCalled())
            ->method('getAvailableCurrencies')
            ->willReturn($this->availableCurrencies);


        $di = $this->getDi();
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

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);
        $service->expects($this->atLeastOnce())
            ->method('getAvailableCurrencies')
            ->willReturn($this->availableCurrencies);
        $service->expects($this->atLeastOnce())
            ->method('createCurrency')
            ->willReturn($data['code']);


        $di = $this->getDi();
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

        $service = $this->createMock(\Box\Mod\Currency\Service::class);
        $service->expects($this->atLeastOnce())
            ->method('updateCurrency')
            ->willReturn(true);


        $di = $this->getDi();
        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->update($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testDeleteException(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $service = $this->createMock(\Box\Mod\Currency\Service::class);
        $service->expects($this->never())
            ->method('deleteCurrencyByCode');

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');
        $this->expectException(\FOSSBilling\InformationException::class);
        $method->invokeArgs($apiHandler, [$adminApi, 'delete', []]);

        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();
        $adminApi->setDi($di);
        $adminApi->setService($service);
        $adminApi->delete([]);
    }

    public function testDelete(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $data = [
            'code' => 'EUR',
            'format' => '€{{price}}',
        ];

        $service = $this->getMockBuilder(\Box\Mod\Currency\Service::class)->onlyMethods(['deleteCurrencyByCode'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('deleteCurrencyByCode')
            ->willReturn(true);


        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();
        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->delete($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public static function SetDefaultExceptionProvider(): array
    {
        return [
            [
                [
                    'code' => 'EUR', // model is not instance of Currency
                ],
                'atLeastOnce',
                null,
            ],
        ];
    }

    #[DataProvider('SetDefaultExceptionProvider')]
    public function testSetDefaultException(array $data, $getByCodeCalled, $getByCodeReturn): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $di = $this->getDi();
        $adminApi->setDi($di);

        $adminApi->setService($service);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->set_default($data); // Expecting \FOSSBilling\Exception every time
    }

    public function testSetDefault(): void
    {
        $adminApi = new \Box\Mod\Currency\Api\Admin();

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $data = [
            'code' => 'EUR',
            'format' => '€{{price}}',
        ];

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);
        $service->expects($this->atLeastOnce())
            ->method('setAsDefault')
            ->willReturn(true);


        $di = $this->getDi();
        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->set_default($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}

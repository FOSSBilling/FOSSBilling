<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

$availableCurrencies = [
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

test('get list', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $willReturn = [
        'list' => ['id' => 1],
    ];

    $qbStub = $this->createStub(\Doctrine\ORM\QueryBuilder::class);

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('getSearchQueryBuilder')
    ->atLeast()->once()
    ->andReturn($qbStub);

    $serviceMock = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $serviceMock
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $pager = Mockery::mock('\\' . \FOSSBilling\Pagination::class)->makePartial();
    $pager
    ->shouldReceive('paginateDoctrineQuery')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $di = container();
    $di['pager'] = $pager;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $result = $adminApi->get_list([]);

    expect($result)->toBeArray();
    expect($result['list'])->toBeArray();
});

test('get pairs', function () use ($availableCurrencies) {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $service = Mockery::mock(\Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getAvailableCurrencies')
    ->atLeast()->once()
    ->andReturn($availableCurrencies);

    $adminApi->setService($service);
    $result = $adminApi->get_pairs();
    expect($availableCurrencies)->toEqual($result);
    expect($result)->toBeArray();
});

test('get', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $model = Mockery::mock('\\' . \Box\Mod\Currency\Entity\Currency::class);
    $model
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 1.0,
            'format' => '€{{price}}',
            'price_format' => '€{{price}}',
        ]);

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('findOneByCode')
    ->atLeast()->once()
        ->with('EUR')
    ->andReturn($model);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $di = container();
    $data = [
        'code' => 'EUR',
    ];
    $adminApi->setService($service);
    $adminApi->setDi($di);
    $result = $adminApi->get($data);
    expect($result)->toBeArray();
    expect('EUR')->toEqual($result['code']);
});

test('get default', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $returnArr = [
        'code' => 'EUR',
        'title' => 'Euro',
        'conversion_rate' => 3.4528,
        'format' => '',
        'price_format' => '',
        'default' => true,
    ];

    $model = Mockery::mock('\\' . \Box\Mod\Currency\Entity\Currency::class);
    $model
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn($returnArr);

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('findDefault')
    ->atLeast()->once()
    ->andReturn($model);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $adminApi->setService($service);
    $result = $adminApi->get_default([]);

    expect($result)->toBeArray();
    expect($returnArr)->toEqual($result);
    expect($returnArr['code'])->toEqual('EUR');
    expect($returnArr['title'])->toEqual('Euro');
    expect($returnArr['conversion_rate'])->toBeFloat();
    expect($returnArr['conversion_rate'])->toEqual(3.4528);
    expect($returnArr['format'])->toEqual('');
    expect($returnArr['price_format'])->toEqual('');
    expect($returnArr['default'])->toBeTrue();
});

dataset('createException', [
    [
        [
            'code' => 'EUR',
            'format' => '€{{price}}',
        ],
        'atLeastOnce',
        'currency_exists',
        'never',
    ],
    [
        [
            'code' => 'NON',
            'format' => '€{{price}}',
        ],
        'atLeastOnce',
        null,
        'atLeastOnce',
    ],
]);

test('create exception', function ($data, $findOneByCodeCalled, $findOneByCodeReturn, $getAvailableCurrenciesCalled) use ($availableCurrencies) {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);

    if ($findOneByCodeReturn === 'currency_exists') {
        $findOneByCodeReturn = $this->createStub('\\' . \Box\Mod\Currency\Entity\Currency::class);
    }

    if ($findOneByCodeCalled === 'atLeastOnce') {
        $repositoryMock
            ->shouldReceive('findOneByCode')
            ->atLeast()->once()
            ->andReturn($findOneByCodeReturn);
    }

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    if ($getAvailableCurrenciesCalled === 'atLeastOnce') {
        $service
            ->shouldReceive('getAvailableCurrencies')
            ->atLeast()->once()
            ->andReturn($availableCurrencies);
    }

    $di = container();
    $adminApi->setService($service);
    $adminApi->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $adminApi->create($data);
})->with('createException');

test('create', function () use ($availableCurrencies) {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $data = [
        'code' => 'EUR',
        'format' => '€{{price}}',
    ];

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('findOneByCode')
    ->atLeast()->once()
    ->andReturn(null);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);
    $service
    ->shouldReceive('getAvailableCurrencies')
    ->atLeast()->once()
    ->andReturn($availableCurrencies);
    $service
    ->shouldReceive('createCurrency')
    ->atLeast()->once()
    ->andReturn($data['code']);

    $di = container();
    $adminApi->setService($service);
    $adminApi->setDi($di);

    $result = $adminApi->create($data);

    expect($result)->toBeString();
    expect(3)->toEqual(strlen($result));
    expect($data['code'])->toEqual($result);
});

test('update', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $data = [
        'code' => 'EUR',
        'format' => '€{{price}}',
        'title' => 'Euros',
        'price_format' => '€{{Price}}',
        'conversion_rate' => 0.6,
    ];

    $service = Mockery::mock(\Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('updateCurrency')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($service);

    $result = $adminApi->update($data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('delete exception', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $service = Mockery::mock(\Box\Mod\Currency\Service::class);
    $service->shouldReceive("deleteCurrencyByCode")->never();

    $apiHandler = new \Api_Handler(new \Model_Admin());
    $reflection = new \ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');
    $this->expectException(\FOSSBilling\InformationException::class);
    $method->invokeArgs($apiHandler, [$adminApi, 'delete', []]);

    $di = container();
    $di['validator'] = new \FOSSBilling\Validate();
    $adminApi->setDi($di);
    $adminApi->setService($service);
    $adminApi->delete([]);
});

test('delete', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $data = [
        'code' => 'EUR',
        'format' => '€{{price}}',
    ];

    $service = Mockery::mock(\Box\Mod\Currency\Service::class)->makePartial();
    $service
    ->shouldReceive('deleteCurrencyByCode')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $di['validator'] = new \FOSSBilling\Validate();
    $adminApi->setDi($di);
    $adminApi->setService($service);

    $result = $adminApi->delete($data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

dataset('setDefaultException', [
    [
        [
            'code' => 'EUR',
        ],
        'atLeastOnce',
        null,
    ],
]);

test('set default exception', function ($data, $findOneByCodeCalled, $findOneByCodeReturn) {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('findOneByCode')
    ->atLeast()->once()
    ->andReturn(null);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $di = container();
    $adminApi->setDi($di);

    $adminApi->setService($service);
    $this->expectException(\FOSSBilling\Exception::class);
    $adminApi->set_default($data);
})->with('setDefaultException');

test('set default', function () {
    $adminApi = new \Box\Mod\Currency\Api\Admin();

    $model = $this->createStub('\\' . \Box\Mod\Currency\Entity\Currency::class);

    $data = [
        'code' => 'EUR',
        'format' => '€{{price}}',
    ];

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('findOneByCode')
    ->atLeast()->once()
    ->andReturn($model);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);
    $service
    ->shouldReceive('setAsDefault')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($service);

    $result = $adminApi->set_default($data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

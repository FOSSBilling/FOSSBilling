<?php


namespace Box\Mod\Invoice;


use Symfony\Component\Yaml\Tests\B;

class ServiceTest extends \BBTestCase
{

    /**
     * @var \Box\Mod\Invoice\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Invoice\Service();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function dataForSearchQuery()
    {
        return array(
            array(array(), 'FROM invoice p', array()),
            array(
                array('order_id' => '1'),
                'AND pi.type = :item_type AND pi.rel_id = :order_id',
                array(
                    'item_type' => \Model_InvoiceItem::TYPE_ORDER,
                    'order_id'  => 1,
                )
            ),
            array(
                array('id' => 1),
                'AND p.id = :id',
                array(
                    'id' => 1,
                )
            ),
            array(
                array('nr' => 1),
                'AND (p.id = :id_nr OR p.nr = :id_nr)',
                array(
                    'id_nr' => 1,
                )
            ),
            array(
                array('approved' => true),
                'AND p.approved = :approved',
                array(
                    'approved' => true,
                )
            ),
            array(
                array('status' => 'unpaid'),
                'AND p.status = :status',
                array(
                    'status' => 'unpaid',
                )
            ),
            array(
                array('currency' => 'usd'),
                'AND p.currency = :currency',
                array(
                    'currency' => 'usd',
                )
            ),
            array(
                array('client_id' => 1),
                'AND p.client_id = :client_id',
                array(
                    'client_id' => 1,
                )
            ),
            array(
                array('client' => 'John'),
                'AND (cl.first_name LIKE :client_search OR cl.last_name LIKE :client_search OR cl.id = :client OR cl.email = :client)',
                array(
                    'client_search' => 'John%',
                    'client'        => 'John',
                )
            ),
            array(
                array('id' => 1),
                'AND p.id = :id',
                array(
                    'id' => 1,
                )
            ),
            array(
                array('created_at' => '1353715200'),
                "AND DATE_FORMAT(p.created_at, '%Y-%m-%d') = :created_at",
                array(
                    'created_at' => '1353715200',
                )
            ),
            array(
                array('date_from' => '1353715200'),
                'AND UNIX_TIMESTAMP(p.created_at) >= :date_from',
                array(
                    'date_from' => '1353715200',
                )
            ),
            array(
                array('date_to' => '1353715200'),
                'AND UNIX_TIMESTAMP(p.created_at) <= :date_to',
                array(
                    'date_to' => '1353715200',
                )
            ),
            array(
                array('paid_at' => 1353715200),
                "AND DATE_FORMAT(p.paid_at, '%Y-%m-%d') = :paid_at",
                array(
                    'paid_at' => 1353715200,
                )
            ),
            array(
                array('search' => 'trend'),
                'AND (p.id = :int OR p.nr LIKE :search_like OR p.id LIKE :search OR pi.title LIKE :search_like)',
                array(
                    'search'      => 'trend',
                    'search_like' => '%trend%',
                    'int'         => 0,
                )
            ),
        );
    }

    /**
     * @dataProvider dataForSearchQuery
     */
    public function testgetSearchQuery($data, $expectedStr, $expectedParams)
    {
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);
        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(strpos($result[0], $expectedStr) !== false, $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == array());
    }

    public function testtoApiArray()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getTax');

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getCompany');

        $subscriptionServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subscriptionServiceMock->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->will($this->returnValue(true));
        $modelToArrayResult = array(
            'id'                    => 1,
            'serie'                 => 'BB',
            'nr'                    => '0001',
            'serie_nr'              => 'BB0001',
            'hash'                  => 'hashedValue',
            'gateway_id'            => '',
            'taxname'               => '',
            'taxrate'               => '',
            'currency'              => '',
            'currency_rate'         => '',
            'status'                => '',
            'notes'                 => '',
            'text_1'                => '',
            'text_2'                => '',
            'due_at'                => '',
            'paid_at'               => '',
            'created_at'            => '',
            'updated_at'            => '',
            'buyer_first_name'      => '',
            'buyer_last_name'       => '',
            'buyer_company'         => '',
            'buyer_company_vat'     => '',
            'buyer_company_number'  => '',
            'buyer_address'         => '',
            'buyer_city'            => '',
            'buyer_state'           => '',
            'buyer_country'         => '',
            'buyer_phone'           => '',
            'buyer_phone_cc'        => '',
            'buyer_email'           => '',
            'buyer_zip'             => '',
            'seller_company_vat'    => '',
            'seller_company_number' => '',
        );
        $dbMock             = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue($modelToArrayResult));
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue('1W'));

        $periodMock = $this->getMockBuilder('\Box_Period')
            ->disableOriginalConstructor()
            ->getMock();
        $periodMock->expects($this->atLeastOnce())
            ->method('getUnit');
        $periodMock->expects($this->atLeastOnce())
            ->method('getQty');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($itemInvoiceServiceMock, $systemService, $subscriptionServiceMock) {
            $service = null;
            if ($sub == 'InvoiceItem') {
                $service = $itemInvoiceServiceMock;
            }
            if ($serviceName == 'system') {
                $service = $systemService;
            }
            if ($sub == 'Subscription') {
                $service = $subscriptionServiceMock;
            }

            return $service;
        });
        $di['period']      = $di->protect(function () use ($periodMock) { return $periodMock; });

        $this->service->setDi($di);

        $result = $this->service->toApiArray($invoiceModel);
        $this->assertIsArray($result);
    }

    public function testonAfterAdminInvoicePaymentReceived()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('toApiArray'))
            ->getMock();
        $arr         = array(
            'total'  => 1,
            'client' => array(
                'id' => 0,
            ),
        );
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue($arr));

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters');

        $emailService = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($invoiceModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }
        });
        $di['db']          = $dbMock;

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $this->service->onAfterAdminInvoicePaymentReceived($eventMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testonAfterAdminInvoiceReminderSent()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('toApiArray'))
            ->getMock();
        $arr         = array(
            'total'  => 1,
            'client' => array(
                'id' => 1,
            ),
        );
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue($arr));

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters');

        $emailService = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($invoiceModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }
        });
        $di['db']          = $dbMock;

        $this->service->setDi($di);
        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));


        $result = $this->service->onAfterAdminInvoicePaymentReceived($eventMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testonAfterAdminCronRun()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $remove_after_days = 64;
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('remove_after_days')
            ->willReturn($remove_after_days);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($systemServiceMock) {
            return $systemServiceMock;
        });
        $di['db']          = $dbMock;

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $this->service->onAfterAdminCronRun($eventMock);
    }

    public function testonEventAfterInvoiceIsDue()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('toApiArray'))
            ->getMock();
        $arr         = array(
            'total'  => 1,
            'client' => array(
                'id' => 1,
            ),
        );
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue($arr));

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $params    = array('days_passed' => 5, 'id' => 1);
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $emailService = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($invoiceModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }
        });
        $di['db']          = $dbMock;

        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));
        $result = $serviceMock->onEventAfterInvoiceIsDue($eventMock);
    }

    public function testmarkAsPaid()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('countIncome', 'getNextInvoiceNumber'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('countIncome');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getNextInvoiceNumber');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->status = \Model_Invoice::STATUS_UNPAID;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('markAsPaid');
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask');

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');

        $currencyService = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('getRateByCode');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                   = new \Box_Di();
        $di['mod_service']    = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock, $currencyService) {
            if ($serviceName == 'system') {
                return $systemService;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
            if ($serviceName == 'Currency') {
                return $currencyService;
            }
        });
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->markAsPaid($invoiceModel, true, true);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetNextInvoiceNumber()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->id = 2;
        $invoiceModel->nr = 2;

        $expected = $invoiceModel->id + 1;

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');
        $systemService->expects($this->atLeastOnce())
            ->method('setParamValue');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($invoiceModel));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });

        $this->service->setDi($di);

        $result = $this->service->getNextInvoiceNumber($invoiceModel);
        $this->assertIsInt($result);
        $this->assertEquals($expected, $result);
    }

    public function testcountIncome()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('getTotal'))
            ->getMock();

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $currencyService = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('toBaseCurrency');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($currencyService) {
            return $currencyService;
        });

        $serviceMock->setDi($di);
        $serviceMock->countIncome($invoiceModel);
    }

    public function testprepareInvoiceCurrencyWasNotDefined()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('setInvoiceDefaults'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('setInvoiceDefaults');


        $data = array(
            'gateway_id' => '',
            'text_1'     => '',
            'text_2'     => '',
            'items'      => array(
                array(
                    'id' => 1
                )
            ),
            'approve'
        );

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());

        $currencyService = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('getDefault')
            ->will($this->returnValue($currencyModel));

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNew');

        $newRecordId = 1;
        $dbMock      = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newRecordId));

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($invoiceModel));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $itemInvoiceServiceMock) {
            if ($serviceName == 'Currency') {
                return $currencyService;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
        });
        $di['logger']      = new \Box_Log();
        $di['array_get']   = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $serviceMock->setDi($di);
        $result = $serviceMock->prepareInvoice($clientModel, $data);
        $this->assertInstanceOf('Model_Invoice', $result);
    }

    public function testsetInvoiceDefaults()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $buyer         = array(
            'first_name'     => '',
            'last_name'      => '',
            'company'        => '',
            'company_vat'    => '',
            'company_number' => '',
            'address_1'      => '',
            'address_2'      => '',
            'city'           => '',
            'state'          => '',
            'country'        => '',
            'phone_cc'       => '',
            'phone'          => '',
            'email'          => '',
            'postcode'       => '',
        );
        $clientService = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue($buyer));

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $seller        = array(
            'name'       => '',
            'vat_number' => '',
            'number'     => '',
            'address_1'  => '',
            'address_2'  => '',
            'address_3'  => '',
            'tel'        => '',
            'email'      => '',
        );
        $systemService->expects($this->atLeastOnce())
            ->method('getCompany')
            ->will($this->returnValue($seller));
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue(0));

        $serviceTaxMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $serviceTaxMock->expects($this->atLeastOnce())
            ->method('getTaxRateForClient');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($clientModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($clientService, $systemService, $serviceTaxMock) {
            if ($serviceName == 'Client') {
                return $clientService;
            }
            if ($serviceName == 'system') {
                return $systemService;
            }
            if ($sub == 'Tax') {
                return $serviceTaxMock;
            }
        });

        $this->service->setDi($di);

        $this->service->setInvoiceDefaults($invoiceModel);
    }

    public function testapproveInvoice()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('tryPayWithCredits'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $data['use_credits'] = true;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->approveInvoice($invoiceModel, $data);
        $this->assertTrue($result);
    }

    public function testgetTotalWithTax()
    {
        $total       = 10;
        $tax         = 2.2;
        $expected    = $total + $tax;
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('getTotal', 'getTax'))
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('getTotal')
            ->will($this->returnValue($total));
        $serviceMock->expects($this->once())
            ->method('getTax')
            ->will($this->returnValue($tax));

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $result = $serviceMock->getTotalWithTax($invoiceModel);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetTotal()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();

        $itemTotal = 10;
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getTotal')
            ->will($this->returnValue($itemTotal));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($itemInvoiceServiceMock) { return $itemInvoiceServiceMock; });

        $this->service->setDi($di);
        $result = $this->service->getTotal($invoiceModel);
        $this->assertIsFloat($result);
        $this->assertEquals($itemTotal, $result);
    }

    public function testrefundInvoiceWithNegativeInvoiceLogic()
    {
        $newId       = 1;
        $total       = 10;
        $tax         = 2.2;
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('getTotal', 'getTax', 'countIncome', 'addNote', 'getNextInvoiceNumber'))
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('getTotal')
            ->will($this->returnValue($total));
        $serviceMock->expects($this->once())
            ->method('getTax')
            ->will($this->returnValue($tax));
        $serviceMock->expects($this->once())
            ->method('countIncome');
        $serviceMock->expects($this->exactly(3))
            ->method('addNote');
        $serviceMock->expects($this->once())
            ->method('getNextInvoiceNumber');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->id = $newId;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');


        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue('negative_invoice'));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->onConsecutiveCalls($invoiceModel, $invoiceItemModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['mod_service']    = $di->protect(function () use ($systemService) { return $systemService; });
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->refundInvoice($invoiceModel, 'custonNote');
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testupdateInvoice()
    {
        $data         = array(
            'gateway_id'            => '',
            'taxname'               => '',
            'taxrate'               => '',
            'status'                => '',
            'notes'                 => '',
            'text_1'                => '',
            'text_2'                => '',
            'due_at'                => '',
            'paid_at'               => '',
            'buyer_first_name'      => '',
            'buyer_last_name'       => '',
            'buyer_company'         => '',
            'buyer_company_vat'     => '',
            'buyer_company_number'  => '',
            'buyer_address'         => '',
            'buyer_city'            => '',
            'buyer_state'           => '',
            'buyer_country'         => '',
            'buyer_phone'           => '',
            'buyer_email'           => '',
            'buyer_zip'             => '',
            'seller_company'        => '',
            'seller_address'        => '',
            'seller_phone'          => '',
            'seller_email'          => '',
            'seller_company_vat'    => '',
            'seller_company_number' => '',
            'approved'              => '',
            'items'                 => array(0 => array()),
            'new_item'              => array('title' => 'new Item'),
        );
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNew');
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('update');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($invoiceItemModel));

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['mod_service']    = $di->protect(function () use ($itemInvoiceServiceMock) { return $itemInvoiceServiceMock; });
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->service->setDi($di);

        $result = $this->service->updateInvoice($invoiceModel, $data);
        $this->assertTrue($result);
    }

    public function testrmInvoice()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->rmInvoice($invoiceModel);
        $this->assertTrue($result);
    }

    public function testdeleteInvoiceByAdmin()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('rmInvoice'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('rmInvoice');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->deleteInvoiceByAdmin($invoiceModel);
        $this->assertTrue($result);
    }

    public function testdeleteInvoiceByClient()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('rmInvoice'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('rmInvoice');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->deleteInvoiceByClient($invoiceModel);
        $this->assertTrue($result);
    }

    public function testdeleteInvoiceByClientInvoiceIsRelatedToOrder()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->type   = \Model_InvoiceItem::TYPE_ORDER;
        $rel_id                   = 1;
        $invoiceItemModel->rel_id = $rel_id;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceItemModel)));

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventManagerMock;

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf("Invoice is related to order #%d. Please cancel order first.", $rel_id));
        $this->service->deleteInvoiceByClient($invoiceModel);
    }

    public function testrenewInvoice()
    {
        $newId        = 2;
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->id = $newId;

        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('generateForOrder', 'approveInvoice'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('approveInvoice');
        $serviceMock->expects($this->once())
            ->method('generateForOrder')
            ->will($this->returnValue($invoiceModel));

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->renewInvoice($clientOrder, array());
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testdoBatchPayWithCredits()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('findAllUnpaid', 'tryPayWithCredits'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findAllUnpaid')
            ->will($this->returnValue(array(array())));
        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($invoiceModel));

        $di           = new \Box_Di();
        $di['logger'] = new \Box_Log();
        $di['db']     = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->doBatchPayWithCredits(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpayInvoiceWithCredits()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('tryPayWithCredits'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $di           = new \Box_Di();
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->payInvoiceWithCredits($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgenerateForOrderInvoiceIsCreatedAlready()
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrder->unpaid_invoice_id = 2;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($invoiceModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->generateForOrder($clientOrder);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testgenerateForOrder()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('setInvoiceDefaults'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('setInvoiceDefaults');

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderModel->price           = 10;
        $orderModel->promo_recurring = true;

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($clientModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($invoiceModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $invoiceItemServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->getMock();
        $invoiceItemServiceMock->expects($this->atLeastOnce())
            ->method('generateFromOrder');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($invoiceItemServiceMock) {
            return $invoiceItemServiceMock;
        });

        $serviceMock->setDi($di);
        $result = $serviceMock->generateForOrder($orderModel);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testsgenerateForOrderAmountIsZero()
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrder->price = 0;

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Invoices are not generated for 0 amount orders');
        $this->service->generateForOrder($clientOrder);
    }

    public function testgenerateInvoicesForExpiringOrdersNoExpOrders()
    {
        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getSoonExpiringActiveOrders')
            ->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderService) { return $orderService; });

        $this->service->setDi($di);
        $result = $this->service->generateInvoicesForExpiringOrders();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgenerateInvoicesForExpiringOrders()
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $newId            = 4;
        $invoiceModel->id = $newId;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('generateForOrder', 'approveInvoice'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('approveInvoice');
        $serviceMock->expects($this->once())
            ->method('generateForOrder')
            ->will($this->returnValue($invoiceModel));

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getSoonExpiringActiveOrders')
            ->will($this->returnValue(array(array())));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($clientOrder));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderService) { return $orderService; });
        $di['logger']      = new \Box_Log();
        $di['db']          = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->generateInvoicesForExpiringOrders();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchPaidInvoiceActivation()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask')
            ->with($invoiceItemModel);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getAllNotExecutePaidItems')
            ->willReturn(array(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($invoiceItemModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($itemInvoiceServiceMock) { return $itemInvoiceServiceMock; });
        $di['logger']      = new \Box_Log();
        $di['db']          = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->doBatchPaidInvoiceActivation();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchPaidInvoiceActivationException()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask')
            ->with($invoiceItemModel)
            ->willThrowException(new \Box_Exception('tesitng exception..'));
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getAllNotExecutePaidItems')
            ->willReturn(array(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($invoiceItemModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($itemInvoiceServiceMock) { return $itemInvoiceServiceMock; });
        $di['logger']      = new \Box_Log();
        $di['db']          = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->doBatchPaidInvoiceActivation();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchRemindersSend()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('getUnpaidInvoicesLateFor', 'sendInvoiceReminder'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('sendInvoiceReminder');
        $serviceMock->expects($this->once())
            ->method('getUnpaidInvoicesLateFor')
            ->will($this->returnValue(array($invoiceModel)));

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->doBatchRemindersSend();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchInvokeDueEvent()
    {

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');
        $systemService->expects($this->atLeastOnce())
            ->method('setParamValue');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array(array())));

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['mod_service']    = $di->protect(function () use ($systemService) { return $systemService; });
        $di['logger']         = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->doBatchInvokeDueEvent(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsendInvoiceReminderProtectionFromAccidentalReminders()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->status = \Model_Invoice::STATUS_PAID;

        $result = $this->service->sendInvoiceReminder($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsendInvoiceReminder()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger']         = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->sendInvoiceReminder($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcounter()
    {
        $sqlResult = array(
            array('status'  => \Model_Invoice::STATUS_PAID,
                  'counter' => 2),
        );
        $dbMock    = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($sqlResult));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->counter();
        $this->assertIsArray($result);
    }

    public function testgenerateFundsInvoiceNoActiveOrder()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('You must have at least one active order before you can add funds so you cannot proceed at the current time!');
        $this->service->generateFundsInvoice($clientModel, 10);
    }

    public function testgenerateFundsInvoiceMinAmountLimit()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->currency = 'EUR';
        $fundsAmount           = 2;

        $minAmount     = 10;
        $maxAmount     = 50;
        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->onConsecutiveCalls($minAmount, $maxAmount));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($systemService) { return $systemService; });

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(981);
        $this->expectExceptionMessage('Amount is not valid');
        $this->service->generateFundsInvoice($clientModel, $fundsAmount);
    }

    public function testgenerateFundsInvoiceMaxAmountLimit()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->currency = 'EUR';
        $fundsAmount           = 200;

        $minAmount     = 10;
        $maxAmount     = 50;
        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->onConsecutiveCalls($minAmount, $maxAmount));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($systemService) { return $systemService; });

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(982);
        $this->expectExceptionMessage('Amount is not valid');
        $this->service->generateFundsInvoice($clientModel, $fundsAmount);
    }

    public function testgenerateFundsInvoice()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->currency = 'EUR';
        $fundsAmount           = 20;

        $minAmount = 10;
        $maxAmount = 50;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('setInvoiceDefaults'))
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('setInvoiceDefaults');

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->onConsecutiveCalls($minAmount, $maxAmount, true));

        $itemInvoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('generateForAddFunds');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($invoiceModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock) {
            if ('system' == $serviceName) {
                return $systemService;
            }
            if ('InvoiceItem' == $sub) {
                return $itemInvoiceServiceMock;
            }
        });
        $di['db']          = $dbMock;

        $serviceMock->setDi($di);

        $result = $serviceMock->generateFundsInvoice($clientModel, $fundsAmount);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testprocessInvoiceInvoiceNotFound()
    {
        $data = array(
            'hash'       => 'hashString',
            'gateway_id' => 2
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(812);
        $this->expectExceptionMessage('Invoice not found');
        $this->service->processInvoice($data);
    }

    public function testprocessInvoicePayGatewayNotFound()
    {
        $data = array(
            'hash'       => 'hashString',
            'gateway_id' => 2
        );

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(813);
        $this->expectExceptionMessage('Payment method not found');
        $this->service->processInvoice($data);
    }

    public function testprocessInvoicePayGatewayNotEnabled()
    {
        $data = array(
            'hash'       => 'hashString',
            'gateway_id' => 2
        );

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(814);
        $this->expectExceptionMessage('Payment method not enabled');
        $this->service->processInvoice($data);
    }

    public function testprocessInvoice()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')
            ->setMethods(array('getPaymentInvoice'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPaymentInvoice')
            ->willReturn(new \Payment_Invoice());
        $data = array(
            'hash'       => 'hashString',
            'gateway_id' => 2
        );

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());


        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());
        $payGatewayModel->enabled = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $subcribeService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subcribeService->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->will($this->returnValue(true));

        $payGatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $payGatewayService->expects($this->atLeastOnce())
            ->method('canPerformRecurrentPayment')
            ->will($this->returnValue(true));


        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Dummy')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('setDi');
        $adapterMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn(array());

        $payGatewayService->expects($this->atLeastOnce())
            ->method('getPaymentAdapter')
            ->will($this->returnValue($adapterMock));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subcribeService) {
            if ($sub == 'PayGateway') {
                return $payGatewayService;
            }
            if ($sub == 'Subscription') {
                return $subcribeService;
            }
        });
        $di['api_admin']   = new \Api_Handler(new \Model_Admin());
        $di['logger']      = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->processInvoice($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('service_url', $result);
        $this->assertArrayHasKey('subscription', $result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testaddNote()
    {
        $note = 'test Note';

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->addNote($invoiceModel, $note);
        $this->assertTrue($result);
    }

    public function testfindAllUnpaid()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock       = $this->getMockBuilder('\Box_Database')->getMock();
        $getAllResult = array(
            array(
                'id'        => 1,
                'client_id' => 1,
                'serie'     => 'BB',
                'nr'        => '00',
            ),
        );
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($getAllResult));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findAllUnpaid();
        $this->assertIsArray($result);
    }

    public function testfindAllPaid()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceModel)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findAllPaid();
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_Invoice', $result[0]);
    }


    public function testgetUnpaidInvoicesLateFor()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($invoiceModel)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getUnpaidInvoicesLateFor();
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_Invoice', $result[0]);
    }

    public function testgetBuyer()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $expected = array(
            'first_name' => '',
            'last_name'  => '',
            'company'    => '',
            'address'    => '',
            'city'       => '',
            'state'      => '',
            'country'    => '',
            'phone'      => '',
            'phone_cc'   => '',
            'email'      => '',
            'zip'        => '',
        );

        $result = $this->service->getBuyer($invoiceModel);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testisInvoiceTypeDeposit()
    {
        $di = new \Box_Di();

        $modelInvoiceItem = new \Model_InvoiceItem();
        $modelInvoiceItem->loadBean(new \RedBeanPHP\OODBBean());
        $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_DEPOSIT;

        $invoiceItems = array($modelInvoiceItem);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('InvoiceItem')
            ->willReturn($invoiceItems);

        $di['db'] = $dbMock;

        $modelInvoice = new \Model_Invoice();
        $modelInvoice->loadBean(new \RedBeanPHP\OODBBean());

        $this->service->setDi($di);
        $result = $this->service->isInvoiceTypeDeposit($modelInvoice);
        $this->assertTrue($result);
    }

    public function testisInvoiceTypeDeposit_TypeIsNotDeposit()
    {
        $di = new \Box_Di();

        $modelInvoiceItem = new \Model_InvoiceItem();
        $modelInvoiceItem->loadBean(new \RedBeanPHP\OODBBean());
        $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_ORDER;

        $invoiceItems = array($modelInvoiceItem);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('InvoiceItem')
            ->willReturn($invoiceItems);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $modelInvoice = new \Model_Invoice();
        $modelInvoice->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->isInvoiceTypeDeposit($modelInvoice);
        $this->assertFalse($result);
    }

    public function testisInvoiceTypeDeposit_EmptyArray()
    {
        $di = new \Box_Di();

        $invoiceItems = array();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('InvoiceItem')
            ->willReturn($invoiceItems);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $modelInvoice = new \Model_Invoice();
        $modelInvoice->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->isInvoiceTypeDeposit($modelInvoice);
        $this->assertFalse($result);
    }
}
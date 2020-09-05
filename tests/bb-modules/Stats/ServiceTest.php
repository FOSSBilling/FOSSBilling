<?php


namespace Box\Mod\Stats;

class PdoMock extends \PDO
{
    public function __construct (){}
}
class PdoStatmentsMock extends \PDOStatement
{
    public function __construct (){}
}

class ServiceTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Stats\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service= new \Box\Mod\Stats\Service();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetOrdersStatuses()
    {
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('counter')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $this->service->setDi($di);

        $result = $this->service->getOrdersStatuses(array());
        $this->assertIsArray($result);
    }

    public function testgetProductSummary()
    {
        $data = array();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getProductSummary($data);
        $this->assertIsArray($result);
    }

    public function testgetSummary()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $expected = array(
            'clients_total'       => null,
            'clients_today'       => null,
            'clients_yesterday'   => null,
            'clients_this_month'  => null,
            'clients_last_month'  => null,

            'orders_total'        => null,
            'orders_today'        => null,
            'orders_yesterday'    => null,
            'orders_this_month'   => null,
            'orders_last_month'   => null,

            'invoices_total'      => null,
            'invoices_today'      => null,
            'invoices_yesterday'  => null,
            'invoices_this_month' => null,
            'invoices_last_month' => null,

            'tickets_total'       => null,
            'tickets_today'       => null,
            'tickets_yesterday'   => null,
            'tickets_this_month'  => null,
            'tickets_last_month'  => null,
        );
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchColumn');

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $result = $this->service->getSummary();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetSummaryIncome()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchColumn');

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $expected = array(
            'total'      => null,
            'today'      => null,
            'yesterday'  => null,
            'this_month' => null,
            'last_month' => null,
        );

        $result = $this->service->getSummaryIncome();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetProductSales()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $res = array(
            'testProduct' => 1,
        );
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue($res));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $data = array(
            'date_from' => 'yesterday',
            'date_to' => 'now',
        );
        $result = $this->service->getProductSales($data);
        $this->assertIsArray($result);
    }

    public function testincomeAndRefundStats()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $res = array(
            array(
                'refund' => 0,
                'income' => 0
            )
        );
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue($res));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $result = $this->service->incomeAndRefundStats(array());
        $this->assertIsArray($result);
        $this->assertEquals($res[0], $result);
    }

    public function testgetRefunds()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'date_from' => 'yesterday',
            'date_to' => 'now',
        );
        $result = $this->service->getRefunds($data);
        $this->assertIsArray($result);
    }

    public function testgetIncome()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'date_from' => 'yesterday',
            'date_to' => 'now',
        );
        $result = $this->service->getIncome($data);
        $this->assertIsArray($result);
    }

    public function testgetClientCountries()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->getClientCountries(array());
        $this->assertIsArray($result);
    }

    public function testgetSalesByCountry()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->getSalesByCountry(array());
        $this->assertIsArray($result);
    }

    public function testgetTableStats()
    {
        $pdoStatmentMock = $this->getMockBuilder('\Box\Mod\Stats\PdoStatmentsMock')
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Stats\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatmentMock));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'date_from' => 'yesterday',
            'date_to' => 'now',
        );
        $result = $this->service->getTableStats('TableName', $data);
        $this->assertIsArray($result);
    }
}
 
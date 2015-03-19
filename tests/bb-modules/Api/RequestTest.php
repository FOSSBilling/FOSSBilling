<?php


namespace Box\Mod\Api;


class RequestTest extends \PHPUnit_Framework_TestCase
{
    public function testgetDi()
    {
        $di = new \Box_Di();
        $apiRequest = new Request();
        $apiRequest->setDi($di);
        $getDi = $apiRequest->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testRequest()
    {
        $apiRequest = new Request();
        $data = array(
            'id' => 1,
        );
        $apiRequest->setRequest($data);
        $result = $apiRequest->getRequest();
        $this->assertEquals($data, $result);
    }

    public function testget_GetWholeRequest()
    {
        $apiRequest = new Request();
        $data = array(
            'id' => 1,
            'product_id' => 2,
        );
        $apiRequest->setRequest($data);
        $result = $apiRequest->get();
        $this->assertEquals($data, $result);
    }

    public function testget_GetItem()
    {
        $apiRequest = new Request();
        $data = array(
            'id' => 1,
            'product_id' => 2,
        );
        $apiRequest->setRequest($data);
        $result = $apiRequest->get('product_id');
        $this->assertEquals($data['product_id'], $result);
    }

    public function testget_GetItem_DefaultValue()
    {
        $apiRequest = new Request();
        $data = array(
            'id' => 1,
            'product_id' => 2,
        );
        $defaultValue = 'none';
        $apiRequest->setRequest($data);
        $result = $apiRequest->get('shop_id', $defaultValue);
        $this->assertEquals($defaultValue, $result);
    }

    public function testget_GetItem_DefaultValueNotSet()
    {
        $apiRequest = new Request();
        $data = array(
            'id' => 1,
            'product_id' => 2,
        );
        $apiRequest->setRequest($data);
        $result = $apiRequest->get('shop_id');
        $this->assertNull($result);
    }
}

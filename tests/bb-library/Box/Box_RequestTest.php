<?php
/**
 * @group Core
 */
class Box_RequestTest extends PHPUnit\Framework\TestCase
{
    public function testDi()
    {
        $request = new Box_Request();
        $request->setDi(2);
        $res = $request->getDi();
        $this->assertEquals(2, $res);
    }

    public function testGet()
    {
        $r = array('key'=>'value');
        $_REQUEST = $r;
        $request = new Box_Request();
        $full_request = $request->get();
        $this->assertEquals($r, $full_request);

        $res = $request->get('key');
        $this->assertEquals('value', $res);

        $res = $request->get('non_existing', null, 'default');
        $this->assertEquals('default', $res);
    }

    public function testPost()
    {
        $r = array('key'=>'value');
        $_POST = $r;
        $request = new Box_Request();
        $full_request = $request->getPost();
        $this->assertEquals($r, $full_request);

        $res = $request->getPost('key');
        $this->assertEquals('value', $res);

        $res = $request->getPost('non_existing', null, 'default');
        $this->assertEquals('default', $res);
    }

    public function testQuery()
    {
        $r = array('key'=>'value');
        $_GET = $r;
        $request = new Box_Request();
        $full_request = $request->getQuery();
        $this->assertEquals($r, $full_request);

        $res = $request->getQuery('key');
        $this->assertEquals('value', $res);

        $res = $request->getQuery('non_existing', null, 'default');
        $this->assertEquals('default', $res);
    }

    public function testServer()
    {
        $_SERVER['key'] = 'value';
        $request = new Box_Request();

        $res = $request->getServer('key');
        $this->assertEquals('value', $res);

        $res = $request->getServer('non_existing');
        $this->assertNull($res);
    }

    public function testPut()
    {
        $input = 'example=1&foo=bar';

        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getRawBody'))
            ->getMock();
        $request->expects($this->atLeastOnce())
            ->method('getRawBody')
            ->will($this->returnValue($input));

        $full_request = $request->getPut();
        $this->assertEquals(array('example'=>'1', 'foo'=>'bar'), $full_request);

        $res = $request->getPut('foo');
        $this->assertEquals('bar', $res);

        $res = $request->getPut('non_existing', null, 'default');
        $this->assertEquals('default', $res);
    }

    public function testRawBody()
    {
        $request = new Box_Request();
        $full_request = $request->getRawBody();
        $this->assertIsString($full_request);
    }

    public function testHeaders()
    {
        $request = new Box_Request();
        $headers = $request->getHeaders();
        $this->assertIsArray($headers);
    }

    public function testGetMethod()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();
        $request->expects($this->atLeastOnce())
            ->method('getServer')
            ->will($this->returnValueMap(
                array(
                    array('REQUEST_METHOD', 'POST'),
                )
            ));

        $result = $request->getMethod();
        $this->assertEquals('POST', $result);
    }

    public function testHeader()
    {
        $input = array (
            'Host' => 'www.boxbilling.vm',
            'Connection' => 'keep-alive',
            'Content-Length' => '18',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36',
            'Origin' => 'chrome-extension://hgmloofddffdnphfgcellkdfbfbjeloo',
            'Content-Type' => 'application/xml',
            'Accept' => '*/*',
            'DNT' => '1',
            'Accept-Encoding' => 'gzip,deflate,sdch',
            'Accept-Language' => 'en-US,en;q=0.8,fr;q=0.6,id;q=0.4,lt;q=0.2,ms;q=0.2,nl;q=0.2,pl;q=0.2,pt;q=0.2,ru;q=0.2,es;q=0.2,nb;q=0.2,ro;q=0.2',
        );

        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getHeaders'))
            ->getMock();
        $request->expects($this->atLeastOnce())
            ->method('getHeaders')
            ->will($this->returnValue($input));

        $result = $request->getHeader('Content-Type');
        $this->assertEquals('application/xml', $result);

        $result = $request->getHeader('DNT');
        $this->assertEquals('1', $result);
    }

    public function testIs()
    {
        $request = $this->getMockBuilder(Box_Request::class)
            ->setMethods(['getMethod'])
            ->getMock();
        $request->expects($this->exactly(7))
                ->method('getMethod')
                ->willReturnOnConsecutiveCalls('POST', 'GET', 'PUT', 'PATCH', 'HEAD', 'DELETE', 'OPTIONS'); 

        $this->assertTrue($request->isPost());
        $this->assertTrue($request->isGet());
        $this->assertTrue($request->isPut());
        $this->assertTrue($request->isPatch());
        $this->assertTrue($request->isHead());
        $this->assertTrue($request->isDelete());
        $this->assertTrue($request->isOptions());
    }

    public function testIsMethods()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(['getMethod'])
            ->getMock();
        $request->expects($this->exactly(4))
                ->method('getMethod')
                ->willReturnOnConsecutiveCalls('POST', 'GET', 'GET', 'PUT');
                
        $this->assertTrue($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('POST'));
        $this->assertTrue($request->isMethod(array('POST', 'GET')));
        $this->assertFalse($request->isMethod(array('POST', 'GET')));
    }  

    public function testGetScheme()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->will($this->onConsecutiveCalls($this->returnValueMap(
                    	                    [
                                                ['HTTPS', '1'],
                                                ['HTTPS', null]
                
                                            ]        
                                        )));

        $this->assertEquals('https',  $request->getScheme());
        $this->assertEquals('http', $request->getScheme());
    }

    public function testIsAjax()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->will($this->onConsecutiveCalls($this->returnValueMap(
            [
                ['HTTP_X_REQUESTED_WITH', 'XMLHttpRequest'],
                ['HTTP_X_REQUESTED_WITH', null]
            ])));

        $this->assertTrue($request->isAjax());
        $this->assertFalse($request->isAjax());
    }

    public function testGetServerAddress()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->will($this->onConsecutiveCalls($this->returnValueMap([
                    ['SERVER_ADDR', '8.8.8.8'],
                    ['SERVER_ADDR', null]
                ]
            )
        ));

        $this->assertEquals('8.8.8.8', $request->getServerAddress());
        $this->assertEquals('127.0.0.1', $request->getServerAddress());
    }

    public function testGetServerName()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->will($this->onConsecutiveCalls($this->returnValueMap([
                    ['SERVER_NAME', 'google.com'],
                    ['SERVER_NAME', null]
                ]
            )
        ));

        $this->assertEquals('google.com', $request->getServerName());
        $this->assertEquals('localhost', $request->getServerName());
    }

    public function testGetJsonRawBody()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getRawBody'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getRawBody')
                ->will($this->onConsecutiveCalls('{"example":1}', 'ss'));

        $this->assertEquals(array('example'=>1), $request->getJsonRawBody());
        try {
            $this->assertTrue($request->getJsonRawBody());
            $this->fail('Should be invalid json');
        } catch(RuntimeException $e) {
            //ok invalid json
        }
    }

    public function testGetClientAddressForwarded()
    {
        $request = $this->getMockBuilder(Box_Request::class)
            ->setMethods(['getServer'])
            ->getMock();      

        $request->expects($this->exactly(2)) 
                ->method('getServer')
                ->withConsecutive(['HTTP_X_FORWARDED_FOR'], ['HTTP_X_FORWARDED_FOR'])
                ->will($this->onConsecutiveCalls(
                    '123.123.123.120',
                    '123.123.123.121, 123.123.123.122'
            )
        );

        $this->assertEquals('123.123.123.120', $request->getClientAddress());
        $this->assertEquals('123.123.123.121', $request->getClientAddress(true));
    }

    public function testGetClientAddress()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();
        
        $request->expects($this->exactly(2)) 
                ->method('getServer')
                ->withConsecutive(['REMOTE_ADDR'], ['REMOTE_ADDR'])
                ->will($this->onConsecutiveCalls(
                    '123.123.123.1',
                    '123.123.123.2, 123.123.123.122'
            )
        );

        $this->assertEquals('123.123.123.1', $request->getClientAddress(false));
        $this->assertEquals('123.123.123.2', $request->getClientAddress(false));
    }

    public function testGetUserAgent()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->withConsecutive(['HTTP_USER_AGENT'], ['HTTP_USER_AGENT'])
                ->willReturnOnConsecutiveCalls('Chrome', null);
                
        $this->assertEquals('Chrome', $request->getUserAgent());
        $this->assertNull($request->getUserAgent());
    }

    public function testGetURI()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->withConsecutive(['REQUEST_URI'], ['REQUEST_URI'])
                ->willReturnOnConsecutiveCalls('/foo/bar', null);

        $this->assertEquals('/foo/bar', $request->getURI());
        $this->assertNull($request->getURI());
    }

    public function testGetHttpHost()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();

        $request->expects($this->exactly(2))
                ->method('getServer')
                ->withConsecutive(['HTTP_HOST'], ['HTTP_HOST'])
                ->willReturnOnConsecutiveCalls('www.google.com', null);

        $this->assertEquals('www.google.com', $request->getHttpHost());
        $this->assertNull($request->getHttpHost());
    }


    public function testGetLanguages()
    {
        $request = $this->getMockBuilder('Box_Request')
            ->setMethods(array('getServer'))
            ->getMock();
        $request->expects($this->once())->method('getServer')->will($this->returnValueMap(
            [
                ['HTTP_ACCEPT_LANGUAGE', "en-US,en;q=0.8,fr;q=0.6,id;q=0.4,lt;q=0.2,ms;q=0.2,nl;q=0.2,pl;q=0.2,pt;q=0.2,ru;q=0.2,es;q=0.2,nb;q=0.2,ro;q=0.2"]
            ]));

        $result = array (
            0 =>
                array (
                    'language' => 'en-US',
                    'quality' => 1,
                ),
            1 =>
                array (
                    'language' => 'en',
                    'quality' => '0.8',
                ),
            2 =>
                array (
                    'language' => 'fr',
                    'quality' => '0.6',
                ),
            3 =>
                array (
                    'language' => 'id',
                    'quality' => '0.4',
                ),
            4 =>
                array (
                    'language' => 'lt',
                    'quality' => '0.2',
                ),
            5 =>
                array (
                    'language' => 'ms',
                    'quality' => '0.2',
                ),
            6 =>
                array (
                    'language' => 'nl',
                    'quality' => '0.2',
                ),
            7 =>
                array (
                    'language' => 'pl',
                    'quality' => '0.2',
                ),
            8 =>
                array (
                    'language' => 'pt',
                    'quality' => '0.2',
                ),
            9 =>
                array (
                    'language' => 'ru',
                    'quality' => '0.2',
                ),
            10 =>
                array (
                    'language' => 'es',
                    'quality' => '0.2',
                ),
            11 =>
                array (
                    'language' => 'nb',
                    'quality' => '0.2',
                ),
            12 =>
                array (
                    'language' => 'ro',
                    'quality' => '0.2',
                ),
        );
        $this->assertEquals($result, $request->getLanguages());
    }


}
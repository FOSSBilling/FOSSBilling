<?php

class Server_Manager_VirtualminTest extends PHPUnit_Framework_TestCase
{
    private function getClient()
    {
        $client = new Server_Client();
        $client
            ->setEmail('test@test.com')
            ->setFullName('Test name')
            ->setCompany('Test company')
            ->setStreet('Test address')
            ->setZip(12345)
            ->setCity('NY')
            ->setState('New York')
            ->setCountry('USA')
            ->setTelephone('123456789');

        return $client;
    }

    public function getPackage()
    {
        $p = new Server_Package();
        $p
            ->setCustomValues(array())
            ->setMaxFtp(null)
            ->setMaxSql(null)
            ->setMaxPop(null)
            ->setMaxSubdomains(null)
            ->setMaxParkedDomains(null)
            ->setMaxDomains(null)
            ->setBandwidth(null)
            ->setQuota(null)
            ->setName(null);

        return $p;
    }

    public function testChangeAccountPackage()
    {
        $account = new Server_Account();
        $account->setClient($this->getClient());
        $account->setPackage($this->getPackage());

        $serverMock = $this->getMockBuilder('Server_Manager_Virtualmin')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();
        $serverMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturn(array('status' => 'success'));

        $serverMock->createAccount($account);
        $serverMock->changeAccountPackage($account, $this->getPackage());
    }

}
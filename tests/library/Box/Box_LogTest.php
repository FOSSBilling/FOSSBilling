<?php
/**
 * @group Core
 */
class Box_LogTest extends PHPUnit\Framework\TestCase
{
    public function testLog()
    {
        $service_mock = $this->getMockBuilder(Box\Mod\Activity\Service::class)->getMock();
        $service_mock->expects($this->atLeastOnce())
            ->method('logEvent')
            ->will($this->returnValue(true));

        $writer1 = new Box_LogDb($service_mock);
        $writer2 = new Box_LogStream('php://output');
        $writer3 = new Box_LogStream(BB_PATH_LOG . '/test.log');

        $log = new Box_Log();
        $log->addWriter($writer1);
        $log->addWriter($writer2);
        $log->addWriter($writer3);

        $log->err('Test', array('admin_id'=>1, 'client_id'=>2));
        $log->err('Test 2', array('admin_id'=>3, 'client_id'=>4));

        $date = date('Y-m-d H:i:s');
        $outputString = $date." ERR (3): Test".PHP_EOL;
        $outputString .= $date." ERR (3): Test 2".PHP_EOL;
        $this->expectOutputString($outputString);
    }
}
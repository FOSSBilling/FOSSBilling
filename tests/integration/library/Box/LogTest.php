<?php

/**
 * @group Core
 */
class LogTest extends BBDbApiTestCase
{
    public function logWithoutParamsProvider()
    {
        return array(
            array('Test message'),
            array('Test message with param %s, but param not passed'),
        );
    }

    /**
     * @dataProvider logWithoutParamsProvider
     */
    public function testLogWithoutParams($msg)
    {
        $msg = 'Test message';
        $this->di['logger']->info($msg);

        $array   = $this->api_admin->activity_log_get_list();
        $log     = $array['list'][0]; //by default sorting by ID descending order so newest will always be first array item
        $message = $log['message'];

        $this->assertEquals($msg, $message);
    }


    public function testLogProvider()
    {
        $rand = rand(1, 100);
        $msg1 = 'No params in message, param passed';
        $msg2 = 'Test message with param %s';

        $this->assertTrue(($rand <= 100 && $rand > 0));

        return array(
            array($msg1, $rand, $msg1),
            array($msg2, $rand, 'Test message with param ' . $rand),
        );
    }

    /**
     * @dataProvider testLogProvider
     */
    public function testLogWithSingleParam($msg, $param, $expected)
    {
        $this->di['logger']->info($msg, $param);

        $array   = $this->api_admin->activity_log_get_list();
        $log     = $array['list'][0]; //by default sorting by ID descending order so newest will always be first array item
        $message = $log['message'];

        $this->assertEquals($expected, $message);
    }

    public function testLogMultipleVariables()
    {
        $msg  = '%sMultiple params in message, one param passed%s';
        $rand = rand(1, 100);
        $this->di['logger']->info($msg, $rand, $rand);
        $array   = $this->api_admin->activity_log_get_list();
        $log     = $array['list'][0]; //by default sorting by ID descending order so newest will always be first array item
        $message = $log['message'];

        $this->assertEquals(sprintf($msg, $rand, $rand), $message);
    }
    
}
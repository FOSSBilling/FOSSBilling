<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class LogTest extends BBDbApiTestCase
{
    public static function logWithoutParamsProvider()
    {
        return [
            ['Test message'],
            ['Test message with param %s, but param not passed'],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('logWithoutParamsProvider')]
    public function testLogWithoutParams($msg): void
    {
        $msg = 'Test message';
        $this->di['logger']->info($msg);

        $array = $this->api_admin->activity_log_get_list();
        $log = $array['list'][0]; // by default sorting by ID descending order so newest will always be first array item
        $message = $log['message'];

        $this->assertEquals($msg, $message);
    }

    public function testLogProvider()
    {
        $rand = random_int(1, 100);
        $msg1 = 'No params in message, param passed';
        $msg2 = 'Test message with param %s';

        $this->assertTrue($rand <= 100 && $rand > 0);

        return [
            [$msg1, $rand, $msg1],
            [$msg2, $rand, 'Test message with param ' . $rand],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('testLogProvider')]
    public function testLogWithSingleParam($msg, $param, $expected): void
    {
        $this->di['logger']->info($msg, $param);

        $array = $this->api_admin->activity_log_get_list();
        $log = $array['list'][0]; // by default sorting by ID descending order so newest will always be first array item
        $message = $log['message'];

        $this->assertEquals($expected, $message);
    }

    public function testLogMultipleVariables(): void
    {
        $msg = '%sMultiple params in message, one param passed%s';
        $rand = random_int(1, 100);
        $this->di['logger']->info($msg, $rand, $rand);
        $array = $this->api_admin->activity_log_get_list();
        $log = $array['list'][0]; // by default sorting by ID descending order so newest will always be first array item
        $message = $log['message'];

        $this->assertEquals(sprintf($msg, $rand, $rand), $message);
    }
}

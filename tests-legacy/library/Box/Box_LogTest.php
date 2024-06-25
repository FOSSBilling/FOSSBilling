<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_LogTest extends PHPUnit\Framework\TestCase
{
    protected Box_Log $log;
    protected array $valid_params;
    protected array $regression_test_params;
    protected int $regression_limit;

    // Setup mask params for testing
    protected function setUp(): void
    {
        $this->log = new Box_Log('DEBUG');        
        $valid_params = [
            'foo' => 'bar',
            'baz' => 'qux',
            'key' => 'VerySecretValuePleaseDoNotSteal!',
        ];
        $regression_test_params = [
            'foo' => [
                'bar' => [
                    'baz' => [
                        'lorem' => [
                            'ipsum' => [
                                'dolor' => [
                                    'sit' => [
                                        'amet' => [
                                            'foo' => [
                                                'bar' => [
                                                    'key' => 'VerySecretValuePleaseDoNotSteal!'
                                                ],
                                            ],
                                        ],
                                    ],
                                    'key' => 'SecondVerySecretValue',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->valid_params = $valid_params;
        $this->regression_test_params = $regression_test_params;
        $this->regression_limit = 8;

    }
    // test maskParams method
    public function testMaskParams(): void
    {
        // test normal masking with $this->valid_params and $log_message
        $log_message = 'This is a log message';
        $params = [];
        $params = array_merge($this->valid_params, ['message' => $log_message]);

        // target output
        $output = [
            'foo' => 'bar',
            'baz' => 'qux',
            'key' => '********',
            'message' => 'This is a log message',
        ];

        $this->assertEquals($output, $this->log->maskParams($params));
    }

    // test maskParams method with regression limit
    public function testMaskParamsWithRegressionLimit(): void
    {
        // test masking with regression limit
        $params = [];
        $params = $this->regression_test_params;

        // Expect FossBilling\Exception to be thrown
        $this->expectException(FOSSBilling\Exception::class);
        $this->log->maskParams($params, 0, $this->regression_limit);
        

    }
}



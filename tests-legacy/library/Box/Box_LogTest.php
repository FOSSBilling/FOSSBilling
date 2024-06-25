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
        $this->log = new Box_Log();
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
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->valid_params = $valid_params;
        $this->regression_test_params = $regression_test_params;
        $this->regression_limit = 10;

    }
    // test maskParams method
    public function testMaskParams(): void
    {
        $log = new Box_Log();
        // test normal masking
        $params = $this->valid_params;

        $this->assertEquals(
            [
                'foo' => [
                    'bar' => [
                        'baz' => '********',
                    ],
                ],
            ],
            $log->maskParams($params)
        );
    }

    // test maskParams method with regression limit
    public function testMaskParamsWithRegressionLimit(): void
    {
        $log = new Box_Log();
        // test masking with regression limit
        $params = $this->regression_test_params;

        $this->assertEquals(
            [
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
                                                        'key' => '********',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $log->maskParams($params, 0, $this->regression_limit)
        );
    }
}



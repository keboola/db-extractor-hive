<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\DbExtractor\Configuration\ConfigUtils;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ConfigUtilsTest extends TestCase
{
    public function testBase64Decode(): void
    {
        Assert::assertSame(
            'test',
            ConfigUtils::base64Decode(base64_encode('test'), 'foo.bar')
        );
    }

    public function testBase64DecodeInvalidInput(): void
    {
        $this->expectException(UserExceptionInterface::class);
        $this->expectExceptionMessage('Cannot base64 decode "foo.bar" parameter.');
        ConfigUtils::base64Decode('@#$#@%', 'foo.bar');
    }

    /**
     * @dataProvider getMergeParametersData
     */
    public function testMergeParameters(array $input, array $expected): void
    {
        Assert::assertEquals($expected, ConfigUtils::mergeParameters($input));
    }

    public function getMergeParametersData(): iterable
    {
        yield [
            [],
            ['parameters' => []],
        ];

        yield [
            [
                'parameters' => [
                    'a' => 'a1',
                    'b' => 'b1',
                    'c' => [
                        'd' => 'd1',
                        'e' => ['f', 'g'],
                    ],
                ],
            ],
            [
                'parameters' => [
                    'a' => 'a1',
                    'b' => 'b1',
                    'c' => [
                        'd' => 'd1',
                        'e' => ['f', 'g'],
                    ],
                ],
            ],
        ];

        yield [
            [
                'image_parameters' => [
                    'global_config' => [
                        'a' => 'a1',
                        'b' => 'b1',
                        'c' => [
                            'd' => 'd1',
                            'e' => ['f', 'g'],
                        ],
                    ],
                ],
            ],
            [
                'image_parameters' => [],
                'parameters' => [
                    'a' => 'a1',
                    'b' => 'b1',
                    'c' => [
                        'd' => 'd1',
                        'e' => ['f', 'g'],
                    ],
                ],
            ],
        ];

        yield [
            [
                'image_parameters' => [
                    'global_config' => [
                        'a' => 'A2',
                        'c' => [
                            'd' => 'D2',
                            'x' => 'X2',
                            'e' => ['X', 'Y', 'Z'],
                        ],
                    ],
                ],
                'parameters' => [
                    'a' => 'a1',
                    'b' => 'b1',
                    'c' => [
                        'd' => 'd1',
                        'e' => ['f', 'g'],
                    ],
                ],
            ],
            [
                'image_parameters' => [],
                'parameters' => [
                    'a' => 'A2',
                    'b' => 'b1',
                    'c' => [
                        'd' => 'D2',
                        'e' => ['X', 'Y', 'Z'],
                        'x' => 'X2',
                    ],
                ],
            ],
        ];
    }
}

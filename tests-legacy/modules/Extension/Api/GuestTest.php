<?php

declare(strict_types=1);

namespace Box\Mod\Extension\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    public function testSettingsReturnsPublicConfig(): void
    {
        $data = [
            'ext' => 'mod_staff',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->with($data['ext'])
            ->willReturn([
                'secret' => 'private-value',
                'public' => [
                    'theme' => 'default',
                ],
            ]);

        $api = new Guest();
        $api->setService($serviceMock);

        $result = $api->settings($data);

        $this->assertSame(['theme' => 'default'], $result);
    }

    public function testSettingsWithoutPublicConfigReturnsEmptyArray(): void
    {
        $data = [
            'ext' => 'mod_staff',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->with($data['ext'])
            ->willReturn([
                'secret' => 'private-value',
            ]);

        $api = new Guest();
        $api->setService($serviceMock);

        $result = $api->settings($data);

        $this->assertSame([], $result);
    }
}

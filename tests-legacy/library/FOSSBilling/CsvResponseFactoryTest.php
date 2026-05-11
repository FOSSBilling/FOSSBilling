<?php

declare(strict_types=1);

use FOSSBilling\Http\CsvResponseFactory;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class CsvResponseFactoryTest extends PHPUnit\Framework\TestCase
{
    public function testCreateBuildsCsvResponseWithSelectedHeaders(): void
    {
        $bean = new class {
            public function export(): array
            {
                return [
                    'id' => 1,
                    'email' => 'client@example.com',
                    'status' => 'active',
                ];
            }
        };

        $database = $this->createMock(Box_Database::class);
        $database->expects($this->once())
            ->method('findAll')
            ->with('client')
            ->willReturn([$bean]);

        $responseFactory = new CsvResponseFactory($database);
        $response = $responseFactory->create('client', 'clients.csv', ['id', 'email']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=clients.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame("id,email\n1,client@example.com\n", $response->getContent());
    }
}

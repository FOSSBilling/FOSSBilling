<?php

declare(strict_types=1);

use FOSSBilling\Http\RequestFactory;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class RequestFactoryTest extends PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        parent::tearDown();
    }

    public function testConfigureDoesNotTrustForwardedProtoWithoutTrustedProxyConfiguration(): void
    {
        $request = Request::create('http://billing.example.com/admin', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        RequestFactory::configure($request);

        $this->assertFalse($request->isSecure());
    }

    public function testConfigureTrustsForwardedProtoFromConfiguredTrustedProxy(): void
    {
        $request = Request::create('http://billing.example.com/admin', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
        ]);

        RequestFactory::configure($request, [
            'enabled' => true,
            'proxies' => ['198.51.100.10'],
            'headers' => 'x_forwarded',
        ]);

        $this->assertTrue($request->isSecure());
        $this->assertSame('billing.example.com', $request->getHost());
    }

    public function testConfigureIgnoresForwardedProtoFromUntrustedProxy(): void
    {
        $request = Request::create('http://billing.example.com/admin', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.99',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        RequestFactory::configure($request, [
            'enabled' => true,
            'proxies' => ['198.51.100.10'],
            'headers' => 'x_forwarded',
        ]);

        $this->assertFalse($request->isSecure());
    }

    public function testConfigureSupportsForwardedHeaderMode(): void
    {
        $request = Request::create('http://internal.example/admin', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_FORWARDED' => 'for=203.0.113.9;host=billing.example.com;proto=https',
        ]);

        RequestFactory::configure($request, [
            'enabled' => true,
            'proxies' => ['198.51.100.10'],
            'headers' => 'forwarded',
        ]);

        $this->assertTrue($request->isSecure());
        $this->assertSame('billing.example.com', $request->getHost());
    }

    public function testConfigureRejectsUnknownTrustedProxyHeaderMode(): void
    {
        $request = Request::create('http://billing.example.com/admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid trusted proxy header configuration.');

        RequestFactory::configure($request, [
            'enabled' => true,
            'proxies' => ['198.51.100.10'],
            'headers' => 'custom',
        ]);
    }

    public function testNormalizeRoutePathRewritesLegacyCustomPageRoute(): void
    {
        $request = Request::create('http://billing.example.com/page/about-us');

        $path = RequestFactory::normalizeRoutePath($request);

        $this->assertSame('/custompages/about-us', $path);
        $this->assertSame('/custompages/about-us', $request->query->get('_url'));
        $this->assertSame('/custompages/about-us', RequestFactory::getRoutePath($request));
    }

    public function testNormalizeRoutePathRejectsInvalidPathAndFallsBackToRoot(): void
    {
        $request = Request::create('http://billing.example.com/admin', 'GET', [
            '_url' => "invalid\x00path",
        ]);

        $path = RequestFactory::normalizeRoutePath($request);

        $this->assertSame('/', $path);
        $this->assertSame('/', RequestFactory::getRoutePath($request));
    }
}

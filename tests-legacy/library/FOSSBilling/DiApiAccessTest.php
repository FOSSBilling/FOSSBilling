<?php

declare(strict_types=1);

use FOSSBilling\Http\RequestFactory;
use FOSSBilling\Security\EmailValidationRequiredException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class DiApiAccessTest extends PHPUnit\Framework\TestCase
{
    private ?Request $requestBackup = null;

    protected function setUp(): void
    {
        global $request;
        $this->requestBackup = $request instanceof Request ? $request : null;
    }

    protected function tearDown(): void
    {
        global $request;
        $request = $this->requestBackup;
        parent::tearDown();
    }

    public function testClientApiAllowsUnvalidatedClientProfileRoutes(): void
    {
        $di = $this->createContainerForRoute('/api/client/profile/get');

        $api = $di['api']('client');

        $this->assertInstanceOf(Api_Handler::class, $api);
    }

    public function testClientApiBlocksUnvalidatedClientAccessOutsideAllowedRoutes(): void
    {
        $di = $this->createContainerForRoute('/api/client/invoice/get');

        $this->expectException(EmailValidationRequiredException::class);
        $di['api']('client');
    }

    private function createContainerForRoute(string $routePath): Pimple\Container
    {
        global $request;

        $request = Request::create('http://localhost' . $routePath, 'GET', ['_url' => $routePath]);
        RequestFactory::normalizeRoutePath($request);

        $di = require PATH_ROOT . '/di.php';
        $di['loggedin_client'] = new Model_Client();
        $di['is_client_email_validated'] = $di->protect(fn (): bool => false);

        return $di;
    }
}

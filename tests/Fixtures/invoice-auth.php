<?php

declare(strict_types=1);

// Run under the PHP web SAPI so the CLI authorization exemption is not active.
define('DEBUG', false);
define('PATH_CONFIG', dirname(__DIR__, 2) . '/src/config-sample.php');
require dirname(__DIR__, 2) . '/src/vendor/autoload.php';

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\InvoiceOperation;
use Box\Mod\Invoice\Service;
use FOSSBilling\Core\Exception\InformationException;

$checks = 0;
$di = new Pimple\Container();
$di['auth'] = new class {
    public function isAdminLoggedIn(): bool
    {
        return ($_GET['role'] ?? '') === 'staff';
    }

    public function isClientLoggedIn(): bool
    {
        return ($_GET['role'] ?? '') === 'owner';
    }
};
$di['loggedin_client'] = new class {
    public function getId(): int
    {
        return 7;
    }
};
$staff = new class($checks) {
    public function __construct(private int &$checks)
    {
    }

    public function checkPermissionsAndThrowException(string $module, string $permission): void
    {
        ++$this->checks;
        if ($module !== 'invoice' || $permission !== 'view' || ($_GET['allowed'] ?? '') !== '1') {
            throw new InformationException('Permission denied', code: 403);
        }
    }
};
$system = new class {
    public function getParamValue(string $name, string $default): string
    {
        return $_GET['hash'] ?? $default;
    }
};
$di['mod_service'] = $di->protect(fn (string $name): object => strtolower($name) === 'staff' ? $staff : $system);
$invoice = new Invoice();
$invoice->setClientId(7);
$invoice->setHashExpiresAt(new DateTime(($_GET['expired'] ?? '') === '1' ? '-1 day' : '+1 day'));
$service = new Service();
$service->setDi($di);

try {
    $operation = ($_GET['operation'] ?? '') === 'payment' ? InvoiceOperation::PAYMENT : InvoiceOperation::READ;
    $service->checkInvoiceAuth($invoice, $operation);
    $status = 200;
} catch (InformationException $e) {
    $status = $e->getCode();
}
header('Content-Type: application/json');
echo json_encode(['status' => $status, 'checks' => $checks, 'cli' => FOSSBilling\Core\System\Environment::isCLI()], JSON_THROW_ON_ERROR);

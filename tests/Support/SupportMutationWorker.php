<?php

declare(strict_types=1);

putenv('APP_ENV=test');
require __DIR__ . '/../../src/load.php';
require __DIR__ . '/../../src/vendor/autoload.php';

use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Service;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;

$db = DriverManager::getConnection(json_decode((string) getenv('SUPPORT_MUTATION_DB'), true, flags: JSON_THROW_ON_ERROR));
if ($db->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) {
    $db->executeStatement('PRAGMA busy_timeout = 5000');
}
$em = EntityManagerFactory::create($db);
$di = new Pimple\Container();
$di['em'] = $em;
$di['logger'] = new Psr\Log\NullLogger();
$di['request'] = Symfony\Component\HttpFoundation\Request::create('/');
$service = new Service();
$service->setDi($di);
$ticket = $em->find(SupportTicket::class, 1);
$message = $em->find(SupportTicketMessage::class, 1);
$admin = new Box\Mod\Staff\Entity\Admin();
(new ReflectionProperty($admin, 'id'))->setValue($admin, 9);
echo "ready\n";
if (trim((string) fgets(STDIN)) !== 'go') {
    throw new RuntimeException('Missing mutation barrier');
}
echo "writing\n";

try {
    match (getenv('SUPPORT_MUTATION_ACTION')) {
        'note' => $service->noteCreate($ticket, $admin, 'Private note'),
        'message' => $service->messageCreateForTicket($ticket, $admin, 'Direct reply'),
        'edit' => $service->ticketMessageUpdate($message, 'Edited reply', $admin),
    };
    echo json_encode(['completed' => true]) . "\n";
} catch (Throwable $error) {
    echo json_encode(['error' => $error::class, 'message' => $error->getMessage()]) . "\n";
}

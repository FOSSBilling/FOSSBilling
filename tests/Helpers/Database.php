<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\PaymentFenceMiddleware;

function fencedConfiguration(string $driver = 'pdo_sqlite'): Configuration
{
    $configuration = new Configuration();
    $configuration->setMiddlewares([new PaymentFenceMiddleware($driver)]);

    return $configuration;
}

function fencedConnection(array $params): Connection
{
    return DriverManager::getConnection($params, fencedConfiguration($params['driver']));
}

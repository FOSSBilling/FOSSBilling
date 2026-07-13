<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'load.php';

use FOSSBilling\Environment;
use Symfony\Component\Filesystem\Path;

$di = include Path::join(PATH_ROOT, 'di.php');

$di['translate']();

$success = false;

try {
    $interval = $argv[1] ?? null;
    $service = $di['mod_service']('cron');

    if (Environment::isCLI()) {
        echo "\e[33m- Welcome to FOSSBilling.\n";
        echo "\e[34mLast executed: {$service->getLastExecutionTime()}.\e[0m";
    }

    $success = $service->runCrons($interval);
} catch (Exception $exception) {
    throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
} finally {
    if (Environment::isCLI()) {
        echo $success
            ? "\e[32mSuccessfully ran the cron jobs.\e[0m"
            : "\e[31mCron jobs finished with failures. Check the logs for details.\e[0m" . PHP_EOL;
    }
}

// Surface partial failures (e.g. an isolated cron task that threw) via a non-zero exit code so
// system cron monitors relying on the process exit status can detect them.
if (Environment::isCLI() && !$success) {
    exit(1);
}

#!/usr/bin/env php
<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/tools/i18n/bootstrap.php';
exit(FOSSBilling\Tools\I18n\Application::run('modules', array_slice($argv, 1)));

<?php

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    echo 'Error: PHP version 8.3.0 or higher is required. You have version ' . PHP_VERSION;
    exit;
}
header('Location: ' . dirname((string) $_SERVER['PHP_SELF']) . '/install.php');
exit;

<?php

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    echo 'Error: PHP version 8.1.0 or higher is required. You have version ' . PHP_VERSION;
    exit;
}
header('Location: ' . pathinfo($_SERVER['PHP_SELF'], PATHINFO_DIRNAME) . '/install.php');
exit;

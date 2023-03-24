<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

const DIR_SEP = DIRECTORY_SEPARATOR;

class FOSSPatch_29 extends FOSSPatchAbstract
{
    public function patch()
    {
        $fileActions = [
            __DIR__ . DIR_SEP . 'vendor' . DIR_SEP . 'guzzlehttp' => 'unlink',
        ];
        $this->performFileActions($fileActions);
    }
}

/**
 * Patch to remove .html from email templates action code, see https://github.com/FOSSBilling/FOSSBilling/issues/863
 */
class FOSSPatch_28 extends FOSSPatchAbstract
{
    public function patch()
    {
        $q = "UPDATE email_template SET action_code = REPLACE(action_code, '.html', '')";
        $this->execSql($q);
    }
}

/**
 * Migration steps to create table to allow admin users to do password reset
 */
class FOSSPatch_27 extends FOSSPatchAbstract
{
    public function patch()
    {
        // create admin password reset table
        $q = "CREATE TABLE `admin_password_reset` ( `id` bigint(20) NOT NULL AUTO_INCREMENT, `admin_id` bigint(20) DEFAULT NULL, `hash` varchar(100) DEFAULT NULL, `ip` varchar(45) DEFAULT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `admin_id_idx` (`admin_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $this->execSql($q);
    }
}

/**
 * Migration steps from BoxBilling to FOSSBilling.
 */
class FOSSPatch_26 extends FOSSPatchAbstract
{
    public function patch()
    {
        //Added favicon settings
        $q = "INSERT INTO setting ('id', 'param', 'value', 'public', 'category', 'hash', 'created_at', 'updated_at') VALUES (29,'company_favicon','themes/huraga/assets/favicon.ico',0,NULL,NULL,'2023-01-08 12:00:00','2023-01-08 12:00:00');";
        $this->execSql($q);
    }
}

class FOSSPatch_25 extends FOSSPatchAbstract
{
    public function patch()
    {
        //Migrate email templates to be compatible with Twig 3.x
        $q = "UPDATE email_template SET content = REPLACE(content, '{% filter markdown %}', '{% apply markdown %}')";
        $this->execSql($q);

        $q = "UPDATE email_template SET content = REPLACE(content, '{% endfilter %}', '{% endapply %}')";
        $this->execSql($q);
    }
}

abstract class FOSSPatchAbstract
{
    protected mixed $pdo;
    private int $version;
    private string $k = 'last_patch';
    private array $fileActions = [];

    public function __construct($di)
    {
        $this->di = $di;
        $this->pdo = $di['pdo'];
        $c = static::class;
        $this->version = (int) substr($c, strpos($c, '_') + 1);
    }

    abstract public function patch();

    public function donePatching(): void
    {
        $this->setParamValue($this->k, $this->version);
    }

    protected function execSql($sql): void
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isPatched(): bool
    {
        return $this->getParamValue($this->k, 0) >= $this->version;
    }

    private function setParamValue($param, $value): void
    {
        if (is_null($this->getParamValue($param))) {
            $query = 'INSERT INTO setting (param, value, public, updated_at, created_at) VALUES (:param, :value, 1, :u, :c)';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value, 'c' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s')]);
        } else {
            $query = 'UPDATE setting SET value = :value, updated_at = :u WHERE param = :param';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value, 'u' => date('Y-m-d H:i:s')]);
        }
    }

    private function getParamValue($param, $default = null)
    {
        $query = 'SELECT value
                FROM setting
                WHERE param = :param
               ';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['param' => $param]);
        $r = $stmt->fetchColumn();
        if (false === $r) {
            return $default;
        }

        return $r;
    }

    protected function performFileActions(array $files)
    {
        foreach ($files as $file => $action) {
            $relPath = str_replace(__DIR__, '', $file);
            if ($action == 'unlink' && file_exists($file) && !is_dir($file)) {
                @unlink($file);
                $this->fileActions[] = "<strong>Deleted:</strong> <em>$relPath</em>";
            } elseif ($action == 'unlink' && is_dir($file)) {
                @$this->emptyFolder($file);
                @rmdir($file);
                $this->fileActions[] = "<strong>Deleted:</strong> <em>$relPath</em>";
            } elseif (file_exists($file)) {
                @rename($file, $action);
                $this->fileActions[] = "<strong>Moved:</strong> <em>$relPath</em> to <em>" . str_replace(__DIR__, '', $action . "</em>");
            } else {
                //$this->fileActions[] = "<strong>Error:</strong> $relPath does not exist";
            }
        }
    }

    public function getFileActions()
    {
        return (!$this->fileActions) ? ['None'] : $this->fileActions;
    }

    private function emptyFolder($folder)
    {
        /* Original source for this lovely codesnippet: https://stackoverflow.com/a/24563703
         * With modification suggested from KeineMaster (replaced $file with$file->getRealPath())
         */
        if (file_exists($folder)) {
            $di = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($ri as $file) {
                $file->isDir() ?  rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
        }
    }
}

$patches = [];
foreach (get_declared_classes() as $class) {
    if (str_contains($class, 'FOSSPatch_')) {
        $patches[] = $class;
    }
}

require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';
natsort($patches);
?>

<!DOCTYPE html>
<html>

<head>
    <title>FOSSBilling Updater</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>FOSSBilling Updater</h1>
    </div>
    <div class="container">
        <table>
            <thead>
                <tr>
                    <th>Patch Number</th>
                    <th>Status</th>
                    <th>File Actions Performed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patches as $class) {
                    $p = new $class($di);
                    if (!$p->isPatched()) {
                        $p->patch();
                        $p->donePatching();

                        $version = $p->getVersion();
                        $fileActions = $p->getFileActions();
                ?>
                        <tr>
                            <td><?php echo $version ?></td>
                            <td>Executed</td>
                            <td>
                                <ul>
                                    <?php foreach ($fileActions as $action) {
                                        echo '<li><p>' . $action . '</p></li>';
                                    } ?>
                                </ul>
                            </td>
                        </tr>
                <?php
                    }
                } ?>
            </tbody>
        </table>
        <p>Update completed. You are using FOSSBilling <strong><?php echo Box_Version::VERSION ?></strong></p>
    </div>
</body>
</html>

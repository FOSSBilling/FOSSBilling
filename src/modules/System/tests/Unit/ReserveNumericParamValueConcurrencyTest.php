<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/*
 * reserveNextNumericParamValue() must never hand two callers the same counter value. On SQLite
 * that guarantee depends entirely on reserveNumericParamValue() driving a raw BEGIN IMMEDIATE:
 * a mocked Connection can assert BEGIN IMMEDIATE was *called*, but it can't prove that call
 * actually serializes concurrent readers the way FOR UPDATE does on MySQL/PostgreSQL - only real
 * contention on a real on-disk database file can (see concurrency-test-invariants: assert the
 * observable invariant, not the engine mechanism).
 *
 * This spawns real child PHP processes - not just separate Connection objects in one process -
 * each reserving from the same counter in a shared SQLite file, then asserts the combined results
 * are exactly the expected contiguous range: no two processes ever got the same number, and none
 * were skipped.
 */
test('reserveNextNumericParamValue never hands out a duplicate value under real concurrent SQLite access', function (): void {
    $workerCount = 6;
    $reservationsPerWorker = 15;
    $totalReservations = $workerCount * $reservationsPerWorker;

    $dbPath = Path::join(sys_get_temp_dir(), 'fossbilling-reserve-numeric-concurrency-' . bin2hex(random_bytes(4)) . '.sqlite');
    $workerScriptPath = Path::join(sys_get_temp_dir(), 'fossbilling-reserve-numeric-concurrency-worker-' . bin2hex(random_bytes(4)) . '.php');
    $filesystem = new Filesystem();

    // A standalone script rather than reusing Service::setDi(): setDi() also wires up the
    // EntityManager for unrelated settings CRUD this test doesn't exercise, and pulling that in
    // would require the full application bootstrap in every worker process. Reflection sets the
    // protected $di property directly, bypassing setDi() - reserveNextNumericParamValue() only
    // ever touches $this->di['dbal'].
    $filesystem->dumpFile($workerScriptPath, <<<'PHP'
        <?php
        require $argv[1];
        [, , $dbPath, $count] = $argv;
        $connection = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbPath]);
        $service = new \Box\Mod\System\Service();
        (new ReflectionProperty($service, 'di'))->setValue($service, new \Pimple\Container(['dbal' => $connection]));

        $results = [];
        for ($i = 0; $i < (int) $count; $i++) {
            $results[] = $service->reserveNextNumericParamValue('concurrency_counter');
        }

        fwrite(STDOUT, implode(',', $results));
        PHP);

    try {
        $setupConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbPath]);
        $setupConnection->executeStatement(
            'CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, param VARCHAR(255) UNIQUE, value TEXT, created_at TEXT, updated_at TEXT)'
        );
        $setupConnection->executeStatement(
            "INSERT INTO setting (param, value, created_at, updated_at) VALUES ('concurrency_counter', '0', '2026-01-01 00:00:00', '2026-01-01 00:00:00')"
        );

        $autoloadPath = Path::join(PATH_ROOT, 'vendor', 'autoload.php');
        $handles = [];
        foreach (range(1, $workerCount) as $ignored) {
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open(
                [PHP_BINARY, $workerScriptPath, $autoloadPath, $dbPath, (string) $reservationsPerWorker],
                $descriptors,
                $pipes
            );
            expect($process)->not->toBeFalse();
            $handles[] = ['process' => $process, 'pipes' => $pipes];
        }

        $reserved = [];
        $stderrOutput = '';
        foreach ($handles as $handle) {
            $stdout = stream_get_contents($handle['pipes'][1]);
            $stderrOutput .= stream_get_contents($handle['pipes'][2]);
            fclose($handle['pipes'][1]);
            fclose($handle['pipes'][2]);
            proc_close($handle['process']);

            foreach (explode(',', trim($stdout)) as $value) {
                if ($value !== '') {
                    $reserved[] = (int) $value;
                }
            }
        }

        expect($stderrOutput)->toBe('');
        expect($reserved)->toHaveCount($totalReservations);

        sort($reserved);
        expect($reserved)->toBe(range(0, $totalReservations - 1));
    } finally {
        $filesystem->remove([$dbPath, $workerScriptPath]);
    }
});

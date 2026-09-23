<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use FOSSBilling\Doctrine\EntityManagerFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('entity definitions hash is stable and node-independent', function (): void {
    // Same contents in different directories hash identically: absolute paths and mtimes must
    // not leak in, or load-balanced nodes would perpetually disagree and re-run the ambient
    // schema sync after every other node.
    $root = Path::join(sys_get_temp_dir(), 'fossbilling-entity-hash-' . bin2hex(random_bytes(8)));
    $dirA = Path::join($root, 'node-a', 'Entity');
    $dirB = Path::join($root, 'node-b', 'Entity');

    $filesystem = new Filesystem();
    $filesystem->mkdir([$dirA, $dirB]);

    try {
        $filesystem->dumpFile(Path::join($dirA, 'Invoice.php'), "<?php\n// v1\n");
        $filesystem->dumpFile(Path::join($dirB, 'Invoice.php'), "<?php\n// v1\n");
        touch(Path::join($dirB, 'Invoice.php'), time() - 3600);

        expect(EntityManagerFactory::entityDefinitionsHash([$dirA]))->toBe(EntityManagerFactory::entityDefinitionsHash([$dirB]));

        $filesystem->dumpFile(Path::join($dirB, 'Invoice.php'), "<?php\n// v2\n");

        expect(EntityManagerFactory::entityDefinitionsHash([$dirA]))->not->toBe(EntityManagerFactory::entityDefinitionsHash([$dirB]));
    } finally {
        $filesystem->remove($root);
    }
});

test('entity definitions hash covers the real entity tree deterministically', function (): void {
    $first = EntityManagerFactory::entityDefinitionsHash();

    expect($first)->toBe(EntityManagerFactory::entityDefinitionsHash())
        ->and($first)->toMatch('/^[0-9a-f]{32}$/');
});

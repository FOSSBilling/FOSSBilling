<?php

declare(strict_types=1);

namespace FOSSBilling\Tools\I18n;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class TemporaryDirectory
{
    public static function run(callable $action): mixed
    {
        $filesystem = new Filesystem();
        $path = Path::join(sys_get_temp_dir(), 'fossbilling-i18n-' . bin2hex(random_bytes(12)));
        $filesystem->mkdir($path);

        try {
            return $action($path);
        } finally {
            $filesystem->remove($path);
        }
    }
}

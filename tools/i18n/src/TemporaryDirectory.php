<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

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

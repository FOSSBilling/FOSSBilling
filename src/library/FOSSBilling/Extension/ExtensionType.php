<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension;

use Symfony\Component\Filesystem\Path;

/**
 * The types of extension installed under PATH_EXTENSIONS.
 *
 * The case value is the one identifier for a type: it is the "type" recorded
 * against an installed extension, the type declared by an extension itself, and
 * the type used by the extension directory API. It is singular, as is the
 * namespace segment derived from it. Only the directory is plural, being a
 * folder of many extensions of that type.
 */
enum ExtensionType: string
{
    case Gateway = 'gateway';
    case Registrar = 'registrar';
    case Manager = 'manager';

    /**
     * The namespace segment following FOSSBilling\Extension\ for this type.
     */
    public function namespaceSegment(): string
    {
        return ucfirst($this->value);
    }

    public static function fromNamespaceSegment(string $segment): ?self
    {
        return self::tryFrom(lcfirst($segment));
    }

    /**
     * The directory holding every installed extension of this type.
     */
    public function directory(): string
    {
        return Path::join(PATH_EXTENSIONS, $this->value . 's');
    }

    public function pathFor(string $id): string
    {
        return Path::join($this->directory(), $id);
    }

    public function classFor(string $id): string
    {
        return 'FOSSBilling\\Extension\\' . $this->namespaceSegment() . '\\' . $id . '\\' . $id;
    }
}

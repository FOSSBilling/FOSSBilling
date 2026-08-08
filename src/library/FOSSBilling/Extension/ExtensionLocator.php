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

use FOSSBilling\InformationException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * Discovers the extensions installed under PATH_EXTENSIONS.
 *
 * An extension is a directory named after its ID containing a like-named class
 * file, so gateways/Stripe/Stripe.php provides the "Stripe" payment gateway.
 */
final class ExtensionLocator
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * The IDs of every installed extension of the given type, sorted by name.
     *
     * @return string[]
     */
    public function listInstalled(ExtensionType $type): array
    {
        $finder = new Finder();

        try {
            $finder->directories()->in($type->directory())->depth('== 0')->sortByName();
        } catch (DirectoryNotFoundException) {
            return [];
        }

        $ids = [];
        foreach ($finder as $directory) {
            $id = $directory->getFilename();
            if ($this->isInstalled($type, $id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Whether the given extension is present on the disk.
     *
     * IDs reach this class from admin-supplied input, so the identifier is
     * validated before it is used to build a path.
     */
    public function isInstalled(ExtensionType $type, string $id): bool
    {
        if (preg_match('/\A[A-Za-z0-9_]+\z/', $id) !== 1) {
            return false;
        }

        $directory = $type->pathFor($id);

        if (!$this->filesystem->exists(Path::join($directory, $id . '.php'))) {
            return false;
        }

        // A bundle whose manifest is missing, malformed, or written against a
        // different extension API is not offered rather than failing later.
        try {
            Manifest::fromDirectory($directory, $id, $type);
        } catch (InformationException) {
            return false;
        }

        return true;
    }

    /**
     * The manifest describing an installed extension.
     *
     * @throws InformationException if the manifest is missing or unusable
     */
    public function manifest(ExtensionType $type, string $id): Manifest
    {
        return Manifest::fromDirectory($type->pathFor($id), $id, $type);
    }

    /**
     * The class implementing the given extension.
     *
     * @throws InformationException if the extension is not installed
     */
    public function resolveClass(ExtensionType $type, string $id): string
    {
        if (!$this->isInstalled($type, $id)) {
            throw new InformationException('The :type extension ":id" was not found.', [':type' => $type->value, ':id' => $id]);
        }

        $class = $type->classFor($id);
        if (!class_exists($class)) {
            throw new InformationException('The :type extension ":id" does not declare the expected class.', [':type' => $type->value, ':id' => $id]);
        }

        return $class;
    }
}

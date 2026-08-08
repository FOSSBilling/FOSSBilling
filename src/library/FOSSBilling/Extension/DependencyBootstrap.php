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

use Symfony\Component\Filesystem\Filesystem;

/**
 * Installs the dependencies of every extension that is missing them.
 *
 * Run after core's own composer install, so that a fresh checkout ends up with
 * a working tree, and again after an extension is added to the disk by hand.
 */
final class DependencyBootstrap
{
    public function __construct(
        private readonly ExtensionLocator $locator,
        private readonly DependencyInstaller $installer,
    ) {
    }

    /**
     * @param callable(string):void|null $report receives a line of progress per extension
     *
     * @return array<string, string> failures, keyed by "type/id"
     */
    public function installMissing(?callable $report = null): array
    {
        $report ??= static function (string $line): void {};
        $failures = [];

        foreach (ExtensionType::cases() as $type) {
            foreach ($this->locator->listInstalled($type) as $id) {
                $directory = $type->pathFor($id);
                $label = $type->value . '/' . $id;

                if (!$this->installer->hasDependencies($directory)) {
                    continue;
                }

                if ($this->installer->isInstalled($directory)) {
                    $report("{$label}: already installed");

                    continue;
                }

                $report("{$label}: installing dependencies");

                try {
                    $this->installer->install($directory);
                    $report("{$label}: done");
                } catch (\Throwable $e) {
                    $failures[$label] = $e->getMessage();
                    $report("{$label}: FAILED - " . $e->getMessage());
                }
            }
        }

        return $failures;
    }

    /**
     * Build an instance without a container, for use during bootstrap.
     */
    public static function create(): self
    {
        $filesystem = new Filesystem();

        return new self(new ExtensionLocator($filesystem), new DependencyInstaller($filesystem));
    }
}

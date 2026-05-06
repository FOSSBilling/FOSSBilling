<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Commands;

use FOSSBilling\Environment;
use FOSSBilling\UpdatePatcher;
use FOSSBilling\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'system:run-patcher',
    description: 'Runs update patches and config migrations',
    hidden: false
)]
class RunPatcher extends Command implements \FOSSBilling\InjectionAwareInterface
{
    protected $di;

    public function setDi($di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!Environment::isCLI()) {
            $output->writeln('<error>This command can only be run from the CLI.</error>');

            return Command::FAILURE;
        }

        $patcher = new UpdatePatcher();
        $patcher->setDi($this->di);

        $version = Version::VERSION;
        $cacheItem = $this->di['cache']->getItem('updatePatcher');
        if ($cacheItem->isHit() && $version === $cacheItem->get() && $patcher->availablePatches() === 0) {
            $output->writeln('<info>The update patcher has already been run for this version.</info>');

            return Command::SUCCESS;
        }

        try {
            $output->writeln('Applying config patches...');
            $patcher->applyConfigPatches();

            $output->writeln('Applying core patches...');
            $patcher->applyCorePatches();

            $filesystem = new Filesystem();
            $cachePath = Path::normalize($this->di['config']['path_data'] . '/cache');
            $filesystem->remove($cachePath);
            $filesystem->mkdir($cachePath);

            $this->di['cache']->getItem('updatePatcher')->set(Version::VERSION);

            $output->writeln('<info>All patches have been applied and the cache has been cleared.</info>');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>An error occurred while applying patches: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }
    }
}

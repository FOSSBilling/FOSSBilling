<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Imageproxy\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to revert proxified image URLs back to originals.
 */
#[AsCommand(
    name: 'imageproxy:revert',
    description: 'Revert proxified image URLs back to their original URLs',
    hidden: false
)]
class RevertProxified extends Command implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    /**
     * Set dependency injection container.
     *
     * @param \Pimple\Container $di Dependency injection container
     */
    public function setDi($di): void
    {
        $this->di = $di;
    }

    /**
     * Get dependency injection container.
     *
     * @return \Pimple\Container|null Dependency injection container
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Imageproxy: Revert Proxified URLs');
        $io->warning('This will revert all proxified image URLs back to their original URLs.');
        $io->info('Scanning all ticket messages for proxified images...');

        /** @var \Box\Mod\Imageproxy\Service $service */
        $service = $this->di['mod_service']('imageproxy');
        $stats = $service->revertAllProxifiedUrls();

        $io->success('Reversion completed!');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Messages Processed', $stats['processed']],
                ['Messages Reverted', $stats['reverted']],
            ]
        );

        return Command::SUCCESS;
    }
}


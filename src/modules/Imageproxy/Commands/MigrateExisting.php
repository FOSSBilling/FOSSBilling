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
 * Console command to migrate existing ticket messages to use proxified image URLs.
 */
#[AsCommand(
    name: 'imageproxy:migrate-existing',
    description: 'Migrate existing ticket messages to use proxified image URLs',
    hidden: false
)]
class MigrateExisting extends Command implements \FOSSBilling\InjectionAwareInterface
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

        $io->title('Imageproxy: Migrate Existing Tickets');
        $io->info('Scanning all ticket messages for remote images...');

        /** @var \Box\Mod\Imageproxy\Service $service */
        $service = $this->di['mod_service']('imageproxy');
        $stats = $service->migrateExistingTickets();

        $io->success('Migration completed!');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Messages Processed', $stats['processed']],
                ['Messages with Images', $stats['images_found']],
                ['Messages Updated', $stats['updated']],
            ]
        );

        return Command::SUCCESS;
    }
}


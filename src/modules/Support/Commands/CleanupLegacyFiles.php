<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support\Commands;

use Box\Mod\Support\TicketFiles;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use Pimple\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'support:files:cleanup-legacy', description: 'Remove orphaned attachments from before upload recovery markers')]
final class CleanupLegacyFiles extends Command implements InjectionAwareInterface
{
    private ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    protected function configure(): void
    {
        $this->addOption('inventory', null, InputOption::VALUE_NONE, 'Print a read-only JSON inventory of up to 500 candidates')
            ->addOption('manifest', null, InputOption::VALUE_REQUIRED, 'Path to the reviewed inventory JSON')
            ->addOption('legacy-uploads-drained', null, InputOption::VALUE_NONE, 'Confirm all legacy attachment writers have stopped and all their requests have finished')
            ->setHelp('Before using this command, upgrade or stop every legacy attachment writer on every node, and wait for all legacy requests and transactions to finish. Keep legacy writers stopped while this command runs. Generate and review another inventory for each batch.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('inventory')) {
            $output->writeln(json_encode(TicketFiles::legacyOrphanInventory($this->di), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }
        if (!$input->getOption('legacy-uploads-drained')) {
            $output->writeln('<error>Stop all legacy attachment writers and drain their requests, then pass --legacy-uploads-drained.</error>');

            return Command::INVALID;
        }
        $manifest = $input->getOption('manifest');
        if (!is_string($manifest) || $manifest === '') {
            $output->writeln('<error>Generate --inventory, review it, then pass --manifest=path with the drain confirmation.</error>');

            return Command::INVALID;
        }
        $inventory = json_decode((new \Symfony\Component\Filesystem\Filesystem())->readFile($manifest), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($inventory)) {
            throw new \InvalidArgumentException('Invalid legacy attachment inventory.');
        }
        $removed = TicketFiles::cleanupLegacyInventory($this->di, $inventory, true);
        $output->writeln(sprintf('Removed %d legacy orphan(s).%s', $removed, $removed === 500 ? ' Generate and review another inventory to continue.' : ''));

        return Command::SUCCESS;
    }
}

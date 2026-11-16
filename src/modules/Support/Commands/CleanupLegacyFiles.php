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
        $this->addOption('legacy-uploads-drained', null, InputOption::VALUE_NONE, 'Confirm all legacy attachment writers have stopped and all their requests have finished')
            ->setHelp('Before using this command, upgrade or stop every legacy attachment writer on every node, and wait for all legacy requests and transactions to finish. Keep legacy writers stopped while this command runs. Run again if 500 files were removed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('legacy-uploads-drained')) {
            $output->writeln('<error>Stop all legacy attachment writers and drain their requests, then pass --legacy-uploads-drained.</error>');

            return Command::INVALID;
        }
        $removed = TicketFiles::cleanupLegacyOrphans($this->di, true);
        $output->writeln(sprintf('Removed %d legacy orphan(s).%s', $removed, $removed === 500 ? ' Run again to continue.' : ''));

        return Command::SUCCESS;
    }
}

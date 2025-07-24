<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cron\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:run',
    description: 'Executes the cron jobs',
    hidden: false
)]
class Run extends Command implements \FOSSBilling\InjectionAwareInterface
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

    protected function configure(): void
    {
        $this->addArgument('interval', InputArgument::OPTIONAL, 'Interval in minutes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = $this->di['mod_service']('cron');
        $interval = $input->getArgument('interval') ?? null;

        $output->writeln([
            'FOSSBilling Cron Job Runner',
            '============',
            'Last executed: ' . $service->getLastExecutionTime(),
            '',
        ]);

        try {
            $service->runCrons($interval);
        } catch (\Exception $e) {
            $output->writeln("<error>An error occurred: {$e->getMessage()}</error>");

            return Command::FAILURE;
        } finally {
            $output->writeln('<info>Successfully ran the cron jobs.</info>');

            return Command::SUCCESS;
        }
    }
}

<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2023
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Cron\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
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
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            $output->writeln('<info>Successfully ran the cron jobs.</info>');
            return Command::SUCCESS;
        }
    }
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Commands;

use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'database:json:audit', description: 'Inspect legacy JSON fields without modifying data or printing payloads')]
final class JsonAudit extends Command implements InjectionAwareInterface
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
        $this->addOption('table', null, InputOption::VALUE_REQUIRED, 'Inspect one table from the JSON migration inventory')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per read', '500')
            ->setHelp('Reports row IDs and issue classes only. Empty, malformed, scalar and double-encoded documents remain byte-for-byte intact. Review and back up affected rows before explicit repairs; no blanket normalization is safe. Encrypted email_template.vars and already-mapped JSON fields are outside this migration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batch = filter_var($input->getOption('batch-size'), FILTER_VALIDATE_INT);
        if ($batch === false || $batch < 1 || $batch > 10000) {
            $output->writeln('<error>Batch size must be between 1 and 10000.</error>');

            return Command::INVALID;
        }
        $count = 0;

        try {
            foreach ((new \FOSSBilling\Doctrine\JsonAudit())->inspect($this->di['dbal'], $input->getOption('table'), $batch) as $issue) {
                $output->writeln(sprintf('%s.%s id=%d %s', $issue['table'], $issue['column'], $issue['id'], $issue['issue']));
                ++$count;
            }
        } catch (\InvalidArgumentException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::INVALID;
        }
        $output->writeln(sprintf('%d JSON field(s) require review. No data was modified.', $count));

        return $count === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}

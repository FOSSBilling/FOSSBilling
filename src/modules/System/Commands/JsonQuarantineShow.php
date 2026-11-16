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

use FOSSBilling\Core\Container\InjectionAwareInterface;
use Pimple\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'database:json:quarantine:show', description: 'Print a quarantined JSON original for manual review')]
final class JsonQuarantineShow extends Command implements InjectionAwareInterface
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
        $this->addOption('table', null, InputOption::VALUE_REQUIRED, 'Quarantined table from the JSON migration inventory')
            ->addOption('column', null, InputOption::VALUE_REQUIRED, 'Quarantined column, as listed by database:json:audit --quarantined')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Quarantined row ID (or hashed session ID) as listed by database:json:audit --quarantined')
            ->setHelp('Decrypts and prints the original value the migration quarantined. Handle the output as sensitive data: it is a verbatim historical payload.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getOption('table');
        $column = $input->getOption('column');
        $id = $input->getOption('id');
        if (!is_string($table) || $table === '' || !is_string($column) || $column === '' || !is_string($id) || $id === '') {
            $output->writeln('<error>--table, --column and --id are required.</error>');

            return Command::INVALID;
        }
        if (!isset(\FOSSBilling\Core\Doctrine\JsonFields::FIELDS[$table]['columns'][$column])) {
            $output->writeln('<error>Unknown JSON migration column.</error>');

            return Command::INVALID;
        }
        $store = new \FOSSBilling\Core\Doctrine\JsonQuarantineStore($this->di['em']->getConnection(), $this->di['crypt']);
        $found = $store->find($table, $column, $id);
        if ($found === null) {
            $output->writeln(sprintf('<error>No quarantined value for %s.%s id=%s.</error>', $table, $column, $id));

            return Command::INVALID;
        }
        $output->writeln(sprintf('%s.%s id=%s quarantined:%s (sensitive historical payload below)', $table, $column, $found['entry']['id'], $found['entry']['issue']));
        $output->writeln($found['original'], OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}

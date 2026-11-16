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

#[AsCommand(name: 'database:json:audit', description: 'Inspect JSON fields without modifying data or printing payloads')]
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
            ->addOption('quarantined', null, InputOption::VALUE_NONE, 'Also list values nulled during migration (from the migration journal)')
            ->setHelp('Reports row IDs and issue categories without JSON payloads; session IDs are hashed. Uses the automatic migration\'s interpretation of old form defaults and interrupted work. Repair reported rows, then retry normal update finalization. Pass --quarantined to also list values the migration already nulled.');
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
            foreach ((new \FOSSBilling\Core\Doctrine\JsonAudit())->inspect($this->di['em']->getConnection(), $input->getOption('table'), $batch) as $issue) {
                $output->writeln(sprintf('%s.%s id=%s %s', $issue['table'], $issue['column'], $issue['id'], $issue['issue']));
                ++$count;
            }
            if ($input->getOption('quarantined')) {
                foreach ($this->quarantined() as $entry) {
                    if ($input->getOption('table') !== null && $entry['table'] !== $input->getOption('table')) {
                        continue;
                    }
                    $output->writeln(sprintf('%s.%s id=%s quarantined:%s (value was set to NULL during migration)', $entry['table'], $entry['column'], $entry['id'], $entry['issue']));
                }
            }
        } catch (\InvalidArgumentException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::INVALID;
        }
        $output->writeln(sprintf('%d JSON field(s) require review. No data was modified.', $count));

        return $count === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return list<array{table: string, column: string, id: int|string, issue: string}> */
    private function quarantined(): array
    {
        $connection = $this->di['em']->getConnection();
        if (!$connection->createSchemaManager()->tablesExist(['json_migration_record'])) {
            return [];
        }
        $journal = new \FOSSBilling\Core\Doctrine\JsonMigrationJournal($this->di['em'], $this->di['crypt']);
        $summary = $journal->find(\FOSSBilling\Core\Doctrine\JsonMigrationJournal::COMPLETE)
            ?? $journal->find('quarantine-index:json-fields-v1');
        $entries = $summary['quarantined'] ?? $summary['entries'] ?? [];

        return array_values(is_array($entries) ? $entries : []);
    }
}

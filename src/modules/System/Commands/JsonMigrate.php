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

use FOSSBilling\Config;
use FOSSBilling\Doctrine\JsonMigration;
use FOSSBilling\Doctrine\JsonMigrationException;
use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'database:json:migrate', description: 'Back up, preflight and convert legacy JSON columns while all writers are stopped')]
final class JsonMigrate extends Command implements InjectionAwareInterface
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
        $this->addOption('backup-file', null, InputOption::VALUE_REQUIRED, 'New absolute private JSONL backup path outside the application root')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Explicitly apply the migration')
            ->addOption('writers-stopped', null, InputOption::VALUE_NONE, 'Confirm web requests, cron and queue workers have all been stopped')
            ->setHelp('Take a full database backup, enable maintenance mode, and stop ALL writers before --apply --writers-stopped --backup-file=/private/path/new.jsonl. The backup contains secrets: keep it private. Malformed JSON, scalar roots, double encoding, duplicate keys, unsupported numbers, numeric-key objects, custom indexes/defaults and native database validation failures block before source changes. Empty strings become SQL NULL; empty objects decode as arrays under the new API contract; scalar and double-encoded documents require explicit repair before conversion. MySQL DDL is not atomic: keep maintenance enabled after failure, retain the original complete backup, and retry with a NEW backup path after addressing the cause. Restore the full database backup before reverting application code. No web-triggered DDL or silent repair is performed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('apply')) {
            $output->writeln('No data was modified. Run database:json:audit to inspect rows; see --help for the offline upgrade procedure.');

            return Command::SUCCESS;
        }
        if (!Config::getProperty('maintenance_mode.enabled', false) || !$input->getOption('writers-stopped') || !$input->getOption('backup-file')) {
            $output->writeln('<error>Enable maintenance mode, stop all writers, and supply --writers-stopped and --backup-file before applying.</error>');

            return Command::INVALID;
        }

        try {
            $result = (new JsonMigration())->migrate($this->di['dbal'], $input->getOption('backup-file'));
        } catch (JsonMigrationException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            $output->writeln('Keep maintenance enabled. Retain existing backups and inspect the failure before retrying.');

            return Command::FAILURE;
        } catch (\Throwable) {
            $output->writeln('<error>JSON migration failed. Keep maintenance enabled and retain the backup. Driver details are withheld because they may contain sensitive data. Check database/schema access before retrying with a new backup path.</error>');

            return Command::FAILURE;
        }
        $output->writeln(sprintf('Migrated %d JSON columns; normalized %d empty values to SQL NULL. The private backup is complete. Clear caches and finalize the update before restarting writers.', $result['columns'], $result['empty_values']));

        return Command::SUCCESS;
    }
}

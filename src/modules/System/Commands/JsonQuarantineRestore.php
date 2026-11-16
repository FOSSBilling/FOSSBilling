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
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'database:json:quarantine:restore', description: 'Write a corrected value back for a quarantined JSON field')]
final class JsonQuarantineRestore extends Command implements InjectionAwareInterface
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
            ->addOption('from-file', null, InputOption::VALUE_REQUIRED, 'File containing the corrected JSON document')
            ->setHelp('Validates the replacement with the migration\'s own checks and writes it back only into the still-empty slot, then drops the quarantine record. Refuses invalid documents and never overwrites a value set since.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getOption('table');
        $column = $input->getOption('column');
        $id = $input->getOption('id');
        $file = $input->getOption('from-file');
        if (!is_string($table) || $table === '' || !is_string($column) || $column === '' || !is_string($id) || $id === '' || !is_string($file) || $file === '') {
            $output->writeln('<error>--table, --column, --id and --from-file are required.</error>');

            return Command::INVALID;
        }
        if (!isset(\FOSSBilling\Core\Doctrine\JsonFields::FIELDS[$table]['columns'][$column])) {
            $output->writeln('<error>Unknown JSON migration column.</error>');

            return Command::INVALID;
        }

        try {
            $candidate = (new Filesystem())->readFile($file);
        } catch (IOException) {
            $output->writeln(sprintf('<error>Could not read %s.</error>', $file));

            return Command::INVALID;
        }
        $connection = $this->di['em']->getConnection();
        $store = new \FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, $this->di['crypt']);
        $found = $store->locate($table, $column, $id);
        if ($found === null) {
            $output->writeln(sprintf('<error>No quarantined value for %s.%s id=%s.</error>', $table, $column, $id));

            return Command::INVALID;
        }
        if (!$connection->createSchemaManager()->tablesExist(['json_migration_record'])
            || !\FOSSBilling\Core\Doctrine\JsonMigrationJournal::hasRecord($connection, \FOSSBilling\Core\Doctrine\JsonMigrationJournal::COMPLETE)) {
            $output->writeln('<error>JSON conversion has not completed; retry update finalization before restoring a value.</error>');

            return Command::INVALID;
        }

        try {
            \FOSSBilling\Core\Doctrine\JsonMigrationValidation::assertCompatible($candidate, $table === 'form_field' && $column === 'default_value');
        } catch (\JsonException) {
            $output->writeln(sprintf('<error>%s is not a storable JSON document for %s.%s; nothing was changed.</error>', $file, $table, $column));

            return Command::INVALID;
        }

        try {
            $candidate = $this->encryptAdapterSecrets($table, $column, $found['row_id'], $candidate);
        } catch (\JsonException|\FOSSBilling\Core\Exception\InformationException $exception) {
            $output->writeln(sprintf('<error>Could not store %s: %s; nothing was changed.</error>', $file, $exception->getMessage()));

            return Command::INVALID;
        }
        $idType = $found['id_binary'] ? \Doctrine\DBAL\ParameterType::BINARY : \Doctrine\DBAL\ParameterType::STRING;
        $current = $connection->fetchOne(
            'SELECT ' . $connection->quoteSingleIdentifier($column) . ' FROM ' . $connection->quoteSingleIdentifier($table) . ' WHERE id = ?',
            [$found['row_id']],
            [$idType]
        );
        if ($current === false) {
            $output->writeln(sprintf('<error>Row id=%s no longer exists in %s; nothing was changed.</error>', $found['entry']['id'], $table));

            return Command::INVALID;
        }
        if ($current !== null) {
            $output->writeln(sprintf('<error>%s.%s id=%s already has a value; refusing to overwrite it.</error>', $table, $column, $found['entry']['id']));

            return Command::INVALID;
        }

        try {
            $connection->transactional(function () use ($connection, $store, $table, $column, $candidate, $found, $id, $idType): void {
                $changed = $connection->executeStatement(
                    'UPDATE ' . $connection->quoteSingleIdentifier($table) . ' SET ' . $connection->quoteSingleIdentifier($column) . ' = ? WHERE id = ? AND ' . $connection->quoteSingleIdentifier($column) . ' IS NULL',
                    [$candidate, $found['row_id']],
                    [\Doctrine\DBAL\ParameterType::STRING, $idType]
                );
                if ($changed !== 1) {
                    throw new \RuntimeException(sprintf('Concurrent modification of %s.%s id=%s during restore.', $table, $column, $found['entry']['id']));
                }
                $store->delete($table, $column, $id);
            });
        } catch (\Doctrine\DBAL\Exception) {
            $output->writeln(sprintf('<error>The database rejected the replacement for %s.%s; nothing was changed.</error>', $table, $column));

            return Command::FAILURE;
        } catch (\RuntimeException) {
            $output->writeln(sprintf('<error>Could not restore %s.%s id=%s; the row changed concurrently and nothing was changed.</error>', $table, $column, $found['entry']['id']));

            return Command::FAILURE;
        }
        $output->writeln(sprintf('Restored %s.%s id=%s from %s and dropped its quarantine record.', $table, $column, $found['entry']['id'], $file));

        return Command::SUCCESS;
    }

    /**
     * Encrypt declared adapter secrets before a restored gateway or registrar
     * configuration reaches storage, mirroring the normal write path. Other
     * columns have no secret fields and pass through unchanged.
     */
    private function encryptAdapterSecrets(string $table, string $column, string $rowId, string $candidate): string
    {
        if (($table !== 'pay_gateway' && $table !== 'tld_registrar') || $column !== 'config') {
            return $candidate;
        }
        if ($table === 'pay_gateway') {
            $model = $this->di['em']->getRepository(\Box\Mod\Invoice\Entity\PayGateway::class)->find((int) $rowId);
            $fields = $model === null ? [] : $this->di['mod_service']('Invoice', 'PayGateway')->getSecretFields($model);
        } else {
            $model = $this->di['em']->getRepository(\Box\Mod\Servicedomain\Entity\TldRegistrar::class)->find((int) $rowId);
            $fields = $model === null ? [] : $this->di['mod_service']('Servicedomain')->registrarGetSecretFields($model);
        }
        if ($fields === []) {
            return $candidate;
        }
        $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        $protected = (new \FOSSBilling\Core\Security\SecretFields($this->di['crypt']))->store($decoded, $fields);

        return json_encode($protected, JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\Manifest\Command;

use FOSSBilling\Tools\Manifest\Validator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'manifest:validate', description: 'Validate bundled module manifest.json files')]
final class ValidateCommand extends Command
{
    public function __construct(
        private readonly string $defaultModulesDir,
        private readonly string $schemaPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('modules', InputArgument::OPTIONAL, 'Modules checkout directory (defaults to this repository)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modulesDir = $input->getArgument('modules') ?? $this->defaultModulesDir;
        $result = (new Validator($modulesDir, $this->schemaPath))->validate();
        foreach ($result['lines'] as $line) {
            $output->writeln($line);
        }

        if ($result['failures'] !== []) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln(sprintf('FAIL: %s', implode(', ', $result['failures'])));

            return Command::FAILURE;
        }
        $output->writeln(sprintf('OK: %d package(s) valid', $result['packages']));

        return Command::SUCCESS;
    }
}

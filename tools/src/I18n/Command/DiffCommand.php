<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\I18n\Command;

use FOSSBilling\Tools\I18n\TranslationCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:diff', description: 'Report message, plural and casing changes between templates')]
final class DiffCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('old-template', InputArgument::REQUIRED)
            ->addArgument('new-template', InputArgument::REQUIRED)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!in_array($format, ['text', 'json'], true)) {
            throw new \InvalidArgumentException('format must be text or json');
        }
        $diff = TranslationCatalog::diff($input->getArgument('old-template'), $input->getArgument('new-template'));
        $report = $format === 'json'
            ? json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            : $diff['report'];
        $output->write($report, false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}

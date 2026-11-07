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

use FOSSBilling\Tools\I18n\Modules;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:extract-modules', description: 'Extract one translation template per module')]
final class ExtractModulesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('modules', InputArgument::REQUIRED, 'Modules checkout or single module directory')
            ->addArgument('core-template', InputArgument::REQUIRED, 'Pinned core POT file')
            ->addArgument('output', InputArgument::REQUIRED, 'Output directory for module templates')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Check templates without modifying them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Modules::extract(
            $input->getArgument('modules'), $input->getArgument('core-template'), $input->getArgument('output'), $input->getOption('check'),
        );
    }
}

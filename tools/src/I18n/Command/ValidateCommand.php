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

use FOSSBilling\Tools\I18n\Gettext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:validate', description: 'Validate module PO files with GNU gettext and project-specific checks')]
final class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('modules', InputArgument::REQUIRED, 'Modules checkout or single module directory')
            ->addArgument('templates', InputArgument::REQUIRED, 'Directory containing module templates');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Gettext::build($input->getArgument('modules'), $input->getArgument('templates'), null);
    }
}

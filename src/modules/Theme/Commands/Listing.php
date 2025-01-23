<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// List is a reserved word in PHP, so we'll be using Listing instead. The command name will stay as 'theme:list'.

#[AsCommand(
    name: 'theme:list',
    description: 'Returns the list of the installed themes',
    hidden: false
)]
class Listing extends Command implements \FOSSBilling\InjectionAwareInterface
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $admin = $this->di['mod_service']('theme')->getThemes(false);
        $client = $this->di['mod_service']('theme')->getThemes();

        $currentAdmin = $this->di['mod_service']('theme')->getCurrentAdminAreaTheme()['code'];
        $currentClient = $this->di['mod_service']('theme')->getCurrentClientAreaThemeCode();

        $table = new Table($output);

        $table->setHeaders(['Name', 'Scope', 'Active']);

        // The admin themes are listed first, followed by a separator, and then the client themes.
        foreach ($admin as $adminTheme) {
            $table->addRow([
                $adminTheme['name'],
                'Admin',
                $adminTheme['code'] === $currentAdmin ? 'Yes' : 'No',
            ]);
        }

        $table->addRow(new TableSeparator());

        foreach ($client as $clientTheme) {
            $table->addRow([
                $clientTheme['name'],
                'Client',
                $clientTheme['code'] === $currentClient ? 'Yes' : 'No',
            ]);
        }

        $table->setHeaderTitle('Installed FOSSBilling Themes');
        $table->setStyle('box-double');
        $table->render();

        return Command::SUCCESS;
    }
}

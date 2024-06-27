<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme\Console;

use CristianG\PimpleConsole\Command;
use Pimple\Container;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Listing extends Command implements \FOSSBilling\InjectionAwareInterface
{
    protected ?Container $di = null;

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
        $this->setName('theme:list');
        $this->setDescription('Returns the list of the installed themes');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $admin = $this->di['mod_service']('theme')->getThemes(false);
        $client = $this->di['mod_service']('theme')->getThemes();

        $currentAdmin = $this->di['mod_service']('theme')->getCurrentAdminAreaTheme()['code'];
        $currentClient = $this->di['mod_service']('theme')->getCurrentClientAreaThemeCode();

        $rows = [];
        // The admin themes are listed first, followed by a separator, and then the client themes.
        foreach ($admin as $adminTheme) {
            $rows[] = [
                $adminTheme['name'],
                'Admin',
                $adminTheme['code'] === $currentAdmin ? 'Yes' : 'No',
            ];
        }
        $rows[] = new TableSeparator();
        foreach ($client as $clientTheme) {
            $rows[] = [
                $clientTheme['name'],
                'Client',
                $clientTheme['code'] === $currentClient ? 'Yes' : 'No',
            ];
        }
        $this->table(['Name', 'Scope', 'Active'], $rows, 'box-double');

        return Command::SUCCESS;
    }
}

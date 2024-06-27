<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Console;

use CristianG\PimpleConsole\Command;
use Pimple\Container;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Version extends Command implements \FOSSBilling\InjectionAwareInterface
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
        $this->setName('system:version');
        $this->setDescription('Returns the current version of FOSSBilling');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->info('FOSSBilling version : ' . $this->di['mod_service']('system')->getVersion());

        return Command::SUCCESS;
    }
}

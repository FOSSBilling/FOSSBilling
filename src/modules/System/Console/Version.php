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

use Pimple\Container;

use CristianG\PimpleConsole\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Version extends Command implements \FOSSBilling\InjectionAwareInterface
{
    protected ?Container $di = null;

    /**
     * @param  Container  $di
     * @return void
     */
    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return Container|null
     */
    public function getDi(): ?Container
    {
        return $this->di;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('system:version');
        $this->setDescription('Returns the current version of FOSSBilling');
        parent::configure();
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->info("FossBilling version : ".$this->di['mod_service']('system')->getVersion());
        return Command::SUCCESS;
    }
}

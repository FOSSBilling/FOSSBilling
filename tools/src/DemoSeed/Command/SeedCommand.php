<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\DemoSeed\Command;

use FOSSBilling\Tools\DemoSeed\ApiClient;
use FOSSBilling\Tools\DemoSeed\Seeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'demo:seed', description: 'Seed a FOSSBilling instance with demo data over the admin API')]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly string $defaultManifestDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Base URL of the target install (env: APP_URL)', null)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Plan the seed without creating anything')
            ->addOption('limit-clients', null, InputOption::VALUE_REQUIRED, 'Cap the number of demo clients (0 for all 24)', 0)
            ->addOption('limit-orders', null, InputOption::VALUE_REQUIRED, 'Cap the number of orders (0 for 36)', 0)
            ->addOption('limit-tickets', null, InputOption::VALUE_REQUIRED, 'Cap the number of tickets (0 for 20)', 0)
            ->addOption('manifest', null, InputOption::VALUE_REQUIRED, 'Manifest output directory (default: tools/var/demo-seed)', null)
            ->setHelp(<<<'TXT'
                Additive seeder. Foundation/catalog records use a "Demo" prefix and are
                skipped when they already exist; orders, invoices and tickets are appended
                on every run, so re-running grows those lists rather than converging.

                Sends no real email (clients created with send_welcome_email=0) and only
                enables offline-safe gateways (Custom, ClientBalance). Requires an admin
                API key in TEST_API_KEY.
                TXT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = (string) (getenv('TEST_API_KEY') ?: '');
        if ($apiKey === '') {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln('Missing API key. Set TEST_API_KEY.');

            return 2;
        }

        $baseUrl = rtrim((string) ($input->getOption('url') ?? getenv('APP_URL') ?: 'https://fossbilling.ddev.site'), '/');
        $manifestDir = Path::normalize($input->getOption('manifest') ?? $this->defaultManifestDir);
        $seeder = new Seeder(
            new ApiClient($baseUrl, $apiKey),
            $baseUrl,
            (bool) $input->getOption('dry-run'),
            (int) $input->getOption('limit-clients'),
            (int) $input->getOption('limit-orders'),
            (int) $input->getOption('limit-tickets'),
            Path::join($manifestDir, $input->getOption('dry-run') ? 'manifest.dry.json' : 'manifest.json'),
            $output,
        );

        try {
            $errors = $seeder->run();
        } catch (\RuntimeException $e) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln($e->getMessage());

            return Command::FAILURE;
        }

        return $errors > 20 ? Command::FAILURE : Command::SUCCESS;
    }
}

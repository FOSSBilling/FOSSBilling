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
        private readonly string $defaultDemoFilePath,
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

                Clients are created with send_welcome_email=0 and only offline-safe
                gateways (Custom, ClientBalance) are enabled, but other seed actions can
                still trigger the target's normal notifications, so seed instances where
                mail delivery is disabled or captured by a local sink. Domain orders are
                only created when the registrar for .com is in test mode. Requires an
                admin API key in TEST_API_KEY.
                TXT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $apiKey = (string) (getenv('TEST_API_KEY') ?: '');
        if ($apiKey === '') {
            $errorOutput->writeln('Missing API key. Set TEST_API_KEY.');

            return 2;
        }

        foreach (['limit-clients', 'limit-orders', 'limit-tickets'] as $limit) {
            $value = $input->getOption($limit);
            if (!is_int($value) && (!is_string($value) || preg_match('/^\d+$/', $value) !== 1)) {
                throw new \InvalidArgumentException(sprintf('The "--%s" option must be a non-negative integer, got "%s".', $limit, (string) $value));
            }
        }

        $baseUrl = rtrim((string) ($input->getOption('url') ?? getenv('APP_URL') ?: 'https://fossbilling.ddev.site'), '/');
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        if (parse_url($baseUrl, PHP_URL_SCHEME) === 'http' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true) && !str_ends_with($host, '.localhost') && !str_ends_with($host, '.ddev.site') && !str_ends_with($host, '.test')) {
            $errorOutput->writeln('Warning: the admin API key will be sent over plaintext HTTP to a remote host. Use HTTPS or a local development domain.');
        }

        $manifestDir = $input->getOption('manifest') ?? $this->defaultManifestDir;
        $dryRun = $input->getOption('dry-run');
        $seeder = new Seeder(
            new ApiClient($baseUrl, $apiKey),
            $baseUrl,
            $dryRun,
            (int) $input->getOption('limit-clients'),
            (int) $input->getOption('limit-orders'),
            (int) $input->getOption('limit-tickets'),
            Path::join($manifestDir, $dryRun ? 'manifest.dry.json' : 'manifest.json'),
            $output,
            $this->defaultDemoFilePath,
        );

        try {
            $errors = $seeder->run();
        } catch (\RuntimeException $e) {
            $errorOutput->writeln($e->getMessage());

            return Command::FAILURE;
        }

        return $errors > 20 ? Command::FAILURE : Command::SUCCESS;
    }
}

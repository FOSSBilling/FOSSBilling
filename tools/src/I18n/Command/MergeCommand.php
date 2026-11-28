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

use FOSSBilling\Tools\I18n\Extractor;
use FOSSBilling\Tools\I18n\TemporaryDirectory;
use FOSSBilling\Tools\I18n\TranslationCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'i18n:merge', description: 'Extract translation templates from multiple source trees and merge them into one template')]
final class MergeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('template', InputArgument::REQUIRED, 'Output POT file')
            ->addArgument('sources', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Source directories to extract from')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Check the template without modifying it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $occurrences = [];
        $files = 0;
        foreach ($input->getArgument('sources') as $source) {
            $result = Extractor::extract($source);
            if ($result['source_errors']) {
                throw new \RuntimeException(implode("\n", $result['source_errors']));
            }
            array_push($occurrences, ...$result['occurrences']);
            $files += $result['files'];
        }
        $target = $input->getArgument('template');
        $count = TemporaryDirectory::run(static function ($temporary) use ($occurrences, $target, $input, $output): int {
            $generated = Path::join($temporary, 'messages.pot');
            $count = TranslationCatalog::write($occurrences, $generated);
            $filesystem = new Filesystem();
            if ($input->getOption('check')) {
                if (!$filesystem->exists($target)) {
                    throw new \RuntimeException("missing template: $target");
                }
                if (TranslationCatalog::normalized($generated) !== TranslationCatalog::normalized($target)) {
                    $output->write(TranslationCatalog::diff($target, $generated)['report'], false, OutputInterface::OUTPUT_RAW);

                    throw new \RuntimeException("template needs regeneration: $target");
                }
            } else {
                $filesystem->copy($generated, $target, true);
            }

            return $count;
        });
        $output->writeln(sprintf('Scanned %d files across %d sources; found %d messages.', $files, count($input->getArgument('sources')), $count));

        return Command::SUCCESS;
    }
}

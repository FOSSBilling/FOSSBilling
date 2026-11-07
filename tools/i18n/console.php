#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

require __DIR__ . '/bootstrap.php';

use FOSSBilling\Tools\I18n\Extractor;
use FOSSBilling\Tools\I18n\Gettext;
use FOSSBilling\Tools\I18n\Modules;
use FOSSBilling\Tools\I18n\TemporaryDirectory;
use FOSSBilling\Tools\I18n\TranslationCatalogue;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

$application = new Application('FOSSBilling translations');
$application->addCommand((new Command('extract'))
    ->setDescription('Extract a combined core and bundled-module template')
    ->addArgument('source', InputArgument::REQUIRED, 'Source directory')
    ->addArgument('template', InputArgument::REQUIRED, 'Output POT file')
    ->addOption('check', null, InputOption::VALUE_NONE, 'Check the template without modifying it')
    ->setCode(static function (InputInterface $input, OutputInterface $output): int {
        $result = Extractor::extract($input->getArgument('source'));
        if ($result['source_errors']) {
            throw new RuntimeException(implode("\n", $result['source_errors']));
        }
        $target = $input->getArgument('template');
        $count = TemporaryDirectory::run(static function ($temporary) use ($result, $target, $input, $output): int {
            $generated = Path::join($temporary, 'messages.pot');
            $count = TranslationCatalogue::write($result['occurrences'], $generated);
            $filesystem = new Filesystem();
            if ($input->getOption('check')) {
                if (!$filesystem->exists($target)) {
                    throw new RuntimeException("missing template: $target");
                }
                if (TranslationCatalogue::normalized($generated) !== TranslationCatalogue::normalized($target)) {
                    $output->write(TranslationCatalogue::diff($target, $generated));

                    throw new RuntimeException("template needs regeneration: $target");
                }
            } else {
                $filesystem->copy($generated, $target, true);
            }

            return $count;
        });
        $output->writeln(sprintf('Scanned %d files; found %d messages.', $result['files'], $count));

        return Command::SUCCESS;
    }));
$application->addCommand((new Command('extract-modules'))
    ->setDescription('Extract one translation template per module')
    ->addArgument('modules', InputArgument::REQUIRED, 'Modules checkout or single module directory')
    ->addArgument('core-template', InputArgument::REQUIRED, 'Pinned core POT file')
    ->addArgument('output', InputArgument::REQUIRED, 'Output directory for module templates')
    ->addOption('check', null, InputOption::VALUE_NONE, 'Check templates without modifying them')
    ->setCode(static fn (InputInterface $input): int => Modules::extract(
        $input->getArgument('modules'), $input->getArgument('core-template'), $input->getArgument('output'), $input->getOption('check'),
    )));
$application->addCommand((new Command('diff'))
    ->setDescription('Report message, plural and casing changes between templates')
    ->addArgument('old-template', InputArgument::REQUIRED)
    ->addArgument('new-template', InputArgument::REQUIRED)
    ->setCode(static function (InputInterface $input, OutputInterface $output): int {
        $output->write(TranslationCatalogue::diff($input->getArgument('old-template'), $input->getArgument('new-template')));

        return Command::SUCCESS;
    }));
$application->addCommand((new Command('validate'))
    ->setDescription('Validate module PO files with GNU gettext and project-specific checks')
    ->addArgument('modules', InputArgument::REQUIRED, 'Modules checkout or single module directory')
    ->addArgument('templates', InputArgument::REQUIRED, 'Directory containing module templates')
    ->setCode(static fn (InputInterface $input): int => Gettext::build($input->getArgument('modules'), $input->getArgument('templates'), null)));
$application->addCommand((new Command('compile'))
    ->setDescription('Compile validated module translations into a clean staging directory')
    ->addArgument('modules', InputArgument::REQUIRED, 'Modules checkout or single module directory')
    ->addArgument('templates', InputArgument::REQUIRED, 'Directory containing module templates')
    ->addArgument('output', InputArgument::REQUIRED, 'New or empty output directory')
    ->setCode(static fn (InputInterface $input): int => Gettext::build($input->getArgument('modules'), $input->getArgument('templates'), $input->getArgument('output'))));
$application->run();

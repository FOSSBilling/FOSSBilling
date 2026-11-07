<?php

declare(strict_types=1);

namespace FOSSBilling\Tools\I18n;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class Application
{
    public static function run(string $mode, array $arguments): int
    {
        try {
            [$positional, $options] = self::arguments($mode, $arguments);
            if (isset($options['help'])) {
                echo self::help($mode);

                return 0;
            }
            if ($mode === 'modules') {
                if (count($positional) !== 2 || !in_array($positional[0], ['validate', 'compile'], true) || !isset($options['templates'])) {
                    throw new \InvalidArgumentException('Use validate ROOT --templates DIR or compile ROOT --templates DIR --output DIR');
                }
                if ($positional[0] === 'compile' && !isset($options['output'])) {
                    throw new \InvalidArgumentException('compile requires --output');
                }
                if ($positional[0] === 'validate' && isset($options['output'])) {
                    throw new \InvalidArgumentException('validate does not accept --output');
                }

                return Gettext::build($positional[1], $options['templates'], $options['output'] ?? null);
            }
            if (isset($options['modules'])) {
                if ($positional || isset($options['diff']) || !isset($options['core-pot'], $options['output'])) {
                    throw new \InvalidArgumentException('--modules requires --core-pot and --output, without positional arguments or --diff');
                }

                return Modules::extract($options['modules'], $options['core-pot'], $options['output'], isset($options['check']));
            }
            if (isset($options['core-pot']) || isset($options['output'])) {
                throw new \InvalidArgumentException('--core-pot and --output require --modules');
            }
            if (isset($options['diff'])) {
                if ($positional) {
                    throw new \InvalidArgumentException('--diff does not accept positional arguments');
                }
                echo Catalogue::diff(...$options['diff']);

                return 0;
            }
            if (count($positional) !== 2) {
                throw new \InvalidArgumentException('src_dir and out_pot are required (or use --diff)');
            }
            [$root, $target] = $positional;
            $result = Extractor::extract($root);
            if ($result['source_errors']) {
                throw new \RuntimeException(implode("\n", $result['source_errors']));
            }
            $count = TemporaryDirectory::run(static function ($temporary) use ($result, $target, $options): int {
                $generated = Path::join($temporary, 'messages.pot');
                $count = Catalogue::write($result['occurrences'], $generated);
                if (isset($options['check'])) {
                    if (!(new Filesystem())->exists($target)) {
                        throw new \RuntimeException("missing template: $target");
                    }
                    if (Catalogue::normalized($generated) !== Catalogue::normalized($target)) {
                        echo Catalogue::diff($target, $generated);

                        throw new \RuntimeException("template needs regeneration: $target");
                    }
                } else {
                    (new Filesystem())->copy($generated, $target, true);
                }

                return $count;
            });
            fwrite(STDERR, 'files scanned: ' . $result['stats']['files'] . "\ntwig hits: {$result['stats']['twig']}, php hits: {$result['stats']['php']}\nunique msgids written: $count\n");

            return 0;
        } catch (\InvalidArgumentException $error) {
            fwrite(STDERR, 'error: ' . $error->getMessage() . "\n");

            return 2;
        } catch (\Throwable $error) {
            fwrite(STDERR, 'error: ' . $error->getMessage() . "\n");

            return 1;
        }
    }

    private static function arguments(string $mode, array $arguments): array
    {
        $positional = $options = [];
        $literal = false;
        $allowed = $mode === 'extract' ? ['modules', 'core-pot', 'output', 'check', 'diff', 'help'] : ['templates', 'output', 'help'];
        while ($arguments) {
            $argument = array_shift($arguments);
            if ($argument === '--') {
                $literal = true;

                continue;
            }
            if (!$literal && ($argument === '-h' || str_starts_with($argument, '--'))) {
                [$key, $value] = array_pad(explode('=', $argument === '-h' ? '--help' : $argument, 2), 2, null);
                $key = substr($key, 2);
                if (!in_array($key, $allowed, true)) {
                    throw new \InvalidArgumentException("unknown option --$key");
                }
                if (isset($options[$key])) {
                    throw new \InvalidArgumentException("duplicate option --$key");
                }
                if (in_array($key, ['check', 'help'], true)) {
                    if ($value !== null) {
                        throw new \InvalidArgumentException("--$key takes no value");
                    }
                    $options[$key] = true;
                } elseif ($key === 'diff') {
                    if ($value !== null || count($arguments) < 2) {
                        throw new \InvalidArgumentException('--diff requires OLD_POT NEW_POT');
                    }
                    $options[$key] = [array_shift($arguments), array_shift($arguments)];
                } else {
                    if ($value === null) {
                        $value = array_shift($arguments);
                    }
                    if ($value === null || str_starts_with($value, '--')) {
                        throw new \InvalidArgumentException("--$key requires a value");
                    }
                    $options[$key] = $value;
                }
            } elseif (!$literal && str_starts_with($argument, '-')) {
                throw new \InvalidArgumentException("unknown option $argument");
            } else {
                $positional[] = $argument;
            }
        }

        return [$positional, $options];
    }

    private static function help(string $mode): string
    {
        return $mode === 'extract' ? "Usage:\n  php extract_pot.php SRC OUT.pot [--check]\n  php extract_pot.php --diff OLD.pot NEW.pot\n  php extract_pot.php --modules ROOT --core-pot CORE.pot --output DIR [--check]\n" : "Usage:\n  php module_translations.php validate ROOT --templates DIR\n  php module_translations.php compile ROOT --templates DIR --output DIR\n";
    }
}

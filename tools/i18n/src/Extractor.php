<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\I18n;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/** Static source inspection only: source PHP and template callables are never executed. */
final class Extractor
{
    /**
     * @param list<string> $dependencies Source directories used only to resolve inherited constructor signatures
     *
     * @return array{occurrences: list<array{string, ?string, string, string}>, source_errors: list<string>, files: int}
     */
    public static function extract(string $root, ?string $module = null, array $dependencies = []): array
    {
        if (!(new Filesystem())->exists($root) || !is_dir($root)) {
            throw new \RuntimeException("source directory does not exist: $root");
        }
        if ($module !== null && (!preg_match('/\A[a-z][a-z0-9_-]*\z/', $module) || $module === 'core')) {
            throw new \RuntimeException("invalid module ID: $module");
        }
        $twig = new Environment(new ArrayLoader(), ['cache' => false, 'autoescape' => false, 'optimizations' => 0]);
        $twig->addExtension(new class extends AbstractExtension {
            public function getExpressionParsers(): array
            {
                return [new TranslationFilterParser()];
            }
        });
        // Parsing needs names, not the application services behind these callables.
        $twig->registerUndefinedFilterCallback(static fn (string $name): TwigFilter => new TwigFilter($name, static fn (...$args): null => null, ['is_variadic' => true]));
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (...$args): null => null, ['is_variadic' => true]));
        $twig->registerUndefinedTestCallback(static fn (string $name): TwigTest => new TwigTest($name, static fn (...$args): true => true));
        $files = $contexts = [];
        $finder = (new Finder())->useUnixPaths()->files()->in($root)->name(['*.php', '*.twig'])
            ->exclude(['vendor', 'install', 'data', 'tests', 'locale', 'node_modules', '.git'])->notName('load.php')->sortByName();
        foreach ($finder as $file) {
            $rel = $file->getRelativePathname();
            $files[$rel] = $file->getContents();
            if ($file->getExtension() === 'php') {
                $contexts[$rel] = self::contexts($files[$rel]);
            }
        }
        $signatureContexts = array_merge([], ...array_values($contexts));
        foreach ($dependencies as $dependency) {
            if ($dependency === $root) {
                continue;
            }
            foreach ((new Finder())->files()->in($dependency)->name('*.php')->exclude(['vendor', 'install', 'data', 'tests', 'locale', 'node_modules', '.git'])->notName('load.php') as $file) {
                array_push($signatureContexts, ...self::contexts($file->getContents()));
            }
        }
        $signatures = self::signatures($signatureContexts);
        $out = $errors = [];
        foreach ($files as $rel => $code) {
            if (str_ends_with($rel, '.php')) {
                foreach ($contexts[$rel] as $context) {
                    self::phpOccurrences($rel, $context, $signatures, $out, $errors);
                }
            } else {
                $inferred = $module;
                if ($inferred === null && preg_match('~^modules/([^/]+)/~', $rel, $matches)) {
                    $candidate = strtolower($matches[1]);
                    if ($matches[1] === ucfirst($candidate) && preg_match('/\A[a-z][a-z0-9_-]*\z/', $candidate)) {
                        $inferred = $candidate;
                    }
                }
                self::twigOccurrences($twig, $rel, $code, $inferred, $out, $errors);
            }
        }

        return ['occurrences' => $out, 'source_errors' => $errors, 'files' => count($files)];
    }

    private static function tokens(string $code): array
    {
        $result = [];
        $raw = \PhpToken::tokenize($code);
        $count = count($raw);
        for ($i = 0; $i < $count; ++$i) {
            $token = $raw[$i];
            if ($token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_INLINE_HTML])) {
                continue;
            }
            // Dynamic quoted strings and heredocs are opaque, matching current extraction policy.
            if ($token->text === '"' || $token->id === T_START_HEREDOC || $token->text === '`') {
                $end = $token->id === T_START_HEREDOC ? T_END_HEREDOC : $token->text;
                while (++$i < $count && !$raw[$i]->is($end)) {
                }
                $result[] = new \PhpToken(T_ENCAPSED_AND_WHITESPACE, '__dynamic__', $token->line, $token->pos);
            } else {
                $result[] = $token;
            }
        }

        return $result;
    }

    private static function pairs(array $tokens): array
    {
        $stack = [];
        $pairs = [];
        foreach ($tokens as $i => $token) {
            if (in_array($token->text, ['(', '[', '{', '#['], true)) {
                $stack[] = $i;
            } elseif (in_array($token->text, [')', ']', '}'], true) && $stack) {
                $open = array_pop($stack);
                $pairs[$open] = $i;
            }
        }

        return $pairs;
    }

    private static function arguments(array $tokens, int $open, array $pairs): ?array
    {
        if (!isset($pairs[$open])) {
            return null;
        }
        $result = [];
        $start = $open + 1;
        $end = $pairs[$open];
        for ($i = $start; $i < $end; ++$i) {
            if (isset($pairs[$i])) {
                $i = $pairs[$i];

                continue;
            }
            if ($tokens[$i]->text === ',') {
                $result[] = array_slice($tokens, $start, $i - $start);
                $start = $i + 1;
            }
        }
        if ($start < $end) {
            $result[] = array_slice($tokens, $start, $end - $start);
        }

        return $result;
    }

    private static function bind(array $args, array $names): array
    {
        $bound = [];
        $named = false;
        foreach ($args as $i => $value) {
            if (count($value) > 1 && $value[0]->id === T_STRING && $value[1]->text === ':') {
                $named = true;
                $name = $value[0]->text;
                $value = array_slice($value, 2);
            } else {
                if ($named) {
                    throw new \RuntimeException('positional argument after named argument');
                }
                $name = $names[$i] ?? (string) $i;
            }
            if (array_key_exists($name, $bound)) {
                throw new \RuntimeException("duplicate argument '$name'");
            }
            $bound[$name] = $value;
        }

        return $bound;
    }

    private static function ungroup(array $tokens): array
    {
        $pairs = self::pairs($tokens);
        while (($tokens[0]->text ?? '') === '(' && ($pairs[0] ?? -1) === count($tokens) - 1) {
            $tokens = array_slice($tokens, 1, -1);
            $pairs = self::pairs($tokens);
        }

        return $tokens;
    }

    private static function literal(array $tokens): ?string
    {
        $tokens = self::ungroup($tokens);
        if (count($tokens) !== 1 || $tokens[0]->id !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }
        $literal = $tokens[0]->text;
        if ($literal[0] === 'b' || $literal[0] === 'B') {
            $literal = substr($literal, 1);
        }
        $body = substr($literal, 1, -1);
        if ($literal[0] === "'") {
            return str_replace(['\\\\', "\\'", "\0"], ["\0", "'", '\\'], $body);
        }

        // PhpToken retains string spelling; decode PHP escapes without evaluating source.
        return preg_replace_callback('/\\\\(u\{[0-9A-Fa-f]+\}|x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)/s', function ($match) {
            $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\x1b", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'];
            if (str_starts_with($match[1], 'u{')) {
                return mb_chr(hexdec(substr($match[1], 2, -1)), 'UTF-8');
            }

            return $escapes[$match[1]] ?? (str_starts_with($match[1], 'x') ? chr(hexdec(substr($match[1], 1))) : (ctype_digit($match[1][0]) ? chr(octdec($match[1]) & 255) : $match[0]));
        }, $body);
    }

    private static function messages(array $tokens, bool $ternary): ?array
    {
        $tokens = self::ungroup($tokens);
        if (($value = self::literal($tokens)) !== null) {
            return [$value];
        }
        if (!$ternary) {
            return null;
        }
        $pairs = self::pairs($tokens);
        for ($i = 0; $i < count($tokens); ++$i) {
            if (isset($pairs[$i])) {
                $i = $pairs[$i];

                continue;
            }
            if ($tokens[$i]->id === T_DOUBLE_ARROW) {
                return null;
            }
            if ($tokens[$i]->text === '?') {
                $colon = $i + 1;
                while (isset($tokens[$colon]) && $tokens[$colon]->text !== ':') {
                    $colon = ($pairs[$colon] ?? $colon) + 1;
                }
                $a = self::literal(array_slice($tokens, $i + 1, $colon - $i - 1));
                $b = self::literal(array_slice($tokens, $colon + 1));

                return $a !== null && $b !== null ? [$a, $b] : null;
            }
        }

        return null;
    }

    private static function source(?array $tokens, string $default): string
    {
        if ($tokens === null) {
            return $default;
        }
        $tokens = self::ungroup($tokens);
        if (count($tokens) === 1 && strtolower($tokens[0]->text) === 'null') {
            return 'core';
        }
        $s = self::literal($tokens);
        if ($s !== null && preg_match('/\A[a-z][a-z0-9_-]*\z/', $s)) {
            return $s;
        }

        throw new \RuntimeException('from must be a literal lowercase source ID or null');
    }

    private static function resolveName(string $name, array $context): string
    {
        if (str_starts_with($name, '\\')) {
            return strtolower(substr($name, 1));
        }
        if (str_starts_with(strtolower($name), 'namespace\\')) {
            return strtolower(($context['namespace'] ? $context['namespace'] . '\\' : '') . substr($name, 10));
        }
        $parts = explode('\\', $name);
        $first = strtolower($parts[0]);
        if (isset($context['aliases'][$first])) {
            array_shift($parts);

            return $context['aliases'][$first] . ($parts ? '\\' . strtolower(implode('\\', $parts)) : '');
        }

        return strtolower(($context['namespace'] ? $context['namespace'] . '\\' : '') . $name);
    }

    private static function contexts(string $code): array
    {
        $tokens = self::tokens($code);
        $pairs = self::pairs($tokens);
        $contexts = [];
        $start = 0;
        $namespace = '';
        for ($i = 0; $i < count($tokens); ++$i) {
            if ($tokens[$i]->id === T_NAMESPACE) {
                if ($start < $i) {
                    $contexts[] = self::context(array_slice($tokens, $start, $i - $start), $namespace);
                }
                $j = $i + 1;
                $namespace = '';
                if ($tokens[$j]->text !== '{') {
                    $namespace = strtolower($tokens[$j++]->text);
                }
                if ($tokens[$j]->text === '{') {
                    $contexts[] = self::context(array_slice($tokens, $j + 1, $pairs[$j] - $j - 1), $namespace);
                    $i = $pairs[$j];
                    $namespace = '';
                } else {
                    $i = $j;
                }
                $start = $i + 1;
            } elseif (isset($pairs[$i])) {
                $i = $pairs[$i];
            }
        }
        if ($start < count($tokens)) {
            $contexts[] = self::context(array_slice($tokens, $start), $namespace);
        }

        return $contexts;
    }

    private static function context(array $tokens, string $namespace): array
    {
        $pairs = self::pairs($tokens);
        $context = ['tokens' => $tokens, 'pairs' => $pairs, 'namespace' => $namespace, 'aliases' => [], 'functions' => [], 'classes' => []];
        foreach ($tokens as $i => $token) {
            if ($token->id !== T_CLASS || ($tokens[$i + 1]->id ?? 0) !== T_STRING) {
                continue;
            }
            $j = $i + 2;
            $parent = '';
            if (($tokens[$j]->id ?? 0) === T_EXTENDS) {
                $parent = $tokens[++$j]->text;
                ++$j;
            }
            while (isset($tokens[$j]) && $tokens[$j]->text !== '{') {
                ++$j;
            }
            if (!isset($pairs[$j])) {
                continue;
            }
            $parameters = null;
            for ($k = $j + 1; $k < $pairs[$j]; ++$k) {
                if ($tokens[$k]->id === T_FUNCTION && strtolower($tokens[$k + 1]->text ?? '') === '__construct') {
                    $parameters = [];
                    foreach (self::arguments($tokens, $k + 2, $pairs) ?? [] as $arg) {
                        foreach ($arg as $parameter) {
                            if ($parameter->id === T_VARIABLE) {
                                $parameters[] = substr($parameter->text, 1);

                                break;
                            }
                        }
                    }

                    break;
                }
            }
            $context['classes'][] = ['name' => strtolower(($context['namespace'] ? $context['namespace'] . '\\' : '') . $tokens[$i + 1]->text), 'parentRaw' => $parent, 'parameters' => $parameters, 'start' => $i, 'end' => $pairs[$j]];
        }
        foreach ($tokens as $i => $token) {
            if ($token->id !== T_USE || ($tokens[$i + 1]->text ?? '') === '(' || ($tokens[$i + 1]->id ?? 0) === T_CONST) {
                continue;
            }
            foreach ($context['classes'] as $class) {
                if ($class['start'] < $i && $i < $class['end']) {
                    continue 2;
                }
            }
            $function = ($tokens[$i + 1]->id ?? 0) === T_FUNCTION;
            $raw = '';
            for ($j = $i + ($function ? 2 : 1); isset($tokens[$j]) && $tokens[$j]->text !== ';'; ++$j) {
                $raw .= $tokens[$j]->id === T_AS ? ' as ' : $tokens[$j]->text;
            }
            $prefix = '';
            if (preg_match('/^(.*\\\\)\{(.*)\}$/s', $raw, $match)) {
                $prefix = $match[1];
                $raw = $match[2];
            }
            foreach (explode(',', $raw) as $item) {
                $parts = preg_split('/\s+as\s+/i', trim($item));
                $name = ltrim($prefix . $parts[0], '\\');
                $short = explode('\\', $name);
                $alias = $parts[1] ?? end($short);
                $context[$function ? 'functions' : 'aliases'][strtolower($alias)] = strtolower($name);
            }
        }
        foreach ($context['classes'] as &$class) {
            $class['parent'] = $class['parentRaw'] ? self::resolveName($class['parentRaw'], $context) : '';
        }

        return $context;
    }

    private static function signatures(array $contexts): array
    {
        $base = ['message', 'variables', 'code', 'protected', 'from'];
        $adapter = ['message', 'variables', 'code', 'from'];
        $signatures = [];
        foreach (['baseexception', 'informationexception', 'fossbilling\\core\\exception\\baseexception', 'fossbilling\\core\\exception\\informationexception'] as $name) {
            $signatures[$name] = $base;
        }
        foreach (['payment_exception', 'registrar_exception', 'server_exception'] as $name) {
            $signatures[$name] = $adapter;
        }
        $pending = [];
        foreach ($contexts as $context) {
            foreach ($context['classes'] as $class) {
                if (!array_key_exists($class['name'], $signatures)) {
                    $pending[] = $class;
                }
            }
        }
        do {
            $changed = false;
            foreach ($pending as $i => $class) {
                if (array_key_exists($class['parent'], $signatures) && $signatures[$class['parent']] !== null) {
                    $signatures[$class['name']] = $class['parameters'] ?? $signatures[$class['parent']];
                    unset($pending[$i]);
                    $changed = true;
                }
            }
        } while ($changed);
        foreach ($pending as $class) {
            $signatures[$class['name']] = null;
        }

        return $signatures;
    }

    private static function phpOccurrences(string $rel, array $context, array $signatures, array &$out, array &$errors): void
    {
        $tokens = $context['tokens'];
        $pairs = $context['pairs'];
        $deny = array_map(strtolower(...), ['DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'DatePeriod', 'Response', 'RedirectResponse', 'DOMDocument', 'PDO', 'ReflectionClass', 'AuthenticationRequiredException']);
        foreach ($tokens as $i => $token) {
            $name = null;
            $short = null;
            $constructor = '';
            $open = null;
            $acceptsSource = true;
            $function = strtolower(ltrim($token->text, '\\'));
            if ($token->id === T_STRING) {
                $function = $context['functions'][$function] ?? $function;
            }
            $helper = ['__trans' => '__trans', '__pluraltrans' => '__pluralTrans'][$function] ?? null;
            if ($helper !== null && ($tokens[$i + 1]->text ?? '') === '(' && !in_array($tokens[$i - 1]->id ?? 0, [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                $name = $helper;
                $open = $i + 1;
            } elseif ($token->id === T_NEW && isset($tokens[$i + 1]) && ($tokens[$i + 2]->text ?? '') === '(') {
                $parts = explode('\\', $tokens[$i + 1]->text);
                $short = end($parts);
                if (in_array(strtolower($short), $deny, true) || strtolower($short) === 'class') {
                    continue;
                }
                $constructor = self::resolveName($tokens[$i + 1]->text, $context);
                $open = $i + 2;
            } elseif (in_array(strtolower($token->text), ['parent', 'self', 'static'], true) && ($tokens[$i + 1]->id ?? 0) === T_DOUBLE_COLON && strtolower($tokens[$i + 2]->text ?? '') === '__construct' && ($tokens[$i + 3]->text ?? '') === '(') {
                $short = '__construct';
                $open = $i + 3;
                foreach (array_reverse($context['classes']) as $class) {
                    if ($class['start'] < $i && $i < $class['end']) {
                        $constructor = strtolower($token->text) === 'parent' ? $class['parent'] : $class['name'];

                        break;
                    }
                }
            } else {
                continue;
            }
            if ($name === '__trans') {
                $names = ['msgid', 'values', 'from'];
            } elseif ($name === '__pluralTrans') {
                $names = ['msgid', 'msgidPlural', 'number', 'values', 'from'];
            } else {
                $signature = $signatures[$constructor] ?? null;
                $names = $signature ?: ['message'];
                $acceptsSource = $signature !== null || ($short !== null && str_ends_with(strtolower($short), 'exception') && strtolower($short) !== 'exception' && !array_key_exists($constructor, $signatures));
            }
            $ref = $rel . ':' . $token->line;

            try {
                $args = self::arguments($tokens, $open, $pairs);
                if ($args === null) {
                    throw new \RuntimeException('unbalanced translation arguments');
                }
                $bound = self::bind($args, $names);
                $variants = self::messages($bound[$names[0]] ?? [], $name !== '__pluralTrans');
                if ($variants === null) {
                    continue;
                }
                if (in_array('from', $names, true)) {
                    foreach ($args as $arg) {
                        if (($arg[0]->id ?? 0) === T_ELLIPSIS) {
                            throw new \RuntimeException('argument unpacking prevents resolving the translation source');
                        }
                    }
                }
                $plural = null;
                if ($name === '__pluralTrans') {
                    $forms = self::messages($bound['msgidPlural'] ?? [], false);
                    if ($forms === null) {
                        continue;
                    }
                    $plural = $forms[0];
                }
                $from = self::source($acceptsSource ? ($bound['from'] ?? null) : null, 'core');
                foreach ($variants as $msgid) {
                    $out[] = [$msgid, $plural, $ref, $from];
                }
            } catch (\Throwable $e) {
                $errors[] = $ref . ': ' . $e->getMessage();
            }
        }
    }

    private static function twigOccurrences(Environment $twig, string $rel, string $code, ?string $module, array &$out, array &$errors): void
    {
        try {
            $tree = $twig->parse($twig->tokenize(new Source($code, $rel)));
        } catch (\Throwable $e) {
            $errors[] = $rel . ': ' . $e->getMessage();

            return;
        }
        $seen = new \SplObjectStorage();
        $walk = function (Node $node) use (&$walk, $rel, $module, &$out, &$errors, $seen): void {
            if ($seen->offsetExists($node)) {
                return;
            }
            $seen[$node] = true;
            if ($node instanceof FilterExpression && $node->getAttribute('name') === 'trans') {
                $value = $node->getNode('node');
                if ($value instanceof ConstantExpression && is_string($value->getAttribute('value'))) {
                    $ref = $rel . ':' . $value->getTemplateLine();

                    try {
                        $arguments = $node->getNode('arguments');
                        $source = $arguments->hasNode('from') ? $arguments->getNode('from') : ($arguments->hasNode('1') ? $arguments->getNode('1') : null);
                        $from = $module ?? 'core';
                        if ($source !== null) {
                            if (!$source instanceof ConstantExpression) {
                                throw new \RuntimeException('from must be a literal lowercase source ID or null');
                            }
                            $from = $source->getAttribute('value');
                            if ($from === null) {
                                $from = 'core';
                            } elseif (!is_string($from) || !preg_match('/\A[a-z][a-z0-9_-]*\z/', $from)) {
                                throw new \RuntimeException('from must be a literal lowercase source ID or null');
                            }
                        }
                        $out[] = [$value->getAttribute('value'), null, $ref, $from];
                    } catch (\Throwable $e) {
                        $errors[] = $ref . ': ' . $e->getMessage();
                    }
                }
            }
            foreach ($node as $child) {
                $walk($child);
            }
            if ($node instanceof ModuleNode) {
                foreach ($node->getAttribute('embedded_templates') as $embedded) {
                    $walk($embedded);
                }
            }
        };
        $walk($tree);
    }
}

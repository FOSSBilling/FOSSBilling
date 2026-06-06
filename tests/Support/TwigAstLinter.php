<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

declare(strict_types=1);

namespace Tests\Support;

use FOSSBilling\Twig\Extension\ApiExtension;
use FOSSBilling\Twig\Extension\FOSSBillingExtension;
use FOSSBilling\Twig\Extension\LegacyExtension;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Extension\CoreExtension;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\Binary\NullCoalesceExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\NodeTraverser;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Static analysis pass for FOSSBilling Twig templates.
 *
 * Walks the AST of every template and flags attribute accesses (x.y, x.y.z,
 * x['y']) that are not guarded by `is defined`, `?? defaultValue`, or
 * `| default(...)`.
 *
 * The render-everything strict-variables test catches the same class of bug
 * at render time, but only for code paths actually exercised in the harness.
 * This linter covers the rest by walking the AST: partials that are included
 * from other templates, conditional branches, etc. It runs much faster than
 * the render pass and gives stable, file:line-anchored findings.
 *
 * The output is intended to be diffed against a baseline file
 * (`tests/Strict/ast-linter-baseline.json`) so the test only fails on
 * regressions, not on existing patterns.
 */
final class TwigAstLinter
{
    /**
     * Globals that are always defined at template render time. Attribute
     * accesses whose root is one of these are exempt from the linter because
     * the render harness guarantees their presence.
     *
     * @var list<string>
     */
    public const KNOWN_GLOBALS = [
        'app_area',
        'current_theme',
        'CSRFToken',
        'FOSSBillingVersion',
        'default_currency',
        'theme',
        'request',
        'request_query',
        'request_path',
        'request_has_filters',
        'settings',
        'admin',
        'client',
        'guest',
        'mf',
    ];

    /**
     * @return list<array{file: string, line: int, snippet: string, root: string}>
     */
    public function lint(): array
    {
        $env = $this->buildEnvironment();
        $findings = [];
        foreach ($this->findTemplates() as $path) {
            $findings = array_merge($findings, $this->lintTemplate($env, $path));
        }

        return $findings;
    }

    private function buildEnvironment(): Environment
    {
        $loader = new CombinedTwigLoader(PATH_THEMES);
        $env = new Environment($loader, [
            'strict_variables' => true,
            'auto_reload' => true,
            'cache' => false,
            'debug' => false,
        ]);

        // Register the FOSSBilling extensions so the parser can resolve custom
        // filters (`trans`, `url`, ...) and functions (`has_permission`, ...)
        // — without these, every template that uses them throws at parse time
        // and the linter silently skips the whole file.
        $env->getExtension(CoreExtension::class);
        $env->addExtension(new AttributeExtension(ApiExtension::class));
        $env->addExtension(new AttributeExtension(FOSSBillingExtension::class));
        $env->addExtension(new AttributeExtension(LegacyExtension::class));

        return $env;
    }

    /**
     * @return list<string>
     */
    private function findTemplates(): array
    {
        $paths = [];
        $finder = new Finder();
        $finder->files()->in(PATH_MODS)->name('*.html.twig');
        foreach ($finder as $file) {
            $paths[] = $file->getPathName();
        }
        $finder = new Finder();
        $finder->files()->in(PATH_THEMES)->name('*.html.twig');
        foreach ($finder as $file) {
            $paths[] = $file->getPathName();
        }
        sort($paths);

        return $paths;
    }

    /**
     * @return list<array{file: string, line: int, snippet: string, root: string}>
     */
    private function lintTemplate(Environment $env, string $path): array
    {
        $source = (string) file_get_contents($path);

        try {
            $stream = $env->tokenize(new \Twig\Source($source, $path));
            $nodes = $env->parse($stream);
        } catch (\Throwable) {
            // Parser errors are surfaced by StrictVariablesTest, not here.
            return [];
        }

        $visitor = new LinterVisitor($path, $source);
        $traverser = new NodeTraverser($env, [$visitor]);
        $traverser->traverse($nodes);

        return $visitor->getFindings();
    }
}

/**
 * @internal used by {@see TwigAstLinter}
 */
final class LinterVisitor implements NodeVisitorInterface
{
    /**
     * @var list<array{file: string, line: int, snippet: string, root: string}>
     */
    private array $findings = [];

    /** @var list<Node> Stack of ancestor nodes (oldest at index 0). */
    private array $stack = [];

    /**
     * Names of variables known to be defined at the current AST position
     * (globals, `{% set %}` targets, `{% for %}` loop variables, embed/include
     * `with {…}` keys, …). GetAttrExpression whose root matches one of these
     * is exempt from the linter.
     *
     * Implemented as a stack of scopes so a `{% set foo %}` inside an `{% if %}`
     * doesn't leak to siblings after the if-block.
     *
     * @var list<array<string, true>>
     */
    private array $locals = [];

    public function __construct(
        private readonly string $file,
        private readonly string $source,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof SetNode) {
            // `{% set x = … %}` — record x as defined in the enclosing scope.
            $names = $this->collectSetNames($node);
            if (!empty($names)) {
                $top = end($this->locals);
                if ($top === false) {
                    $this->locals[] = array_fill_keys($names, true);
                } else {
                    foreach ($names as $name) {
                        $top[$name] = true;
                    }
                    $this->locals[count($this->locals) - 1] = $top;
                }
            }
        } elseif ($node instanceof ForNode) {
            // `{% for k, v in … %}` — record k, v (and `loop`).
            $this->locals[] = ['loop' => true];
            $target = $node->getNode('key_target');
            if ($target instanceof AssignNameExpression) {
                $name = $target->getAttribute('name');
                if (is_string($name) && $name !== '' && !str_starts_with($name, '_')) {
                    $top = end($this->locals);
                    $top[$name] = true;
                    $this->locals[count($this->locals) - 1] = $top;
                }
            }
            $valueTarget = $node->getNode('value_target');
            if ($valueTarget instanceof AssignNameExpression) {
                $name = $valueTarget->getAttribute('name');
                if (is_string($name) && $name !== '' && !str_starts_with($name, '_')) {
                    $top = end($this->locals);
                    $top[$name] = true;
                    $this->locals[count($this->locals) - 1] = $top;
                }
            }
        }

        if (!$node instanceof GetAttrExpression) {
            $this->stack[] = $node;

            return $node;
        }

        $root = $this->resolveRootName($node);
        if ($root !== null && $this->isKnownName($root)) {
            $this->stack[] = $node;

            return $node;
        }

        if (!$this->isGuarded($node)) {
            $line = $node->getTemplateLine();
            $this->findings[] = [
                'file' => $this->file,
                'line' => $line,
                'snippet' => $this->extractLine($line),
                'root' => $root ?? '?',
            ];
        }

        $this->stack[] = $node;

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        array_pop($this->stack);

        if ($node instanceof ForNode) {
            array_pop($this->locals);
        }

        return $node;
    }

    /**
     * Collect target names from a SetNode, which can carry multiple
     * assignments in one statement: `{% set a, b = foo, bar %}`.
     *
     * @return list<string>
     */
    private function collectSetNames(SetNode $node): array
    {
        $names = [];
        $namesNode = $node->getNode('names');
        if ($namesNode instanceof Node) {
            foreach ($namesNode as $child) {
                if ($child instanceof AssignNameExpression) {
                    $name = $child->getAttribute('name');
                    if (is_string($name) && $name !== '' && !str_starts_with($name, '_')) {
                        $names[] = $name;
                    }
                }
            }
        }

        return $names;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Check whether $name is known to be defined in the current scope
     * (a render global or a local introduced by `{% set %}`/`{% for %}`).
     */
    private function isKnownName(string $name): bool
    {
        if (in_array($name, TwigAstLinter::KNOWN_GLOBALS, true)) {
            return true;
        }
        foreach ($this->locals as $scope) {
            if (isset($scope[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{file: string, line: int, snippet: string, root: string}>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    /**
     * Walk up the GetAttrExpression chain and return the root variable name
     * if it is a simple `name`/`name.attr`/`name.attr.attr` access.
     */
    private function resolveRootName(GetAttrExpression $node): ?string
    {
        $current = $node;
        while ($current instanceof GetAttrExpression) {
            $attr = $current->getNode('attribute');
            if (!$attr instanceof \Twig\Node\Expression\ConstantExpression) {
                return null;
            }
            $current = $current->getNode('node');
        }

        if ($current instanceof \Twig\Node\Expression\NameExpression) {
            return $current->getAttribute('name');
        }

        return null;
    }

    /**
     * Check whether this GetAttrExpression is wrapped in a guard expression:
     * - `is defined` (positive only)
     * - `?? defaultValue` (left side)
     * - `| default(...)` (immediate parent filter)
     */
    private function isGuarded(GetAttrExpression $node): bool
    {
        // When enterNode is called for $node, $node is NOT yet in the stack —
        // the top of the stack is the parent. Use that directly to start the
        // walk; for subsequent ancestors, search the stack for the current node.
        $current = end($this->stack);
        if ($current === false) {
            return false;
        }
        $child = $node;
        while ($current !== null) {
            if ($current instanceof TestExpression) {
                if ($current->getNode('node') === $child) {
                    $name = $current->getAttribute('name');
                    if ($name === 'defined') {
                        // `is defined`: guarded. `is not defined`: not guarded.
                        // The `not` attribute only exists when the test is
                        // negated (e.g. `is not defined`).
                        $isNegated = $current->hasAttribute('not') && $current->getAttribute('not');
                        if (!$isNegated) {
                            return true;
                        }
                    }
                }
            }
            if ($current instanceof NullCoalesceExpression) {
                if ($current->getNode('left') === $child) {
                    return true;
                }
            }
            if ($current instanceof FilterExpression) {
                if ($current->getNode('node') === $child) {
                    $name = $current->getAttribute('name');
                    if ($name === 'default') {
                        return true;
                    }
                }
            }
            $next = $this->parentOf($current);
            $child = $current;
            $current = $next;
        }

        return false;
    }

    /**
     * Find the parent of $node in the visitor's ancestor stack.
     */
    private function parentOf(Node $node): ?Node
    {
        $count = count($this->stack);
        for ($i = $count - 1; $i > 0; --$i) {
            if ($this->stack[$i] === $node) {
                return $this->stack[$i - 1];
            }
        }

        return null;
    }

    private function extractLine(int $line): string
    {
        $lines = explode("\n", $this->source);
        if (isset($lines[$line - 1])) {
            return trim($lines[$line - 1]);
        }

        return '';
    }
}

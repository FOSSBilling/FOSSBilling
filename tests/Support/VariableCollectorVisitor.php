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

use Twig\Environment;
use Twig\Node\EmbedNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Expression\Variable\TemplateVariable;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeTraverser;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Walks a parsed Twig AST and collects the names of every context variable
 * (e.g. `{{ foo }}`, `{% if bar %}`, `{% set baz = ... %}`) referenced anywhere
 * in the template.
 *
 * Also collects the keys of `{% embed %}` and `{% include %}` `with { ... }`
 * hashes, since those keys become variables inside the embedded/included
 * template's scope.
 *
 * Used by StrictTemplateRenderer to pre-populate the render context with
 * permissive stubs so that partials (which expect specific parent-passed
 * variables) render successfully in isolation. Any actual rendering error
 * raised despite every referenced variable being stubbed is a real bug.
 */
final class VariableCollectorVisitor implements NodeVisitorInterface
{
    /** @var array<string, true> Set of discovered variable names. */
    public array $variables = [];

    /** @var list<string> Static includes used when rendering a partial in isolation. */
    public array $templates = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        $this->collectTemplateReferences($node, $env);
        if ($node instanceof ContextVariable || $node instanceof TemplateVariable) {
            $name = $node->getAttribute('name');
            if (is_string($name) && $name !== '' && !str_starts_with($name, '_')) {
                $this->variables[$name] = true;
            }
        } elseif ($node instanceof AssignNameExpression) {
            $name = $node->getAttribute('name');
            if (is_string($name) && $name !== '' && !str_starts_with($name, '_')) {
                $this->variables[$name] = true;
            }
        } elseif ($node instanceof EmbedNode || $node instanceof IncludeNode) {
            // The `with { foo: bar }` block on embed/include passes its keys
            // as variables into the embedded template. Stub those too.
            $variables = $node->hasNode('variables') ? $node->getNode('variables') : null;
            if ($variables instanceof ArrayExpression) {
                foreach ($variables->getKeyValuePairs() as $pair) {
                    $key = $pair['key'];
                    if ($key instanceof ConstantExpression) {
                        $k = $key->getAttribute('value');
                        if (is_string($k) && $k !== '' && !str_starts_with($k, '_')) {
                            $this->variables[$k] = true;
                        }
                    }
                }
            }
        }

        return $node;
    }

    private function collectTemplateReferences(Node $node, Environment $env): void
    {
        if ($node instanceof ModuleNode) {
            if ($node->hasNode('parent')) {
                $this->collectTemplateName($node->getNode('parent'));
            }
            // Twig stores embedded blocks outside the ordinary child-node tree.
            (new NodeTraverser($env, [$this]))->traverse($node->getAttribute('embedded_templates'));
        } elseif ($node instanceof IncludeNode && !$node instanceof EmbedNode) {
            $this->collectTemplateName($node->getNode('expr'));
        }
    }

    private function collectTemplateName(Node $expression): void
    {
        if ($expression instanceof ConstantExpression && is_string($expression->getAttribute('value'))) {
            $this->templates[] = $expression->getAttribute('value');
        }
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @return list<string>
     */
    public function getVariableNames(): array
    {
        return array_keys($this->variables);
    }
}

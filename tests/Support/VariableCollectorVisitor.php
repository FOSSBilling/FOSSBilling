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
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Expression\Variable\TemplateVariable;
use Twig\Node\Node;
use Twig\NodeTraverser;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Walks a parsed Twig AST and collects the names of every context variable
 * (e.g. `{{ foo }}`, `{% if bar %}`, `{% set baz = ... %}`) referenced anywhere
 * in the template.
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

    public function enterNode(Node $node, Environment $env): Node
    {
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
        }

        return $node;
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

<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Twig\Extension;

use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/** Infers translation sources once during compilation, never from the call stack. */
final class TranslationExtension extends AbstractExtension implements NodeVisitorInterface
{
    /** @return list<NodeVisitorInterface> */
    public function getNodeVisitors(): array
    {
        return [$this];
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof FilterExpression || $node->getAttribute('name') !== 'trans') {
            return $node;
        }

        $arguments = $node->getNode('arguments');
        if ($arguments->hasNode('from') || $arguments->hasNode('1')) {
            return $node;
        }

        $path = $node->getSourceContext()?->getPath();
        if (!$path || !Path::isAbsolute($path) || !Path::isBasePath(PATH_MODS, $path)) {
            return $node;
        }

        $relative = Path::makeRelative($path, PATH_MODS);
        $module = explode('/', $relative)[0];
        $from = strtolower($module);
        if ($module !== ucfirst($from) || preg_match('/\A[a-z][a-z0-9_-]*\z/', $from) !== 1) {
            return $node;
        }

        $node->setNode('arguments', new Nodes([
            ...iterator_to_array($arguments),
            'from' => new ConstantExpression($from, $node->getTemplateLine()),
        ]));

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}

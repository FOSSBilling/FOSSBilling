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

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * Walks every `function toApiArray` under `src/modules` and reports fields
 * that are assigned to `$data['xxx']` only inside conditional blocks. The
 * toApiArray method is the canonical bridge between a service-layer model
 * and the JSON returned to the API; if a template reads a key that is only
 * set inside an `if ($identity instanceof \Model_Admin)` branch, the
 * template raises `Key "xxx" does not exist` under strict_variables, or
 * returns a silent empty value under permissive rendering, when the
 * response is consumed by a non-admin caller.
 *
 * Findings shape:
 * <code>
 * [
 *     'modules/Foo/Service.php' => [
 *         'top' => ['config', 'total', ...],   // assigned unconditionally
 *         'conditional' => [
 *             'plugin' => ['if/else', 'if/else'],  // never assigned at the top
 *         ],
 *     ],
 * ]
 * </code>
 */
final class ToApiArrayAuditor
{
    private readonly \PhpParser\Parser $parser;
    private readonly NodeFinder $nodeFinder;

    public function __construct(private readonly string $srcDir)
    {
        $this->parser = new ParserFactory()->createForHostVersion();
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @return array<string, array{top: list<string>, conditional: array<string, list<string>>}>
     */
    public function audit(): array
    {
        $findings = [];

        $finder = new Finder();
        $finder->files()->in($this->srcDir . '/modules')->name('*.php');

        foreach ($finder as $file) {
            $code = (string) $file->getContents();

            try {
                $ast = $this->parser->parse($code);
            } catch (\Throwable) {
                continue;
            }
            if ($ast === null) {
                continue;
            }

            $methods = $this->nodeFinder->findInstanceOf($ast, ClassMethod::class);
            foreach ($methods as $method) {
                if ($method->name->toString() !== 'toApiArray') {
                    continue;
                }
                if ($method->stmts === null) {
                    continue;
                }

                $relPath = str_replace($this->srcDir . '/', '', $file->getPathName());
                $fields = $this->auditMethodBody($method->stmts);

                $top = [];
                $conditional = [];
                foreach ($fields as $name => $locations) {
                    $hasTop = false;
                    $conditionalLocs = [];
                    foreach ($locations as $loc) {
                        if ($loc === 'top') {
                            $hasTop = true;
                        } else {
                            $conditionalLocs[] = $loc;
                        }
                    }
                    if ($hasTop) {
                        $top[] = $name;
                    } elseif (!empty($conditionalLocs)) {
                        $conditional[$name] = $conditionalLocs;
                    }
                }
                if (!empty($conditional)) {
                    $findings[$relPath] = [
                        'top' => $top,
                        'conditional' => $conditional,
                    ];
                }
            }
        }

        ksort($findings);

        return $findings;
    }

    /**
     * Walk a list of statements and return a map of `$data['xxx']` field
     * names to the set of places they are assigned.
     *
     * @param Node\Stmt[] $stmts
     *
     * @return array<string, list<string>>
     */
    private function auditMethodBody(array $stmts): array
    {
        $result = [];

        foreach ($stmts as $stmt) {
            $this->collectAssignments($stmt, $result, 'top');
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $result
     */
    private function collectAssignments(Node $node, array &$result, string $where): void
    {
        if ($node instanceof Node\Stmt\If_) {
            $childWhere = 'if';
            if ($node->elseifs !== null && count($node->elseifs) > 0) {
                $childWhere = 'if (with elseifs)';
            }
            if ($node->else !== null) {
                $childWhere = 'if/else';
            }
            foreach ($node->stmts as $child) {
                $this->collectAssignments($child, $result, $childWhere);
            }
            foreach ($node->elseifs as $elseif) {
                foreach ($elseif->stmts as $child) {
                    $this->collectAssignments($child, $result, $childWhere);
                }
            }
            if ($node->else !== null) {
                foreach ($node->else->stmts as $child) {
                    $this->collectAssignments($child, $result, $childWhere);
                }
            }

            return;
        }

        if ($node instanceof Node\Stmt\For_ || $node instanceof Node\Stmt\Foreach_ || $node instanceof Node\Stmt\While_ || $node instanceof Node\Stmt\Do_) {
            $childWhere = 'loop';
            foreach ($node->stmts as $child) {
                $this->collectAssignments($child, $result, $childWhere);
            }

            return;
        }

        if ($node instanceof Node\Stmt\Switch_) {
            foreach ($node->cases as $case) {
                foreach ($case->stmts as $child) {
                    $this->collectAssignments($child, $result, 'switch');
                }
            }

            return;
        }

        if ($node instanceof Node\Stmt\TryCatch) {
            $this->collectAssignments($node->try, $result, $where);
            foreach ($node->catches as $catch) {
                $this->collectAssignments($catch, $result, $where);
            }
            if ($node->finally !== null) {
                $this->collectAssignments($node->finally, $result, $where);
            }

            return;
        }

        if ($node instanceof Node\Stmt\Block) {
            foreach ($node->stmts as $child) {
                $this->collectAssignments($child, $result, $where);
            }

            return;
        }

        if ($node instanceof Node\Stmt\Expression) {
            $this->collectAssignments($node->expr, $result, $where);

            return;
        }

        if ($node instanceof Assign) {
            $field = $this->extractDataFieldName($node->var);
            if ($field !== null) {
                $result[$field][] = $where;
            }
        }
    }

    /**
     * Extract the field name from a `$data['xxx']` or `$data['xxx'][]` access
     * chain, or null if the chain doesn't terminate at a string-keyed
     * `$data` access.
     */
    private function extractDataFieldName(Node $node): ?string
    {
        if ($node instanceof ArrayDimFetch) {
            if ($node->dim instanceof Node\Scalar\String_) {
                $inner = $node->var;
                if ($inner instanceof Variable && $inner->name === 'data') {
                    return $node->dim->value;
                }
            }

            // Recurse in case of chained access like $data['x']['y'].
            return $this->extractDataFieldName($node->var);
        }

        return null;
    }
}

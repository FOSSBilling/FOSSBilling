<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Twig;

use Twig\Environment;

final class SandboxedStringRenderer
{
    public static function render(
        Environment $twig,
        string $template,
        array $vars,
        string $errorPrefix,
        ?callable $onSecurityError = null,
        bool $protectErrors = false,
    ): string {
        try {
            $render = static fn (): string => $twig->createTemplate($template)->render($vars);

            return $protectErrors
                ? \FOSSBilling\Core\Exception\BaseException::withProtectedDiagnostics($render)
                : $render();
        } catch (\Twig\Error\Error $e) {
            // Runtime diagnostics can contain interpolated single-use secrets.
            // Redact before constructing an application exception or logging it.
            if ($protectErrors) {
                throw new \FOSSBilling\Core\Exception\InformationException($errorPrefix . ' rendering failed', protected: true);
            }
            if ($e instanceof \Twig\Sandbox\SecurityError) {
                if ($onSecurityError !== null) {
                    $onSecurityError($e);
                }

                throw new \FOSSBilling\Core\Exception\InformationException($errorPrefix . ' contains disallowed Twig syntax: ' . $e->getMessage());
            }
            if ($e instanceof \Twig\Error\SyntaxError) {
                throw new \FOSSBilling\Core\Exception\InformationException($errorPrefix . ' syntax error: ' . $e->getMessage());
            }

            throw new \FOSSBilling\Core\Exception\InformationException($errorPrefix . ' rendering error: ' . $e->getMessage());
        }
    }
}

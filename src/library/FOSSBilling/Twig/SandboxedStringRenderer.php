<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

use Twig\Environment;

final class SandboxedStringRenderer
{
    public static function render(
        Environment $twig,
        string $template,
        array $vars,
        string $errorPrefix,
        ?callable $onSecurityError = null
    ): string {
        try {
            return $twig->createTemplate($template)->render($vars);
        } catch (\Twig\Sandbox\SecurityError $e) {
            if ($onSecurityError !== null) {
                $onSecurityError($e);
            }

            throw new \FOSSBilling\InformationException($errorPrefix . ' contains disallowed Twig syntax: ' . $e->getMessage());
        } catch (\Twig\Error\SyntaxError $e) {
            throw new \FOSSBilling\InformationException($errorPrefix . ' syntax error: ' . $e->getMessage());
        } catch (\Twig\Error\Error $e) {
            throw new \FOSSBilling\InformationException($errorPrefix . ' rendering error: ' . $e->getMessage());
        }
    }
}

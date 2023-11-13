<?php

declare(strict_types=1);
/**
 * Copyright 2023 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\TwigExtensions;

use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DebugBar extends AbstractExtension
{
    protected ?\Pimple\Container $di;
    private JavascriptRenderer $debugbarRenderer;

    public function __construct(StandardDebugBar $debugBar)
    {
        $this->debugbarRenderer = $debugBar->getJavascriptRenderer();
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('DebugBar_renderHead', [$this, 'renderHead'], ['is_safe' => ['html']]),
            new TwigFunction('DebugBar_render', [$this, 'render'], ['is_safe' => ['html']])
        ];
    }

    public function getName(): string
    {
        return 'DebugBar';
    }

    public function renderHead(): string
    {
        if (1 === 1) { // Environment::isDevelopment() once it's merged
            return $this->debugbarRenderer->renderHead();
        } else {
            return '';
        }
    }

    public function render(): string
    {
        if (1 === 1) { // Environment::isDevelopment() once it's merged
            return $this->debugbarRenderer->render();
        } else {
            return '';
        }
    }
}

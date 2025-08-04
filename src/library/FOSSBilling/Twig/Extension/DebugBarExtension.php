<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig\Extension;

use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use FOSSBilling\Environment;
use Twig\Attribute\AsTwigFunction;

class DebugBarExtension
{
    private readonly JavascriptRenderer $debugbarRenderer;

    /**
     * @param StandardDebugBar $debugBar The Debugbar instance.
     */
    public function __construct(StandardDebugBar $debugBar)
    {
        $this->debugbarRenderer = $debugBar->getJavascriptRenderer();
    }

    /**
     * Renders the PHP Debugbar's head section.
     *
     * @return string The rendered head section of the Debugbar, if in
     *                development mode. Returns an empty string if not.
     */
    #[AsTwigFunction('debug_bar_render_head', isSafe: ['html'])]
    public function renderHead(): string
    {
        return (Environment::isDevelopment()) ? $this->debugbarRenderer->renderHead() : '';
    }

    /**
     * Renders the PHP Debugbar.
     *
     * @return string The rendered Debugbar, if in development mode. Returns
     *                an empty string otherwise.
     */
    #[AsTwigFunction('debug_bar_render', isSafe: ['html'])]
    public function render(): string
    {
        return (Environment::isDevelopment()) ? $this->debugbarRenderer->render() : '';
    }
}

<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extra\Markdown\MarkdownInterface;

class FOSSBillingMarkdown implements MarkdownInterface
{
    private \Pimple\Container $di;
    private $converter;

    /**
     * FOSSBillingMarkdown constructor.
     *
     * @param \Pimple\Container $di DI container.
     */
    public function __construct(\Pimple\Container $di)
    {
        $this->di = $di;

        // Default configuration with security-focused settings.
        $config = [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ];

        // Get default attributes from the theme service, if set.
        $defaultAttributes = $this->di['mod_service']('theme')->getDefaultMarkdownAttributes() ?? [];
        if (!empty($defaultAttributes)) {
            foreach ($defaultAttributes as $class => $classAttributes) {
                $reflectionClass = new \ReflectionClass($class);
                $className = $reflectionClass->getName();
                $config['default_attributes'][$className] = $classAttributes;
            }
        }

        // Create environment and add extensions.
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        if (!empty($config['default_attributes'])) {
            $environment->addExtension(new DefaultAttributesExtension());
        }

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Convert markdown to HTML.
     *
     * @param string $body The markdown content to convert.
     *
     * @return string The converted HTML.
     */
    public function convert(string $body): string
    {
        return $this->converter->convert($body)->getContent();
    }
}

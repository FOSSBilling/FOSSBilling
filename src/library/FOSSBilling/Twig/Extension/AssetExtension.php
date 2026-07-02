<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig\Extension;

use Symfony\Component\Filesystem\Path;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

class AssetExtension
{
    private array $cacheBusters = [];

    public function __construct(private ?\Pimple\Container $di)
    {
    }

    private function getLoadedAssets(): array
    {
        if (!$this->di->offsetExists('loaded_assets')) {
            $this->di['loaded_assets'] = [];
        }

        return $this->di['loaded_assets'];
    }

    private function markAssetAsLoaded(string $path): void
    {
        $assets = $this->getLoadedAssets();
        $assets[] = $this->normalizeAssetPath($path);
        $this->di['loaded_assets'] = $assets;
    }

    private function isAssetLoaded(string $path): bool
    {
        return in_array($this->normalizeAssetPath($path), $this->getLoadedAssets(), true);
    }

    private function normalizeAssetPath(string $path): string
    {
        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path);
            $path = $parsed['path'] ?? $path;
        }

        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        return ltrim($path, '/\\');
    }

    private function getCacheBuster(string $path): string
    {
        $normalizedPath = $this->normalizeAssetPath($path);

        if (isset($this->cacheBusters[$normalizedPath])) {
            return $this->cacheBusters[$normalizedPath];
        }

        $filePath = Path::join(PATH_ROOT, $normalizedPath);

        if (is_file($filePath)) {
            $buster = hash_file('xxh32', $filePath);
        } else {
            $buster = hash('xxh32', $normalizedPath);
        }

        $this->cacheBusters[$normalizedPath] = $buster;

        return $buster;
    }

    #[AsTwigFilter('asset_url', isSafe: ['html'], needsEnvironment: true)]
    public function assetUrl(Environment $env, string $asset): string
    {
        $globals = $env->getGlobals();
        $themeCode = $globals['current_theme'] ?? ($globals['theme']['code'] ?? null);

        return SYSTEM_URL . 'themes/' . $themeCode . '/assets/' . $asset;
    }

    #[AsTwigFilter('public_asset_url', isSafe: ['html'])]
    public function publicAssetUrl(?string $asset): string
    {
        if ($asset === null) {
            return '';
        }

        return SYSTEM_URL . 'public/assets/' . ltrim($asset, '/');
    }

    #[AsTwigFilter('script_tag', isSafe: ['html'])]
    public function scriptTag(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        $escapedPath = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedCacheBuster = htmlspecialchars($this->getCacheBuster($path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<script src="%s?%s"></script>', $escapedPath, $escapedCacheBuster);
    }

    #[AsTwigFilter('stylesheet_tag', isSafe: ['html'])]
    public function stylesheetTag(?string $path, ?string $media = null): string
    {
        if ($path === null) {
            return '';
        }

        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        $escapedPath = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedCacheBuster = htmlspecialchars($this->getCacheBuster($path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedMedia = htmlspecialchars($media ?? 'screen', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $escapedPath, $escapedCacheBuster, $escapedMedia);
    }

    #[AsTwigFunction('wysiwyg', isSafe: ['html'])]
    public function wysiwyg(?string $selector, array $options = []): string
    {
        if ($selector === null) {
            return '';
        }

        $options['adapter'] ??= 'ckeditor';

        $selectorJson = json_encode($selector, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $optionsJson = json_encode($options, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return implode("\n", [
            $this->stylesheetTag($this->publicAssetUrl('editor/ckeditor.css')),
            $this->scriptTag($this->publicAssetUrl('editor/ckeditor.js')),
            <<<HTML
                <script>
                    FOSSBilling.ready(function () {
                        FOSSBilling.editor.init({$selectorJson}, {$optionsJson});
                    });
                </script>
                HTML,
        ]);
    }
}

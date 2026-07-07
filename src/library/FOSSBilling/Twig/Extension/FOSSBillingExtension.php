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

use Composer\InstalledVersions;
use DiceBear\Avatar;
use DiceBear\Style;
use FOSSBilling\Environment as AppEnvironment;
use FOSSBilling\Twig\Enum\AppArea;
use Symfony\Component\Filesystem\Path;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

class FOSSBillingExtension
{
    private array $cacheBusters = [];
    private ?Style $avatarStyle = null;
    private array $avatarDataUris = [];

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
        $normalizedPath = $this->normalizeAssetPath($path);
        $loadedAssets = $this->getLoadedAssets();

        return in_array($normalizedPath, $loadedAssets, true);
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

    #[AsTwigFunction('render_widgets', isSafe: ['html'], needsEnvironment: true)]
    public function renderWidgets(Environment $env, string $slot, array $context = []): string
    {
        $widgets = $this->di['mod_service']('Widgets')->getSlotWidgets($slot);

        if (empty($widgets)) {
            return '';
        }

        $output = '';

        foreach ($widgets as $widget) {
            try {
                $templateName = 'widgets/' . $widget['template'] . '.html.twig';
                $output .= $env->render($templateName, $context);
            } catch (\Throwable $e) {
                $output .= $env->render('widgets/mod_widgets_error.html.twig', array_merge($context, [
                    'widget' => [
                        'slot' => $slot,
                        'mod_name' => $widget['module'],
                        'template' => $widget['template'],
                    ],
                    'error' => AppEnvironment::isDevelopment() ? $e->getMessage() : null,
                ]));
            }
        }

        return $output;
    }

    #[AsTwigFunction('svg_sprite', isSafe: ['html'], needsEnvironment: true)]
    public function svgSprite(Environment $env): string
    {
        $globals = $env->getGlobals();
        $themeCode = $globals['current_theme'] ?? ($globals['theme']['code'] ?? null);

        if ($themeCode === null) {
            return '';
        }

        $spritePath = Path::join(PATH_THEMES, $themeCode, 'assets/build/symbol/icons-sprite.svg');

        if (!$this->di['filesystem']->exists($spritePath)) {
            return '';
        }

        return $this->di['filesystem']->readFile($spritePath);
    }

    #[AsTwigFunction('has_permission')]
    public function hasPermission(string $module, ?string $permission = null): bool
    {
        if (!$this->di['auth']->isAdminLoggedIn()) {
            return false;
        }

        try {
            return $this->di['mod_service']('Staff')->hasPermission($this->di['loggedin_admin'], $module, $permission);
        } catch (\Throwable) {
            return false;
        }
    }

    #[AsTwigFunction('antispam_honeypot')]
    public function antispamHoneypot(): array
    {
        if ($this->di['mod_service']('extension')->isExtensionActive('mod', 'antispam')) {
            $config = $this->di['mod_config']('Antispam');

            return [
                'enabled' => $config['honeypot_enabled'] ?? true,
                'field' => $config['honeypot_field'] ?? 'bio',
            ];
        }

        return [
            'enabled' => false,
            'field' => 'bio',
        ];
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

    #[AsTwigFilter('daysleft')]
    public function daysleft(?string $dateTime): int
    {
        if ($dateTime === null) {
            return 0;
        }

        $timeLeft = strtotime($dateTime) - time();

        return intval($timeLeft / 86400);
    }

    #[AsTwigFilter('file_size')]
    public function fileSize(?int $size): string
    {
        if ($size === null) {
            return '';
        }

        return \FOSSBilling\Tools::humanReadableBytes($size);
    }

    #[AsTwigFilter('hash')]
    public function hash(mixed $value, string $algo = 'xxh128'): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new \InvalidArgumentException(sprintf('Hash algorithm "%s" is not supported.', $algo));
        }

        return hash($algo, (string) $value);
    }

    #[AsTwigFunction('avatar', isSafe: ['html'])]
    public function avatar(?string $email, int $size = 40, string $classes = 'avatar', ?string $fallback = null, string $tag = 'span'): string
    {
        if ($email === null || trim($email) === '') {
            return htmlspecialchars($fallback ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $tag = in_array($tag, ['span', 'div'], true) ? $tag : 'span';
        $size = max(1, $size);
        $dataUri = $this->getAvatarDataUri($this->hash($email), $size);
        $styles = sprintf(
            'width: %1$dpx; height: %1$dpx; background-image: url("%2$s"); background-size: 100%% 100%%; background-position: center; background-repeat: no-repeat;',
            $size,
            $dataUri,
        );

        return sprintf(
            '<%1$s class="%2$s" style="%3$s"></%1$s>',
            $tag,
            htmlspecialchars(trim('db-avatar ' . $classes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($styles, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    private function getAvatarDataUri(string $seed, int $size): string
    {
        $size = max(1, $size);
        $cacheKey = $seed . ':' . $size;

        if (isset($this->avatarDataUris[$cacheKey])) {
            return $this->avatarDataUris[$cacheKey];
        }

        if (!$this->avatarStyle instanceof Style) {
            $basePath = InstalledVersions::getInstallPath('dicebear/styles');

            if ($basePath === null) {
                throw new \RuntimeException('The dicebear/styles package is not installed.');
            }

            $definitionPath = Path::join($basePath, 'src/identicon.json');

            if (!$this->di['filesystem']->exists($definitionPath)) {
                throw new \RuntimeException(sprintf('DiceBear style definition "%s" was not found.', $definitionPath));
            }

            $this->avatarStyle = Style::fromJson($this->di['filesystem']->readFile($definitionPath));
        }

        $avatar = new Avatar($this->avatarStyle, [
            'seed' => $seed,
            'size' => $size,
        ]);

        return $this->avatarDataUris[$cacheKey] = $avatar->toDataUri();
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

                        if (document.documentElement.getAttribute('data-bs-theme') === 'dark' || localStorage.getItem('theme') === 'dark') {
                            setTimeout(function () {
                                document.querySelectorAll('.ck-editor__main').forEach(function (element) {
                                    element.style.color = '#1d273b';
                                });
                            }, 1000);
                        }
                    });
                </script>
                HTML,
        ]);
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

    #[AsTwigFilter('timeago')]
    public function timeago(?string $dateTime): string
    {
        if ($dateTime === null) {
            return '';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return '';
        }

        $timeAgo = time() - $timestamp;
        if ($timeAgo < 0) {
            return '-';
        }
        $tokens = [
            315_705_600 => ['one' => __trans('decade'), 'other' => __trans('decades')],
            31_570_560 => ['one' => __trans('year'), 'other' => __trans('years')],
            2_630_880 => ['one' => __trans('month'), 'other' => __trans('months')],
            604_800 => ['one' => __trans('week'), 'other' => __trans('weeks')],
            86400 => ['one' => __trans('day'), 'other' => __trans('days')],
            3600 => ['one' => __trans('hour'), 'other' => __trans('hours')],
            60 => ['one' => __trans('minute'), 'other' => __trans('minutes')],
            1 => ['one' => __trans('second'), 'other' => __trans('seconds')],
        ];
        foreach ($tokens as $unit => $forms) {
            if ($timeAgo < $unit) {
                continue;
            }
            $numberOfUnits = (int) floor($timeAgo / $unit);
            $text = ($numberOfUnits === 1) ? $forms['one'] : $forms['other'];

            return sprintf('%d %s', $numberOfUnits, $text);
        }

        return '';
    }

    #[AsTwigFilter('trans')]
    public function trans(?string $text, ?array $values = null): string
    {
        return __trans($text, $values);
    }

    #[AsTwigFilter('truncate')]
    public function truncate(?string $text, int $length = 30, string $suffix = '...'): string
    {
        if ($text === null) {
            return '';
        }

        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . $suffix;
        }

        return $text;
    }

    #[AsTwigFilter('url', isSafe: ['html'], needsEnvironment: true)]
    public function url(Environment $env, string $path, ?array $query = null, ?string $area = null): string
    {
        $globals = $env->getGlobals();

        if ($area === null && isset($globals['app_area'])) {
            $area = $globals['app_area'];
        }

        if ($area !== null && AppArea::tryFrom($area) === null) {
            throw new \InvalidArgumentException(sprintf('Invalid app area "%s". Expected one of: %s.', $area, implode(', ', array_column(AppArea::cases(), 'value'))));
        }

        if ($area === AppArea::ADMIN->value) {
            return $this->di['url']->adminLink($path, $query);
        }

        return $this->di['url']->link($path, $query);
    }
}

<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Support;

use FOSSBilling\Twig\Extension\ApiExtension;
use FOSSBilling\Twig\Extension\FOSSBillingExtension;
use FOSSBilling\Twig\Extension\LegacyExtension;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownInterface;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\NodeTraverser;

class UrlAwarePermissiveContainer extends \Pimple\Container
{
    private readonly PermissiveStub $stub;
    /** @var array<string, mixed> */
    private array $store = [];

    public function __construct()
    {
        parent::__construct();
        $this->stub = new PermissiveStub();
        $this->store['loaded_assets'] = [];
        $this['filesystem'] = new \Symfony\Component\Filesystem\Filesystem();
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        if ($offset === 'url') {
            return new class {
                public function link(string $path, ?array $query = null): string
                {
                    return '';
                }

                public function adminLink(string $path, ?array $query = null): string
                {
                    return '';
                }
            };
        }
        // The LegacyExtension's `ip_country_name`/`ip_country_code` filters
        // call `$this->di['geoip']->country($ip)` and then read properties on
        // the returned record. The signatures are `: string`, so a permissive
        // stub would fail with a TypeError. Provide a stub object whose
        // `country()` call throws — the surrounding try/catch then returns
        // the empty-string fallback the real code emits when GeoIP is
        // unavailable. Mirrors the same handling in `PermissiveContainer`.
        if ($offset === 'geoip') {
            return new class {
                public function __call(string $name, array $args): mixed
                {
                    throw new \RuntimeException('geoip service not available in test environment');
                }

                public function __get(string $name): mixed
                {
                    throw new \RuntimeException('geoip service not available in test environment');
                }
            };
        }
        if (array_key_exists((string) $offset, $this->store)) {
            return $this->store[(string) $offset];
        }

        return $this->stub;
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->store[(string) $offset] = $value;
    }
}

/**
 * Helper that builds a strict-variables Twig environment and renders every template
 * with a permissive stub context, capturing any rendering errors.
 *
 * Used by StrictVariablesTest to enforce that all templates are safe to render under
 * `strict_variables => true`. The permissive stub absorbs every undefined variable, attribute,
 * method, and array key, so what remains is structural errors: syntax errors, missing macros,
 * missing parents/blocks, undefined filters/functions, and runtime errors raised by filters.
 */
final class StrictTemplateRenderer
{
    private function suppressNoticesAndWarnings(int $reportingLevel): int
    {
        return $reportingLevel & ~E_NOTICE & ~E_WARNING;
    }

    /**
     * @param array<string, mixed> $contextOverrides
     */
    public function renderTemplate(string $templatePath, array $contextOverrides = [], bool $emailMode = false): string
    {
        $previousLevel = error_reporting();
        error_reporting($this->suppressNoticesAndWarnings($previousLevel));

        try {
            $twig = $this->buildEnvironment($emailMode, stringifyUrls: true);
            $context = array_replace($this->buildContext($emailMode), $contextOverrides);

            try {
                $context = $this->enrichContextWithTemplateVariables($twig, $templatePath, $context, $emailMode);
                $context = array_replace($context, $contextOverrides);
            } catch (\Throwable) {
                // Let the render call surface any meaningful parser/loader error.
            }

            return $twig->render($this->relativeTemplateName($templatePath), $context);
        } finally {
            error_reporting($previousLevel);
        }
    }

    /**
     * @return list<array{file: string, template: string, error: string, category: string}>
     */
    public function renderAllTemplates(bool $emailMode = false): array
    {
        $findings = [];

        // Suppress E_NOTICE/E_WARNING only for the duration of this harness.
        // The render-everything pass routinely coerces a PermissiveStub to a
        // scalar (e.g. `|format_currency` on a stub amount, or arithmetic on a
        // stub count), which raises PHP notices that aren't bugs in the
        // templates themselves. Restoring the previous level on the way out
        // keeps unrelated tests in the suite under their normal reporting
        // settings.
        $previousLevel = error_reporting();
        error_reporting($this->suppressNoticesAndWarnings($previousLevel));

        try {
            $twig = $this->buildEnvironment($emailMode);

            $templates = $emailMode ? $this->discoverEmailTemplates() : $this->discoverAllTemplates();

            foreach ($templates as $templatePath) {
                $relative = $this->relativeTemplateName($templatePath);
                $context = $this->buildContext($emailMode);

                // Pre-populate the context with stubs for every variable referenced in
                // the template. This lets partials (which expect parent-passed
                // variables) render successfully in isolation.
                try {
                    $context = $this->enrichContextWithTemplateVariables($twig, $templatePath, $context, $emailMode);
                } catch (\Throwable) {
                    // If we can't parse the template, fall through to render and let
                    // the loader/parser surface a meaningful error.
                }

                try {
                    $twig->render($relative, $context);
                } catch (\Throwable $e) {
                    $findings[] = [
                        'file' => $templatePath,
                        'template' => $relative,
                        'error' => $this->summarizeError($e),
                        'category' => $this->categorizeError($e),
                    ];
                }
            }
        } finally {
            error_reporting($previousLevel);
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function enrichContextWithTemplateVariables(Environment $twig, string $templatePath, array $context, bool $emailMode): array
    {
        $source = new \Twig\Source((string) file_get_contents($templatePath), $this->relativeTemplateName($templatePath));

        try {
            $stream = $twig->tokenize($source);
            $nodes = $twig->parse($stream);
        } catch (\Throwable) {
            return $context;
        }

        $visitor = new VariableCollectorVisitor();
        $traverser = new NodeTraverser($twig, [$visitor]);
        $traverser->traverse($nodes);

        $stub = new PermissiveStub();
        foreach ($visitor->getVariableNames() as $name) {
            // Don't overwrite globals already provided in the context.
            if (!array_key_exists($name, $context)) {
                $context[$name] = $stub;
            }
        }

        return $context;
    }

    private function buildEnvironment(bool $emailMode, bool $stringifyUrls = false): Environment
    {
        $loader = new CombinedTwigLoader(PATH_THEMES);
        // Also register every module template directory under the unnamed path
        // so unqualified `{% include 'mod_foo.html.twig' %}` calls resolve.
        // CombinedTwigLoader already registers them under `@Module_area`
        // namespaces; this mirrors that for bare-name lookups.
        $finder = new Finder();
        $finder->directories()->in(PATH_MODS)->depth('== 2')->ignoreDotFiles(true)->name(['admin', 'client', 'email']);
        foreach ($finder as $dir) {
            $loader->addPath($dir->getPathName());
        }
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'auto_reload' => true,
            'cache' => false,
            'debug' => false,
        ]);

        $twig->getExtension(CoreExtension::class)->setNumberFormat(2, '.', '');
        $twig->getExtension(CoreExtension::class)->setTimezone('UTC');

        $dateFormatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC');
        $twig->addExtension(new IntlExtension($dateFormatter));
        $twig->addExtension(new MarkdownExtension());

        $twig->addExtension(new AttributeExtension(ApiExtension::class));
        $twig->addExtension(new AttributeExtension(FOSSBillingExtension::class));
        $twig->addExtension(new AttributeExtension(LegacyExtension::class));

        // Stub the debug bar functions so admin layout templates that reference
        // `{{ debug_bar_render_head() }}` and `{{ debug_bar_render() }}` parse
        // and render in the test environment.
        $twig->addFunction(new \Twig\TwigFunction('debug_bar_render_head', fn (): string => ''));
        $twig->addFunction(new \Twig\TwigFunction('debug_bar_render', fn (): string => ''));

        // Runtime loader: provides stub markdown + FOSSBilling/Legacy/Api
        // extension instances so filter/function method dispatch works in the
        // test environment. A PermissiveContainer absorbs every `$di['x']`
        // access so the extensions can render without a live DI graph.
        $di = $stringifyUrls ? $this->buildUrlAwareContainer() : new PermissiveContainer();
        $twig->addRuntimeLoader(new readonly class($di) implements \Twig\RuntimeLoader\RuntimeLoaderInterface {
            public function __construct(private \Pimple\Container $di)
            {
            }

            public function load($class)
            {
                if ($class === MarkdownRuntime::class) {
                    return new MarkdownRuntime(new class implements MarkdownInterface {
                        public function convert(string $body): string
                        {
                            return $body;
                        }
                    });
                }

                if ($class === FOSSBillingExtension::class) {
                    return new FOSSBillingExtension($this->di);
                }

                if ($class === LegacyExtension::class) {
                    return new LegacyExtension($this->di);
                }

                if ($class === ApiExtension::class) {
                    return new ApiExtension($this->di);
                }

                return null;
            }
        });

        $context = $this->buildContext($emailMode);
        foreach ($context as $name => $value) {
            $twig->addGlobal($name, $value);
        }

        return $twig;
    }

    private function buildUrlAwareContainer(): \Pimple\Container
    {
        return new UrlAwarePermissiveContainer();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(bool $emailMode): array
    {
        $stub = new PermissiveStub();

        if ($emailMode) {
            // Email templates have a different `guest` shape with precomputed globals.
            return [
                'request' => $stub,
                'request_query' => $stub,
                'request_path' => '/',
                'request_has_filters' => false,
                'CSRFToken' => 'test',
                'FOSSBillingVersion' => '0.0.0',
                'default_currency' => 'USD',
                'app_area' => 'email',
                'current_theme' => 'admin_default',
                'theme' => ['code' => 'admin_default', 'name' => 'admin_default', 'url' => '/themes/admin_default/'],
                'settings' => $stub,
                'guest' => [
                    'system_company' => [
                        'name' => 'Test Co',
                        'signature' => '',
                        'favicon_url' => '',
                    ],
                    'system_email' => [
                        'signature' => '',
                    ],
                ],
            ];
        }

        return [
            'request' => $stub,
            'request_query' => $stub,
            'request_path' => '/',
            'request_has_filters' => false,
            'CSRFToken' => 'test',
            'FOSSBillingVersion' => '0.0.0',
            'default_currency' => 'USD',
            'app_area' => 'admin',
            'current_theme' => 'admin_default',
            'theme' => ['code' => 'admin_default', 'name' => 'admin_default', 'url' => '/themes/admin_default/'],
            'settings' => $stub,
            'admin' => $stub,
            'client' => $stub,
            'guest' => $stub,
            'mf' => $stub, // Most templates import this; let missing macros surface as errors
        ];
    }

    /**
     * @return list<string>
     */
    private function discoverAllTemplates(): array
    {
        $paths = [];

        // Module templates
        $finder = new Finder();
        $finder->files()->in(PATH_MODS)->name('*.html.twig');
        foreach ($finder as $file) {
            $paths[] = $file->getPathName();
        }

        // Theme templates
        $finder = new Finder();
        $finder->files()->in(PATH_THEMES)->name('*.html.twig');
        foreach ($finder as $file) {
            $paths[] = $file->getPathName();
        }

        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function discoverEmailTemplates(): array
    {
        $paths = [];
        $finder = new Finder();
        $finder->files()->in(PATH_MODS)->path('templates/email')->name('*.html.twig');
        foreach ($finder as $file) {
            $paths[] = $file->getPathName();
        }
        sort($paths);

        return $paths;
    }

    private function relativeTemplateName(string $absolutePath): string
    {
        // Module templates live under `modules/<Module>/templates/<area>/` and
        // are referenced as `@<Module>_<area>/<basename>` (underscore join
        // because FilesystemLoader splits `@ns/template` on the first '/' and
        // rejects '/' in namespace names). Theme templates live under
        // `themes/<code>/html/` and are referenced by basename.
        if (str_starts_with($absolutePath, PATH_MODS . DIRECTORY_SEPARATOR)) {
            $relative = substr($absolutePath, strlen(PATH_MODS . DIRECTORY_SEPARATOR));
            $parts = explode(DIRECTORY_SEPARATOR, $relative, 4);
            if (count($parts) === 4) {
                $module = $parts[0];
                $area = $parts[2]; // skip 'templates' dir

                return '@' . $module . '_' . $area . '/' . basename($parts[3]);
            }
        }

        return basename($absolutePath);
    }

    private const int MAX_ERROR_MESSAGE_LENGTH = 250;

    private function summarizeError(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (strlen($message) > self::MAX_ERROR_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_ERROR_MESSAGE_LENGTH) . '...';
        }

        return $e::class . ': ' . $message;
    }

    /**
     * Classify a render error as either a real template bug or a test
     * infrastructure issue. The strict-variables test fails only on
     * `real-bug` findings; `test-infra` and `loader` issues are informational
     * because the permissive container/stub setup cannot satisfy strict
     * return-type hints or lookup every real template path.
     */
    private function categorizeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if ($e instanceof \Twig\Error\LoaderError) {
            // LoaderErrors during render are usually caused by child templates
            // (e.g. `partial_embed_styles.html.twig`) being looked up in the
            // current namespace rather than the main one. This is a quirk of
            // the render-everything harness, not a real template bug.
            return 'test-infra';
        }

        if ($e instanceof \Twig\Error\SyntaxError) {
            return 'real-bug';
        }

        if (str_contains($message, 'Variable ') && str_contains($message, ' does not exist')) {
            return 'real-bug';
        }

        if (str_contains($message, 'Key "') && str_contains($message, ' does not exist')) {
            return 'real-bug';
        }

        // Test infrastructure issues: type mismatches caused by PermissiveStub
        // being returned where a typed return is expected (string, array, etc.),
        // and method-on-null errors caused by stub methods that don't match real
        // service signatures.
        if (str_contains($message, 'Return value must be of type')) {
            return 'test-infra';
        }
        if (str_contains($message, 'on null')) {
            return 'test-infra';
        }
        if (str_contains($message, 'Unable to load')) {
            return 'test-infra';
        }
        if (str_contains($message, 'must be of type float') || str_contains($message, 'must be of type int')) {
            return 'test-infra';
        }
        if (str_contains($message, 'NumberFormatter::formatCurrency')) {
            return 'test-infra';
        }
        if (str_contains($message, 'is not callable')) {
            return 'test-infra';
        }
        if (str_contains($message, 'is not iterable')) {
            return 'test-infra';
        }

        return 'real-bug';
    }
}

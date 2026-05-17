<?php

declare(strict_types=1);

final class FOSSBilling_i18nTest extends BBTestCase
{
    private ?string $originalConfigContents = null;

    private ?array $originalCookie = null;

    private ?array $originalServer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $configContents = file_get_contents(PATH_CONFIG);
        if ($configContents === false) {
            self::fail('Failed to read the FOSSBilling config file.');
        }

        $this->originalConfigContents = $configContents;
        $this->originalCookie = $_COOKIE;
        $this->originalServer = $_SERVER;
    }

    public function testGetActiveLocaleFallsBackToConfiguredLocaleWhenBrowserDetectionDisabled(): void
    {
        $this->writeI18nConfig([
            'locale' => 'fr_FR',
            'auto_detect_locale' => false,
        ]);

        $_COOKIE = [];
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';

        $this->assertSame('fr_FR', FOSSBilling\i18n::getActiveLocale());
    }

    public function testGetActiveLocaleUsesBrowserLocaleWhenBrowserDetectionEnabled(): void
    {
        $this->writeI18nConfig([
            'locale' => 'fr_FR',
            'auto_detect_locale' => true,
        ]);

        $_COOKIE = [];
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';

        $this->assertSame('en_US', FOSSBilling\i18n::getActiveLocale());
    }

    protected function tearDown(): void
    {
        if ($this->originalConfigContents !== null) {
            file_put_contents(PATH_CONFIG, $this->originalConfigContents);
            clearstatcache(true, PATH_CONFIG);

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(PATH_CONFIG, true);
            }
        }

        @unlink(\Symfony\Component\Filesystem\Path::changeExtension(PATH_CONFIG, 'old.php'));

        if ($this->originalCookie !== null) {
            $_COOKIE = $this->originalCookie;
        }

        if ($this->originalServer !== null) {
            $_SERVER = $this->originalServer;
        }

        parent::tearDown();
    }

    private function writeI18nConfig(array $i18nConfig): void
    {
        $config = FOSSBilling\Config::getConfig();
        $config['i18n'] = array_merge($config['i18n'], $i18nConfig);

        $reflection = new ReflectionClass(FOSSBilling\Config::class);
        $method = $reflection->getMethod('prettyPrintArrayToPHP');
        $rendered = $method->invoke(null, $config);

        file_put_contents(PATH_CONFIG, $rendered);
        clearstatcache(true, PATH_CONFIG);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }
    }
}

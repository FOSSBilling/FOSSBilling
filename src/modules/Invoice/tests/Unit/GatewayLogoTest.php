<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\ServicePayGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function gatewayLogoPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgQAYAAA4AAbGa6gYAAAAASUVORK5CYII=');
}

function gatewayLogoAvif(): string
{
    return base64_decode('AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAADrbWV0YQAAAAAAAAAhaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAAAAAAAOcGl0bQAAAAAAAQAAAB5pbG9jAAAAAEQAAAEAAQAAAAEAAAETAAAAIgAAAChpaW5mAAAAAAABAAAAGmluZmUCAAAAAAEAAGF2MDFDb2xvcgAAAABqaXBycAAAAEtpcGNvAAAAFGlzcGUAAAAAAAAAAgAAAAIAAAAQcGl4aQAAAAADCAgIAAAADGF2MUOBAAwAAAAAE2NvbHJuY2x4AAEADQABgAAAABdpcG1hAAAAAAAAAAEAAQQBAoMEAAAAKm1kYXQSAAoIGAA2iAhoDCAyFBeHh4YhhJJJJlAAAABIVDZhrGGr');
}

function gatewayLogoCanNormalize(string $extension): bool
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
        return false;
    }
    $support = gd_info();
    $format = match ($extension) {
        'png' => 'PNG Support',
        'jpg' => 'JPEG Support',
        'webp' => 'WebP Support',
        'avif' => 'AVIF Support',
        default => '',
    };

    return ($support['PNG Support'] ?? false) && ($support[$format] ?? false);
}

function gatewayLogoService(MockHttpClient $client, Filesystem $filesystem): ServicePayGateway
{
    $di = container();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
    $em->shouldReceive('flush')->byDefault();
    $di['em'] = $em;
    $di['http_client'] = $client;
    $di['filesystem'] = $filesystem;
    $di['url'] = Mockery::mock(FOSSBilling\Url::class);
    $di['url']->shouldReceive('link')->andReturn('https://billing.example/');
    $service = new ServicePayGateway();
    $service->setDi($di);

    return $service;
}

function captureGatewayLogo(Filesystem $filesystem): object
{
    $stored = new stdClass();
    $stored->files = [];
    $filesystem->shouldReceive('dumpFile')->atLeast()->once()->andReturnUsing(static function (string $path, string $bytes) use ($stored): void {
        $stored->files[basename($path)] = $bytes;
        if (!isset($stored->bytes)) {
            $stored->bytes = $bytes;
            $stored->filename = basename($path);
        }
        expect($path)->toBe(Path::join(PATH_ROOT, 'public', 'gateways', 'custom', basename($path)));
    });

    return $stored;
}

test('gateway image import stores detected image bytes locally and does not retain the source URL', function (): void {
    $bytes = gatewayLogoPng();
    $filesystem = Mockery::mock(Filesystem::class);
    $stored = captureGatewayLogo($filesystem);
    $client = new MockHttpClient(new MockResponse($bytes));
    $service = gatewayLogoService($client, $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);

    expect($service->update($gateway, ['logo_url' => 'https://8.8.8.8/image.php']))->toBeTrue();
    expect($gateway->getCustomLogo())->toBe(hash('sha256', $stored->bytes) . '.png')
        ->and($gateway->getConfig())->toBeNull();
});

test('gateway image is preserved on ordinary updates and can be reset without fetching', function (): void {
    $client = new MockHttpClient(static fn () => throw new LogicException('Must not fetch'));
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService($client, $filesystem);
    $filename = str_repeat('a', 64) . '.png';
    $gateway = createEntity(PayGateway::class, ['enabled' => false, 'customLogo' => $filename]);

    $service->update($gateway, ['title' => 'Cards']);
    $service->update($gateway, ['logo_url' => '']);
    expect($gateway->getCustomLogo())->toBe($filename);
    $service->update($gateway, ['remove_custom_logo' => '0']);
    expect($gateway->getCustomLogo())->toBe($filename);
    $service->update($gateway, ['remove_custom_logo' => '1', 'logo_url' => 'https://8.8.8.8/logo.png']);
    expect($gateway->getCustomLogo())->toBeNull();
});

test('gateway image rejects invalid or private URLs without making a request or changing settings', function (mixed $url): void {
    $client = new MockHttpClient(static fn () => throw new LogicException('Must not fetch'));
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService($client, $filesystem);
    $service->getDi()['em']->shouldReceive('flush')->never();
    $gateway = createEntity(PayGateway::class, ['enabled' => false, 'name' => 'Original', 'customLogo' => 'original.png']);

    expect(fn () => $service->update($gateway, ['title' => 'Changed', 'logo_url' => $url]))->toThrow(FOSSBilling\Exception::class);
    expect($gateway->getName())->toBe('Original')->and($gateway->getCustomLogo())->toBe('original.png');
})->with([
    'file:///etc/passwd', 'ftp://8.8.8.8/logo.png', 'https://',
    'https://user:pass@8.8.8.8/logo.png', 'http://127.0.0.1/logo.png',
    'http://169.254.169.254/logo.png', 'http://192.168.1.1/logo.png',
    'http://[::1]/logo.png', 'http://localhost/logo.png',
    [null], [['https://8.8.8.8/logo.png']],
]);

test('gateway image rejects unsuccessful or unsafe downloads', function (string $bytes, int $status): void {
    $client = new MockHttpClient(new MockResponse($bytes, ['http_code' => $status]));
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService($client, $filesystem);
    $service->getDi()['em']->shouldReceive('flush')->never();
    $gateway = createEntity(PayGateway::class, ['enabled' => false, 'customLogo' => 'original.png']);

    expect(fn () => $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']))->toThrow(FOSSBilling\Exception::class);
    expect($gateway->getCustomLogo())->toBe('original.png');
})->with([
    'HTML' => ['<html>not an image</html>', 200],
    'invalid SVG' => ['<svg><path></svg>', 200],
    // cspell:disable-next-line
    'GIF' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200],
    'oversized' => [str_repeat('x', 2 * 1024 * 1024 + 1), 200],
    'not found' => ['', 404],
]);

test('gateway image rejects redirects to private networks', function (): void {
    $client = new MockHttpClient(new MockResponse('', [
        'http_code' => 302,
        'response_headers' => ['location: http://127.0.0.1/logo.png'],
        'redirect_url' => 'http://127.0.0.1/logo.png',
    ]));
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService($client, $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);

    expect(fn () => $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']))->toThrow(FOSSBilling\Exception::class);
});

test('active gateways use the imported local image and fall back if it is missing', function (bool $exists): void {
    $filename = str_repeat('a', 64) . '.png';
    $gateway = createEntity(PayGateway::class, ['gateway' => 'Custom', 'customLogo' => $filename, 'acceptedCurrencies' => '["USD"]']);
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'custom', $filename))->andReturn($exists);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'custom', str_repeat('a', 64) . '.avif'))->andReturn(false);
    $service = gatewayLogoService(new MockHttpClient(), $filesystem);
    $service->getPayGatewayRepository()->shouldReceive('findEnabledOrderedByIdDesc')->andReturn([$gateway]);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_LIBRARY, 'Payment', 'Adapter', 'default.png'))->andReturn(false);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'default.png'))->andReturn(true);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_LIBRARY, 'Payment', 'Adapter', 'custom.png'))->andReturn(false);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'custom.png'))->andReturn(false);

    $logo = $service->getActive([])[0]['logo'];
    expect($logo['logo'])->toBe(SYSTEM_URL . ($exists ? 'public/gateways/custom/' . $filename : 'public/gateways/default.png'));
})->with([true, false]);

test('active gateways keep the adapter display size for custom logos', function (array $adapterLogo, string $expectedHeight, string $expectedWidth): void {
    $filename = str_repeat('a', 64) . '.png';
    $gateway = createEntity(PayGateway::class, ['gateway' => 'Custom', 'customLogo' => $filename, 'acceptedCurrencies' => '["USD"]']);
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->byDefault()->andReturn(false);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'custom', $filename))->andReturn(true);
    $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'custom', str_repeat('a', 64) . '.avif'))->andReturn(false);

    $service = Mockery::mock(ServicePayGateway::class)->makePartial();
    $di = container();
    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(PayGatewayRepository::class);
    $repo->shouldReceive('findEnabledOrderedByIdDesc')->once()->andReturn([$gateway]);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
    $di['em'] = $em;
    $di['http_client'] = new MockHttpClient();
    $di['filesystem'] = $filesystem;
    $service->setDi($di);
    $service->shouldReceive('getPaymentAdapter')->once()->andReturn(new readonly class($adapterLogo) {
        public function __construct(private array $logo)
        {
        }

        public function getConfig(): array
        {
            return $this->logo === [] ? [] : ['logo' => $this->logo];
        }
    });
    if ($adapterLogo === []) {
        $filesystem->shouldReceive('exists')->with(Path::join(PATH_LIBRARY, 'Payment', 'Adapter', 'default.png'))->andReturn(false);
        $filesystem->shouldReceive('exists')->with(Path::join(PATH_ROOT, 'public', 'gateways', 'default.png'))->andReturn(true);
    }

    $logo = $service->getActive([])[0]['logo'];
    expect($logo['logo'])->toContain('public/gateways/custom/' . $filename)
        ->and($logo['height'])->toBe($expectedHeight)
        ->and($logo['width'])->toBe($expectedWidth);
})->with([
    'tall Stripe-style logo keeps its portrait box' => [['logo' => 'stripe.png', 'height' => '86px', 'width' => '65px'], '86px', '65px'],
    'default Stripe logo keeps its configured size' => [['logo' => 'stripe.png', 'height' => '30px', 'width' => '65px'], '30px', '65px'],
    'adapter without a logo falls back to the square default' => [[], '50px', '50px'],
]);

test('gateway image imports through a public redirect', function (): void {
    $bytes = gatewayLogoPng();
    $filesystem = Mockery::mock(Filesystem::class);
    $stored = captureGatewayLogo($filesystem);
    $client = new MockHttpClient([
        new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://1.1.1.1/logo.png'], 'redirect_url' => 'https://1.1.1.1/logo.png']),
        new MockResponse($bytes),
    ]);
    $service = gatewayLogoService($client, $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);

    $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']);
    expect($gateway->getCustomLogo())->toBe(hash('sha256', $stored->bytes) . '.png');
});

test('gateway image rejects a changed connection address and excessive advertised size', function (string $ip, int $size): void {
    $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($ip, $size): MockResponse {
        $options['on_progress'](0, $size, ['primary_ip' => $ip, 'url' => $url]);

        return new MockResponse(gatewayLogoPng());
    });
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService($client, $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);

    expect(fn () => $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']))->toThrow(FOSSBilling\Exception::class);
})->with([['127.0.0.1', 0], ['8.8.8.8', 2 * 1024 * 1024 + 1]]);

test('gateway settings render image import and default restoration controls', function (?string $imageUrl): void {
    $renderer = new Tests\Support\StrictTemplateRenderer();
    $html = $renderer->renderTemplate(PATH_MODS . '/Invoice/templates/admin/mod_invoice_gateway.html.twig', [
        'gateway' => [
            'id' => 1, 'title' => 'Cards', 'description' => null, 'form' => [], 'custom_logo_url' => $imageUrl, 'custom_logo_avif_url' => $imageUrl === null ? null : str_replace('.png', '.avif', $imageUrl),
            'accepted_currencies' => ['USD'], 'supports_one_time_payments' => true, 'supports_subscriptions' => false,
            'enabled' => true, 'test_mode' => false, 'allow_single' => true, 'callback' => 'https://billing.example/ipn.php',
        ],
    ]);

    expect($html)->toContain('name="logo_url"')->toContain('name="remove_custom_logo"')
        ->toContain('name="logo_file"')->toContain('enctype="multipart/form-data"');
    if ($imageUrl !== null) {
        expect($html)->toContain('src="' . $imageUrl . '"')->toContain('type="image/avif"')
            ->toContain('srcset="' . str_replace('.png', '.avif', $imageUrl) . '"');
    }
})->with([null, 'https://billing.example/public/gateways/custom/' . str_repeat('a', 64) . '.png']);

test('gateway image upload detects the format from bytes rather than the filename', function (string $bytes, string $extension): void {
    $path = tempnam(sys_get_temp_dir(), 'gateway-logo-');
    $realFilesystem = new Filesystem();
    $realFilesystem->dumpFile($path, $bytes);

    try {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('readFile')->with($path)->andReturn($bytes);
        $stored = captureGatewayLogo($filesystem);
        $service = gatewayLogoService(new MockHttpClient(static fn () => throw new LogicException('Must not fetch')), $filesystem);
        $gateway = createEntity(PayGateway::class, ['enabled' => false, 'customLogo' => 'old.png']);
        $file = new UploadedFile($path, 'misleading.gif', 'image/gif', UPLOAD_ERR_OK, true);

        expect($service->update($gateway, ['title' => 'Cards', 'logo_url' => 'ignored because a file is selected'], $file))->toBeTrue();
        expect($gateway->getName())->toBe('Cards');
        $outputExtension = gatewayLogoCanNormalize($extension) ? 'png' : $extension;
        expect($gateway->getCustomLogo())->toBe(hash('sha256', $stored->bytes) . '.' . $outputExtension);
        if ($extension === 'svg') {
            expect($stored->bytes)->not->toContain('<script');
            expect($stored->files)->toHaveCount(1);
        } else {
            $original = getimagesizefromstring($bytes);
            $processed = getimagesizefromstring($stored->bytes);
            expect($processed[0])->toBe($original[0])->and($processed[1])->toBe($original[1])->and($processed['mime'])->toBe(gatewayLogoCanNormalize($extension) ? 'image/png' : $original['mime']);
        }
    } finally {
        $realFilesystem->remove($path);
    }
})->with([
    'PNG' => [gatewayLogoPng(), 'png'],
    'AVIF' => [gatewayLogoAvif(), 'avif'],
    'legacy file with misleading JPEG extension' => [(new Filesystem())->readFile(PATH_ROOT . '/public/gateways/webmoney.jpg'), 'png'],
    'WebP' => [base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoCAAIAAUAmJaQAA3AA/v02aAA='), 'webp'],
    'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0L1 1"/></svg>', 'svg'],
]);

test('gateway image upload rejects errors, GIFs, and oversized files without changing the image', function (string $bytes, int $error): void {
    $path = tempnam(sys_get_temp_dir(), 'gateway-logo-');
    $realFilesystem = new Filesystem();
    $realFilesystem->dumpFile($path, $bytes);

    try {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('readFile')->with($path)->andReturn($bytes);
        $filesystem->shouldReceive('dumpFile')->never();
        $service = gatewayLogoService(new MockHttpClient(), $filesystem);
        $service->getDi()['em']->shouldReceive('flush')->never();
        $gateway = createEntity(PayGateway::class, ['enabled' => false, 'customLogo' => 'original.png', 'name' => 'Original']);
        $file = new UploadedFile($path, 'logo.png', 'image/png', $error, true);

        expect(fn () => $service->update($gateway, ['title' => 'Changed'], $file))->toThrow(FOSSBilling\Exception::class);
        expect($gateway->getCustomLogo())->toBe('original.png')->and($gateway->getName())->toBe('Original');
    } finally {
        $realFilesystem->remove($path);
    }
})->with([
    // cspell:disable-next-line
    'GIF' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), UPLOAD_ERR_OK],
    'too large' => [str_repeat('x', 2 * 1024 * 1024 + 1), UPLOAD_ERR_OK],
    'failed upload' => [gatewayLogoPng(), UPLOAD_ERR_PARTIAL],
]);

test('gateway URL import supports AVIF and sanitizes SVG before storing it', function (string $bytes, string $extension): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $stored = captureGatewayLogo($filesystem);
    $service = gatewayLogoService(new MockHttpClient(new MockResponse($bytes)), $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);

    $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo']);
    $outputExtension = gatewayLogoCanNormalize($extension) ? 'png' : $extension;
    expect($gateway->getCustomLogo())->toBe(hash('sha256', $stored->bytes) . '.' . $outputExtension);
    if ($extension === 'svg') {
        expect($stored->bytes)->not->toContain('<script')->toContain('<path');
    } else {
        expect(getimagesizefromstring($stored->bytes)['mime'])->toBe(gatewayLogoCanNormalize('avif') ? 'image/png' : 'image/avif');
    }
})->with([
    'AVIF' => [gatewayLogoAvif(), 'avif'],
    'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script><path d="M0 0L10 10"/></svg>', 'svg'],
]);

test('gateway SVG sanitizer preserves artwork while removing active and external content', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)" viewBox="0 0 10 10">'
        . '<defs><linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient></defs>'
        . '<style>@import "https://evil.example/style.css";</style><script>alert(1)</script>'
        . '<foreignObject><iframe src="https://evil.example"/></foreignObject>'
        . '<image href="https://evil.example/logo.png"/><use href="#recursive"/>'
        . '<path transform="translate(1, 2)" d="M0 0L10 10" fill="url(#g)" style="stroke:red;fill:url(https://evil.example/a);opacity:0.5"/>'
        . '<rect fill="&#117;rl(https://evil.example/a)"/>'
        . '</svg>';
    $clean = FOSSBilling\Sanitizer\GatewaySvgSanitizer::sanitize($svg);

    expect($clean)->toContain('linearGradient')->toContain('fill="url(#g)"')->toContain('transform="translate(1, 2)"')
        ->toContain('style="stroke:red;opacity:0.5"')->not->toContain('evil.example')
        ->not->toContain('<script')->not->toContain('onload')->not->toContain('foreignObject')->not->toContain('<use');
});

test('gateway SVG rejects entities and non SVG documents', function (string $bytes): void {
    expect(fn () => FOSSBilling\Sanitizer\GatewaySvgSanitizer::sanitize($bytes))->toThrow(FOSSBilling\Exception::class);
})->with([
    '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>',
    '<html><body>not svg</body></html>',
    '<svg xmlns="http://www.w3.org/1999/xhtml"/>',
]);

test('raster normalization strips trailing data and preserves dimensions and transparency', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgQAcAABIAAW/6Y7cAAAAASUVORK5CYII=');
    $bytes = $png . 'private-trailing-data';
    $filesystem = Mockery::mock(Filesystem::class);
    $stored = captureGatewayLogo($filesystem);
    $service = gatewayLogoService(new MockHttpClient(new MockResponse($bytes)), $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);
    $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']);

    if (!function_exists('imagecreatefromstring')) {
        expect($stored->bytes)->toBe($bytes);

        return;
    }
    expect($stored->bytes)->not->toContain('private-trailing-data');
    $image = imagecreatefromstring($stored->bytes);
    expect(imagesx($image))->toBe(2)->and(imagesy($image))->toBe(2)
        ->and(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBe(127);
});

test('raster normalization rejects a PNG with valid headers but corrupt image data', function (): void {
    if (!function_exists('imagecreatefromstring')) {
        $this->markTestSkipped('GD decoding is unavailable.');
    }
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService(new MockHttpClient(new MockResponse($bytes)), $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);

    expect(fn () => $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']))
        ->toThrow(FOSSBilling\Exception::class, 'Could not decode the payment gateway image.');
});

test('raster images store PNG and at most one AVIF generated from the same decoded image', function (): void {
    $bytes = gatewayLogoPng();
    $filesystem = Mockery::mock(Filesystem::class);
    $stored = captureGatewayLogo($filesystem);
    $service = gatewayLogoService(new MockHttpClient(new MockResponse($bytes)), $filesystem);
    $gateway = createEntity(PayGateway::class, ['enabled' => false]);
    $service->update($gateway, ['logo_url' => 'https://8.8.8.8/logo.png']);

    $hasAvif = function_exists('imagecreatefromstring') && function_exists('imageavif') && (imagetypes() & IMG_AVIF);
    expect($stored->files)->toHaveCount($hasAvif ? 2 : 1);
    expect(getimagesizefromstring($stored->bytes)['mime'])->toBe('image/png');
    if ($hasAvif) {
        $avif = $stored->files[substr($gateway->getCustomLogo(), 0, -4) . '.avif'];
        $size = getimagesizefromstring($avif);
        expect($size['mime'])->toBe('image/avif')->and($size[0])->toBe(2)->and($size[1])->toBe(2);
    }
});

test('active gateways offer AVIF only when its stored companion exists', function (bool $hasAvif): void {
    $filename = str_repeat('a', 64) . '.png';
    $gateway = createEntity(PayGateway::class, ['gateway' => 'Custom', 'customLogo' => $filename, 'acceptedCurrencies' => '["USD"]']);
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->andReturnUsing(static fn (string $path): bool => str_ends_with($path, '/custom/' . $filename) || ($hasAvif && str_ends_with($path, '/custom/' . str_repeat('a', 64) . '.avif')));
    $service = gatewayLogoService(new MockHttpClient(), $filesystem);
    $service->getPayGatewayRepository()->shouldReceive('findEnabledOrderedByIdDesc')->andReturn([$gateway]);

    $logo = $service->getActive([])[0]['logo'];
    expect($logo['logo'])->toBe(SYSTEM_URL . 'public/gateways/custom/' . $filename);
    expect($logo['avif'])->toBe($hasAvif ? SYSTEM_URL . 'public/gateways/custom/' . str_repeat('a', 64) . '.avif' : null);
})->with([true, false]);

test('payment selectors render AVIF with one PNG fallback and support images without variants', function (string $template, bool $hasAvif): void {
    $png = 'https://billing.example/logo.png';
    $avif = 'https://billing.example/logo.avif';
    $gateway = [
        'id' => 1, 'title' => 'Cards', 'accepted_currencies' => ['USD'],
        'allow_single' => true, 'allow_recurrent' => false,
        'logo' => ['logo' => $png, 'height' => '50px', 'width' => '150px'],
    ];
    if ($hasAvif) {
        $gateway['logo']['avif'] = $avif;
    }
    $guest = new Tests\Support\PermissiveStub([
        'invoice_gateways' => [$gateway],
        'cart_get' => new Tests\Support\PermissiveStub([
            'items' => [new Tests\Support\PermissiveStub(['id' => 1, 'title' => 'Service', 'quantity' => 1, 'total' => 10, 'discount_price' => 0, 'setup_price' => 0, 'discount_setup' => 0, 'form_id' => false, 'period' => false])],
            'currency' => ['code' => 'USD', 'conversion_rate' => 1], 'total' => 10, 'discount' => 0, 'subtotal' => 10,
        ]),
    ]);
    $html = (new Tests\Support\StrictTemplateRenderer())->renderTemplate(PATH_MODS . '/' . $template, [
        'app_area' => 'client', 'client' => null, 'guest' => $guest,
        'invoice' => new Tests\Support\PermissiveStub([
            'status' => 'unpaid', 'currency' => 'USD', 'items' => [],
            'credit_note_for_invoice_id' => false, 'debit_note_for_invoice_id' => false,
            'refunded_by_invoice_ids' => [], 'debited_by_invoice_ids' => [],
            'paid_at' => '', 'created_at' => '2026-10-12', 'tax' => 0, 'subtotal' => 10, 'total' => 10,
            'buyer' => new Tests\Support\PermissiveStub(['country' => 'US']),
        ]),
        'request' => new Tests\Support\PermissiveStub(['checkout' => true, 'show_custom_form_values' => false]),
    ]);
    expect($html)->toContain('<picture')->toContain('src="' . $png . '"');
    if ($hasAvif) {
        expect($html)->toContain('srcset="' . $avif . '" type="image/avif"');
    } else {
        expect($html)->not->toContain('type="image/avif"');
    }
})->with([
    ['Invoice/templates/client/mod_invoice_invoice.html.twig', true],
    ['Invoice/templates/client/mod_invoice_invoice.html.twig', false],
    ['Orderbutton/templates/client/mod_orderbutton_checkout.html.twig', true],
    ['Orderbutton/templates/client/mod_orderbutton_checkout.html.twig', false],
]);

test('gateway images above four million pixels are rejected before decoding or changing settings', function (): void {
    $bytes = gatewayLogoPng();
    // Change the PNG header dimensions without allocating a large decoded image.
    $header = substr($bytes, 12, 4) . pack('NN', 2000, 2001) . substr($bytes, 24, 5);
    $bytes = substr($bytes, 0, 12) . $header . hash('crc32b', $header, true) . substr($bytes, 33);
    expect(getimagesizefromstring($bytes)[0])->toBe(2000);
    expect(getimagesizefromstring($bytes)[1])->toBe(2001);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('dumpFile')->never();
    $service = gatewayLogoService(new MockHttpClient(new MockResponse($bytes)), $filesystem);
    $service->getDi()['em']->shouldReceive('flush')->never();
    $gateway = createEntity(PayGateway::class, ['enabled' => false, 'name' => 'Original', 'customLogo' => 'original.png']);

    expect(fn () => $service->update($gateway, ['title' => 'Changed', 'logo_url' => 'https://8.8.8.8/logo.png']))
        ->toThrow(FOSSBilling\Exception::class, 'no more than 4 million pixels');
    expect($gateway->getName())->toBe('Original')->and($gateway->getCustomLogo())->toBe('original.png');
});

<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

function invoiceReviewTemplate(string $fragment): Environment
{
    $twig = new Environment(new ArrayLoader(['fragment' => $fragment]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $parameters = []): string => strtr($text, $parameters)));
    $twig->addFilter(new TwigFilter('format_currency', static fn (float|int|string $amount, string $currency): string => $currency . ' ' . number_format((float) $amount, 2)));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, ?array $query = null, ?string $role = null): string => '/api/' . $path));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, ?array $query = null): string => '/' . $path));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $options): string => ''));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $options): string => isset($options['href']) ? 'href="' . htmlspecialchars($options['href']) . '"' : '', ['is_safe' => ['html']]));

    return $twig;
}

test('invoice credit removal renders exactly one working API href', function (): void {
    $source = (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/admin/mod_invoice_invoice.html.twig');
    preg_match('/<a[^\n]+Remove credit[^\n]+<\/a>/', $source, $match);
    $html = invoiceReviewTemplate($match[0])->render('fragment', ['invoice' => ['id' => 42]]);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    expect($dom->getElementsByTagName('a')->item(0)->getAttribute('href'))->toBe('/api/invoice/remove_credit')
        ->and(substr_count($html, 'href='))->toBe(1);
});

test('invoice credit form is visible only to its logged-in owner', function (bool $loggedIn, int $owner): void {
    $source = (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_invoice.html.twig');
    $start = strpos($source, '{% set free_credit');
    $end = strpos($source, '{% endif %}', $start) + strlen('{% endif %}');
    $client = new class {
        public int $balanceReads = 0;
        public array $profile_get = ['id' => 1];

        public function balance_get_total(): float
        {
            ++$this->balanceReads;

            return 50.0;
        }
    };
    $html = invoiceReviewTemplate(substr($source, $start, $end - $start))->render('fragment', [
        'client' => $loggedIn ? $client : null,
        'invoice' => ['client' => ['id' => $owner], 'hash' => 'example', 'balance_due' => 20.0, 'currency' => 'USD', 'lines' => [['type' => 'order']]],
    ]);
    expect(str_contains($html, '/api/invoice/apply_credit'))->toBe($loggedIn && $owner === 1)
        ->and($client->balanceReads)->toBe($loggedIn && $owner === 1 ? 1 : 0);
})->with([[true, 1], [true, 2], [false, 1]]);

test('failed renewal actions offer registrar review only for zero-priced domains', function (string $productType, float $price): void {
    $source = (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/admin/mod_invoice_failed_items.html.twig');
    $start = strpos($source, '{% if item.renewal_outcome');
    $end = strpos($source, '{% endif %}', $start) + strlen('{% endif %}');
    $html = invoiceReviewTemplate(substr($source, $start, $end - $start))->render('fragment', [
        'item' => ['id' => 1, 'type' => 'order', 'task' => 'renew', 'price' => $price, 'product_type' => $productType],
    ]);
    expect(str_contains($html, 'Retry free renewal'))->toBe($productType === 'domain' && $price === 0.0);
})->with([['domain', 0.0], ['hosting', 0.0], ['domain', 10.0]]);

test('gateway fee badges use supplied quotes and quote failure keeps invoice rendering available', function (bool $available): void {
    $source = (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_invoice.html.twig');
    $start = strpos($source, '{% if gateway_fees is not null %}');
    $end = strpos($source, '</form>', $start) + strlen('</form>');
    $end = strpos($source, '{% set free_credit', $end);
    $guest = new class {
        public array $invoice_gateways = [['id' => 1, 'accepted_currencies' => ['EUR'], 'allow_single' => true, 'allow_recurrent' => false, 'title' => 'Gateway', 'logo' => ['height' => '10px', 'width' => '10px', 'logo' => '/logo']]];

        public function invoice_gateway_fees(array $data): array
        {
            throw new RuntimeException('Public fee quotes are unavailable');
        }
    };
    $html = invoiceReviewTemplate(substr($source, $start, $end - $start) . '<footer>Invoice details remain available</footer>')->render('fragment', [
        'client' => null, 'guest' => $guest, 'invoice' => ['hash' => 'example', 'currency' => 'EUR', 'subscribable' => false],
        'gateway_fees' => $available ? [1 => ['fee' => 2.5]] : null,
    ]);
    expect($html)->toContain('Invoice details remain available')
        ->and(str_contains($html, 'EUR 2.50'))->toBe($available)
        ->and(str_contains($html, '<form'))->toBe($available)
        ->and(str_contains($html, 'temporarily unavailable'))->toBe(!$available)
        ->and($source)->not->toContain('invoice_gateway_fees(', 'fetch(');
})->with([true, false]);

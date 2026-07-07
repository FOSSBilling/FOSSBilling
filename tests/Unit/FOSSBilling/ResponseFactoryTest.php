<?php

declare(strict_types=1);

use FOSSBilling\Http\ResponseFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

test('response factory returns existing responses unchanged', function (): void {
    $response = new Response('ready');

    expect((new ResponseFactory())->normalize($response))->toBe($response);
});

test('response factory normalizes scalar and null controller results', function (): void {
    $factory = new ResponseFactory();

    expect($factory->normalize('body')->getContent())->toBe('body')
        ->and($factory->normalize(null)->getContent())->toBe('');
});

test('response factory creates HTML responses with status and headers', function (): void {
    $response = (new ResponseFactory())->html('body', 201, ['X-Test' => 'yes']);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getContent())->toBe('body')
        ->and($response->headers->get('X-Test'))->toBe('yes');
});

test('response factory creates error responses using exception codes or internal server error fallback', function (): void {
    $factory = new ResponseFactory();

    expect($factory->error('not found', new RuntimeException('Missing', 404))->getStatusCode())->toBe(404)
        ->and($factory->error('error', new RuntimeException('Broken'))->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($factory->error('legacy', new RuntimeException('Legacy code', 981))->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR);
});

test('response factory creates redirect responses', function (): void {
    $response = (new ResponseFactory())->redirect('/target', Response::HTTP_MOVED_PERMANENTLY);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_MOVED_PERMANENTLY)
        ->and($response->headers->get('Location'))->toBe('/target');
});

test('response factory creates binary file and download responses', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'fossbilling-response-factory-');
    file_put_contents($file, 'content');

    try {
        $factory = new ResponseFactory();
        $fileResponse = $factory->file('report.txt', 'text/plain', $file);
        $downloadResponse = $factory->download('archive.zip', $file);

        expect($fileResponse)->toBeInstanceOf(BinaryFileResponse::class)
            ->and($fileResponse->headers->get('Content-Type'))->toBe('text/plain')
            ->and($fileResponse->headers->get('Content-Disposition'))->toStartWith('inline;')
            ->and($fileResponse->headers->get('Content-Disposition'))->toContain('report.txt')
            ->and($downloadResponse->headers->get('Content-Type'))->toBe('application/octet-stream')
            ->and($downloadResponse->headers->get('Content-Description'))->toBe('File Transfer')
            ->and($downloadResponse->headers->get('Content-Disposition'))->toStartWith('attachment;')
            ->and($downloadResponse->headers->get('Content-Disposition'))->toContain('archive.zip');
    } finally {
        if (is_string($file) && file_exists($file)) {
            unlink($file);
        }
    }
});

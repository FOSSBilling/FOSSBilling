<?php

declare(strict_types=1);

use FOSSBilling\Http\ResponseEmitter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

test('emitter prepares the response for the request before sending it', function (): void {
    $request = Request::create('https://billing.example.com/download', 'HEAD');
    $response = new class('file contents') extends Response {
        public bool $sent = false;

        public function send(bool $flush = true): static
        {
            $this->sent = true;

            return $this;
        }
    };

    (new ResponseEmitter())->emit($response, $request);

    expect($response->sent)->toBeTrue()
        ->and($response->getContent())->toBe('');
});

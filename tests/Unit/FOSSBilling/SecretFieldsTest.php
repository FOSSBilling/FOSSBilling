<?php

declare(strict_types=1);

use FOSSBilling\Crypt;
use FOSSBilling\SecretFields;

test('credential envelopes encrypt only declared fields, preserve flags and fail closed on tampering', function (): void {
    $codec = new SecretFields(new Crypt());
    $source = ['password' => ' secret ', 'region' => 'eu', 'enabled' => true, 'port' => 443];
    $stored = $codec->store($source, ['password']);
    expect($stored['password'])->toBeArray()->and($stored['region'])->toBe('eu')
        ->and($stored['enabled'])->toBeTrue()->and($stored['port'])->toBe(443)
        ->and($codec->reveal($stored))->toBe($source)
        ->and($codec->store($stored, ['password']))->toBe($stored)
        ->and($codec->names($stored))->toBe(['password']);
    $stored['password']['_fossbilling_encrypted'] = 'v3:invalid';
    expect(fn () => $codec->reveal($stored))->toThrow(FOSSBilling\InformationException::class);
});

test('storing or revealing malformed credential envelopes fails closed', function (string $failure): void {
    $crypt = new Crypt();
    $codec = new SecretFields($crypt);
    $ciphertext = $crypt->encrypt('secret');
    $envelope = ['_fossbilling_encrypted' => $ciphertext];
    switch ($failure) {
        case 'null':
            $envelope['_fossbilling_encrypted'] = null;

            break;
        case 'non-string':
            $envelope['_fossbilling_encrypted'] = 42;

            break;
        case 'version':
            $envelope['_fossbilling_encrypted'] = 'unknown:' . $ciphertext;

            break;
        case 'extra-key':
            $envelope['extra'] = 'unexpected';

            break;
        case 'authentication':
            $raw = base64_decode(substr($ciphertext, strlen(Crypt::CURRENT_FORMAT_PREFIX)), true);
            $raw[12] = chr(ord($raw[12]) ^ 1);
            $envelope['_fossbilling_encrypted'] = Crypt::CURRENT_FORMAT_PREFIX . base64_encode($raw);

            break;
    }
    $config = ['password' => $envelope];
    // Tagged credentials remain protected even if an adapter no longer declares the field.
    expect(fn () => $codec->store($config, []))->toThrow(FOSSBilling\InformationException::class)
        ->and(fn () => $codec->reveal($config))->toThrow(FOSSBilling\InformationException::class);
})->with(['null', 'non-string', 'version', 'extra-key', 'authentication']);

<?php

declare(strict_types=1);

use FOSSBilling\Core\Security\Crypt;
use FOSSBilling\Core\Security\SecretFields;

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
    expect(fn () => $codec->reveal($stored))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
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
    expect(fn () => $codec->store($config, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class)
        ->and(fn () => $codec->reveal($config))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
})->with(['null', 'non-string', 'version', 'extra-key', 'authentication']);

test('existing bare adapter ciphertext survives the historical migration and upgrades to envelopes', function (string $format): void {
    $crypt = new Crypt();
    $secret = 'existing-adapter-secret';
    $salt = FOSSBilling\Core\System\Config::getProperty('info.salt');
    $iv = str_repeat('x', 16);
    $ciphertext = $format === 'v2'
        ? 'v2:' . base64_encode($iv . openssl_encrypt($secret, Crypt::LEGACY_METHOD, hash_pbkdf2('sha256', $salt, 'fossbilling_salt', 100000, 32, true), OPENSSL_RAW_DATA, $iv))
        : $crypt->encrypt($secret);
    $stored = ['password' => $ciphertext, 'region' => 'eu', 'label' => 'v2:ordinary-text'];
    $historical = FOSSBilling\Core\SecretConfig::encryptSecretFields($stored, ['password'], $crypt);
    expect($historical)->toBe($stored)
        ->and(FOSSBilling\Core\SecretConfig::decryptMarkedValues($historical, $crypt)['password'])->toBe($secret);
    $codec = new SecretFields($crypt);
    // Retain protection even if the adapter no longer declares this field.
    $upgraded = $codec->store($historical, []);
    expect($upgraded['password'])->toBeArray()
        ->and($codec->reveal($upgraded))->toBe(['password' => $secret, 'region' => 'eu', 'label' => 'v2:ordinary-text'])
        ->and($codec->store($upgraded, []))->toBe($upgraded)
        ->and($codec->names($historical))->toBe(['password']);
})->with(['v2', 'v3']);

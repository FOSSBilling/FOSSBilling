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

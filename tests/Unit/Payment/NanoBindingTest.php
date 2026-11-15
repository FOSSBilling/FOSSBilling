<?php

declare(strict_types=1);

/*
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * Unit tests for the dependency-free pieces of the Nano (XNO) payment gateway:
 * address codec, raw-amount derivation, xno-binding v1 option C commitment,
 * the block verifier and the restart-proof claims ledger. None of these touch a
 * database, a container or the network, so they run on their own.
 */

// The official xno-binding v1 option C vector (docs/BINDING-STANDARD.md).
const NANO_BURN = 'nano_1111111111111111111111111111111111111111111111111111hifc8npp';
const NANO_PRICE_RAW = '50000000000000000000000000000'; // 0.05 XNO
const NANO_ORDER = 'order-0001';

function nanoConfirmedSend(array $overrides = []): array
{
    $base = [
        'block_account' => 'nano_3t6k35gi95xu6tergt6p69ck76ogmitsa8mnijtpxm9fkcm736xtoncuohr3',
        'amount' => NANO_PRICE_RAW,
        'confirmed' => 'true',
        'subtype' => 'send',
        'local_timestamp' => '1763200000',
        'contents' => [
            'link_as_account' => NANO_BURN,
            'representative' => 'nano_33q9dassq66es6mcay83n5kypqgpzjikr9bkc9ope9xch1borfphgehj4ozj',
        ],
    ];
    $contents = array_merge($base['contents'], $overrides['contents'] ?? []);
    unset($overrides['contents']);

    return array_merge($base, $overrides, ['contents' => $contents]);
}

test('a valid Nano address round-trips through its public key', function (): void {
    expect(Payment_Nano_Address::isValid(NANO_BURN))->toBeTrue();
    expect(Payment_Nano_Address::publicKey(NANO_BURN))->toBe(str_repeat('0', 64));
    expect(Payment_Nano_Address::fromPublicKey(str_repeat('0', 64)))->toBe(NANO_BURN);
});

test('a one-character checksum error is rejected', function (): void {
    expect(Payment_Nano_Address::isValid('nano_1111111111111111111111111111111111111111111111111111hifc8npq'))->toBeFalse();
});

test('xrb_ and nano_ forms of one account match', function (): void {
    expect(Payment_Nano_Address::matches(NANO_BURN, 'xrb_1111111111111111111111111111111111111111111111111111hifc8npp'))->toBeTrue();
});

test('raw is derived from XNO, never finer than one raw', function (): void {
    expect(Payment_Nano_Amount::xnoToRaw('0.05'))->toBe(NANO_PRICE_RAW);
    expect(Payment_Nano_Amount::xnoToRaw('1'))->toBe('1000000000000000000000000000000');
    expect(fn () => Payment_Nano_Amount::xnoToRaw('0.' . str_repeat('0', 30) . '1'))->toThrow(InvalidArgumentException::class);
});

test('a price is derived from the invoice total with no float rounding', function (): void {
    // 12.50 units at 3.2 XNO per unit = 40 XNO.
    expect(Payment_Nano_Amount::multiplyDecimals('12.50', '3.2'))->toBe('40');
    expect(Payment_Nano_Amount::xnoToRaw('40'))->toBe('40000000000000000000000000000000');
});

test('raw amounts compare as integers, exact and at-least', function (): void {
    expect(Payment_Nano_Amount::equals('050', '50'))->toBeTrue();
    expect(Payment_Nano_Amount::atLeast(NANO_PRICE_RAW, NANO_PRICE_RAW))->toBeTrue();
    expect(Payment_Nano_Amount::atLeast('49999999999999999999999999999', NANO_PRICE_RAW))->toBeFalse();
    expect(Payment_Nano_Amount::isRaw('1.5'))->toBeFalse();
});

test('option C commitment reproduces the published vector', function (): void {
    expect(Payment_Nano_Binding::commitmentHex(NANO_ORDER, NANO_BURN, NANO_PRICE_RAW))
        ->toBe('86e75a339b908cc926a478c1a0e5eb5dd6fc612c1d3251eb661faa78135c36cf');
    expect(Payment_Nano_Binding::representativeAccount(NANO_ORDER, NANO_BURN, NANO_PRICE_RAW))
        ->toBe('nano_33q9dassq66es6mcay83n5kypqgpzjikr9bkc9ope9xch1borfphgehj4ozj');
});

test('a different order gives a different representative', function (): void {
    expect(Payment_Nano_Binding::representativeAccount('order-0002', NANO_BURN, NANO_PRICE_RAW))
        ->not->toBe(Payment_Nano_Binding::representativeAccount(NANO_ORDER, NANO_BURN, NANO_PRICE_RAW));
});

$rep = Payment_Nano_Binding::representativeAccount(NANO_ORDER, NANO_BURN, NANO_PRICE_RAW);

test('the verifier accepts a confirmed, bound, exact send', function () use ($rep): void {
    $result = Payment_Nano_Verifier::verify(nanoConfirmedSend(), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763200100);
    expect($result['ok'])->toBeTrue();
    expect($result['amount'])->toBe(NANO_PRICE_RAW);
    expect($result['payer'])->toBe('nano_3t6k35gi95xu6tergt6p69ck76ogmitsa8mnijtpxm9fkcm736xtoncuohr3');
});

test('the verifier refuses an unconfirmed send', function () use ($rep): void {
    $result = Payment_Nano_Verifier::verify(nanoConfirmedSend(['confirmed' => 'false']), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763200100);
    expect($result['ok'])->toBeFalse();
});

test('the verifier refuses a send to another account', function () use ($rep): void {
    $other = ['contents' => ['link_as_account' => 'nano_3t6k35gi95xu6tergt6p69ck76ogmitsa8mnijtpxm9fkcm736xtoncuohr3']];
    $result = Payment_Nano_Verifier::verify(nanoConfirmedSend($other), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763200100);
    expect($result['ok'])->toBeFalse();
});

test('the verifier refuses the wrong amount, short or over', function () use ($rep): void {
    $short = Payment_Nano_Verifier::verify(nanoConfirmedSend(['amount' => '49999999999999999999999999999']), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763200100);
    $over = Payment_Nano_Verifier::verify(nanoConfirmedSend(['amount' => '50000000000000000000000000001']), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763200100);
    expect($short['ok'])->toBeFalse();
    expect($over['ok'])->toBeFalse();
});

test('the verifier refuses a send not bound to this invoice', function () use ($rep): void {
    $wrongRep = ['contents' => ['representative' => 'nano_1111111111111111111111111111111111111111111111111111hifc8npp']];
    $result = Payment_Nano_Verifier::verify(nanoConfirmedSend($wrongRep), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763200100);
    expect($result['ok'])->toBeFalse();
});

test('the verifier refuses a send older than the replay window', function () use ($rep): void {
    $result = Payment_Nano_Verifier::verify(nanoConfirmedSend(['local_timestamp' => '1763200000']), NANO_BURN, NANO_PRICE_RAW, $rep, 3600, 1763299999);
    expect($result['ok'])->toBeFalse();
});

test('a claim credits one block to one invoice once, idempotently', function (): void {
    $path = sys_get_temp_dir() . '/nano-claims-' . bin2hex(random_bytes(6)) . '.log';
    $hash = str_repeat('A', 64);
    try {
        $ledger = new Payment_Nano_ClaimsFile($path);
        expect($ledger->claim($hash, 'inv-hash-1'))->toBeTrue();
        // A retried callback for the same block and invoice does not credit again.
        expect($ledger->claim($hash, 'inv-hash-1'))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

test('a claimed block cannot be replayed for another invoice', function (): void {
    $path = sys_get_temp_dir() . '/nano-claims-' . bin2hex(random_bytes(6)) . '.log';
    $hash = str_repeat('B', 64);
    try {
        $ledger = new Payment_Nano_ClaimsFile($path);
        $ledger->claim($hash, 'inv-hash-1');
        expect(fn () => $ledger->claim($hash, 'inv-hash-2'))->toThrow(Payment_Exception::class);
    } finally {
        @unlink($path);
    }
});

test('an invoice cannot be paid twice by two different blocks', function (): void {
    $path = sys_get_temp_dir() . '/nano-claims-' . bin2hex(random_bytes(6)) . '.log';
    try {
        $ledger = new Payment_Nano_ClaimsFile($path);
        $ledger->claim(str_repeat('C', 64), 'inv-hash-1');
        expect(fn () => $ledger->claim(str_repeat('D', 64), 'inv-hash-1'))->toThrow(Payment_Exception::class);
    } finally {
        @unlink($path);
    }
});

test('a claim survives a restart (a fresh ledger still refuses the block)', function (): void {
    $path = sys_get_temp_dir() . '/nano-claims-' . bin2hex(random_bytes(6)) . '.log';
    $hash = str_repeat('E', 64);
    try {
        (new Payment_Nano_ClaimsFile($path))->claim($hash, 'inv-hash-1');
        // A new process would construct a new ledger; it reloads from disk.
        $reloaded = new Payment_Nano_ClaimsFile($path);
        expect($reloaded->isClaimed($hash))->toBeTrue();
        expect($reloaded->claim($hash, 'inv-hash-1'))->toBeFalse();
        expect(fn () => $reloaded->claim($hash, 'inv-hash-2'))->toThrow(Payment_Exception::class);
    } finally {
        @unlink($path);
    }
});

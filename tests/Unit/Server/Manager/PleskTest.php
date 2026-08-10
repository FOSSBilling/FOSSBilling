<?php

declare(strict_types=1);

function invokePleskCreateSubscriptionProps(Server_Manager_Plesk $manager, Server_Account $account, string $action): array
{
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('createSubscriptionProps');

    return $method->invokeArgs($manager, [$account, $action]);
}

beforeEach(function (): void {
    $this->manager = new Server_Manager_Plesk([
        'host' => 'plesk.example.com',
        'username' => 'admin',
        'password' => 'secret',
    ]);

    $this->account = (new Server_Account())
        ->setUsername('example')
        ->setDomain('example.com')
        ->setIp('192.0.2.10')
        ->setPassword('secret')
        ->setClient(new Server_Client())
        ->setPackage((new Server_Package())->setName('Business Hosting'));
});

test('createSubscriptionProps sends the settings directly under <add>, in schema order', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');

    expect($props)->toHaveKey('add')
        ->and(array_keys($props['add']))->toBe(['gen_setup', 'hosting', 'limits', 'permissions'])
        ->and($props['add'])->not->toHaveKey('filter')
        ->and($props['add'])->not->toHaveKey('values');
});

test('createSubscriptionProps includes htype in gen_setup for the add action', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');

    expect($props['add']['gen_setup'])->toHaveKey('htype')
        ->and($props['add']['gen_setup']['htype'])->toBe('vrt_hst');
});

test('createSubscriptionProps wraps the set action in filter and values, as Plesk requires', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');

    expect($props)->toHaveKey('set')
        ->and(array_keys($props['set']))->toBe(['filter', 'values'])
        ->and(array_keys($props['set']['values']))->toBe(['gen_setup', 'hosting', 'limits', 'permissions']);
});

test('createSubscriptionProps filters the set action by domain name, not owner-login', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');

    // 'owner-login' would match every webspace this customer owns, applying $values to all of
    // them; 'name' (the domain) scopes the update to this one subscription.
    expect($props['set']['filter'])->toBe(['name' => 'example.com']);
});

test('createSubscriptionProps omits htype from gen_setup for the set action', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');

    expect($props['set']['values']['gen_setup'])->not->toHaveKey('htype');
});

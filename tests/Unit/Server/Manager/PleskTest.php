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
        ->and(array_keys($props['add']))->toBe(['gen_setup', 'hosting', 'limits', 'permissions', 'plan-name'])
        ->and($props['add'])->not->toHaveKey('filter')
        ->and($props['add'])->not->toHaveKey('values');
});

test('createSubscriptionProps includes htype in gen_setup for the add action', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');

    expect(array_keys($props['add']['gen_setup']))->toBe(['name', 'owner-login', 'htype', 'ip_address'])
        ->and($props['add']['gen_setup']['htype'])->toBe('vrt_hst');
});

test('createSubscriptionProps wraps the set action in filter and values, as Plesk requires', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');

    expect($props)->toHaveKey('set')
        ->and(array_keys($props['set']))->toBe(['filter', 'values'])
        ->and(array_keys($props['set']['values']))->toBe(['gen_setup', 'hosting', 'limits', 'permissions', 'plan-name']);
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

test('createSubscriptionProps uses custom package limits and permissions', function (): void {
    $this->account->getPackage()->setCustomValues([
        'aftp' => 'true',
        'cron' => '1',
        'nemailml' => '25',
        'spam' => 'yes',
        'ssh' => 'on',
    ]);

    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');
    $limits = array_column($props['add']['limits']['limit'], 'value', 'name');
    $permissions = array_column($props['add']['permissions']['permission'], 'value', 'name');

    expect($limits['max_maillists'])->toBe('25')
        ->and($permissions['manage_crontab'])->toBe('true')
        ->and($permissions['manage_anonftp'])->toBe('true')
        ->and($permissions['manage_maillists'])->toBe('true')
        ->and($permissions['manage_not_chroot_shell'])->toBe('true')
        ->and($permissions['manage_spamfilter'])->toBe('true');
});

test('createSubscriptionProps disables mailing lists when the custom limit is zero', function (): void {
    $this->account->getPackage()->setCustomValue('nemailml', '0');

    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');
    $limits = array_column($props['add']['limits']['limit'], 'value', 'name');
    $permissions = array_column($props['add']['permissions']['permission'], 'value', 'name');

    expect($limits['max_maillists'])->toBe(0)
        ->and($permissions['manage_maillists'])->toBe('false');
});

test('createSubscriptionProps assigns the hosting plan name to the Plesk subscription', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');

    expect($props['add']['plan-name'])->toBe('Business Hosting');
});

test('createSubscriptionProps carries the plan name through on updates too', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');

    expect($props['set']['values']['plan-name'])->toBe('Business Hosting');
});

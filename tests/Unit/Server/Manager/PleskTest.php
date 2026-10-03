<?php

declare(strict_types=1);

use PleskX\Api\Client;
use PleskX\Api\Operator\Session;
use Symfony\Component\HttpFoundation\Request;

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

test('createSubscriptionProps only sends the FTP password when creating a subscription', function (): void {
    $addProps = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');
    $setProps = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');
    $addHostingProperties = array_column($addProps['add']['hosting']['vrt_hst']['property'], 'value', 'name');
    $setHostingProperties = array_column($setProps['set']['values']['hosting']['vrt_hst']['property'], 'value', 'name');

    expect($addHostingProperties['ftp_password'])->toBe('secret')
        ->and($setHostingProperties)->not->toHaveKey('ftp_password');
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

test('createSubscriptionProps assigns the hosting plan name to the Plesk subscription on creation', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'add');

    expect($props['add']['plan-name'])->toBe('Business Hosting');
});

test('createSubscriptionProps does not send plan-name on updates, since webspace/set has no plan node', function (): void {
    $props = invokePleskCreateSubscriptionProps($this->manager, $this->account, 'set');

    expect($props['set']['values'])->not->toHaveKey('plan-name');
});

test('getLoginUrl passes the trusted client IP to Plesk session creation', function (): void {
    $originalServer = $_SERVER;
    $originalTrustedProxies = Request::getTrustedProxies();
    $originalTrustedHeaderSet = Request::getTrustedHeaderSet();

    try {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8';
        Request::setTrustedProxies(['10.0.0.2'], Request::HEADER_X_FORWARDED_FOR);

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('create')
            ->once()
            ->with('example', '8.8.8.8')
            ->andReturn('session-id');
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('session')->once()->andReturn($session);

        $clientProperty = new ReflectionProperty($this->manager, '_client');
        $clientProperty->setValue($this->manager, $client);

        expect($this->manager->getLoginUrl($this->account))
            ->toBe('http://plesk.example.com:8443/enterprise/rsession_init.php?PHPSESSID=session-id');
    } finally {
        $_SERVER = $originalServer;
        Request::setTrustedProxies($originalTrustedProxies, $originalTrustedHeaderSet);
    }
});

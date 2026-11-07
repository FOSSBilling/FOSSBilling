<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Service;
use Pimple\Container;

/**
 * Minimal entity manager stand-in; syncWhois() only calls flush().
 */
function makeEntityManagerSpy(): object
{
    return new class {
        public int $flushCount = 0;

        public function flush(): void
        {
            ++$this->flushCount;
        }
    };
}

/**
 * A fully populated registrar contact, as syncWhois() copies every field.
 */
function makeWhoisContact(): Registrar_Domain_Contact
{
    $contact = new Registrar_Domain_Contact();
    $contact->setFirstName('Jane');
    $contact->setLastName('Doe');
    $contact->setEmail('jane@example.com');
    $contact->setCompany('Example Inc');
    $contact->setAddress1('1 Main St');
    $contact->setAddress2('Suite 2');
    $contact->setCountry('US');
    $contact->setCity('Springfield');
    $contact->setState('IL');
    $contact->setZip('62701');
    $contact->setTelCc('1');
    $contact->setTel('5551234');

    return $contact;
}

/**
 * Build a Servicedomain service whose registrar adapter returns $whois
 * from getDomainDetails(), bypassing the real registrar stack.
 */
function makeSyncWhoisService(Registrar_Domain $whois, object $em): Service
{
    $service = new class($whois) extends Service {
        public function __construct(private readonly Registrar_Domain $whois)
        {
        }

        protected function _getD(ServiceDomain $model): array
        {
            $whois = $this->whois;
            $adapter = new readonly class($whois) {
                public function __construct(private Registrar_Domain $whois)
                {
                }

                public function getDomainDetails(Registrar_Domain $domain): Registrar_Domain
                {
                    return $this->whois;
                }
            };

            return [new Registrar_Domain(), $adapter];
        }
    };

    $di = new Container();
    $di['em'] = $em;
    $service->setDi($di);

    return $service;
}

test('syncWhois does not clear registered_at when the registrar returns no registration timestamp', function (): void {
    $em = makeEntityManagerSpy();

    $whois = new Registrar_Domain();
    $whois->setContactRegistrar(makeWhoisContact());
    // No registration time set: many registrars omit it.

    $service = makeSyncWhoisService($whois, $em);

    $model = new ServiceDomain();
    $stored = new DateTime('2020-05-01 12:00:00');
    $model->setRegisteredAt($stored);

    $service->syncWhois($model, new Box\Mod\Order\Entity\Order());

    expect($model->getRegisteredAt()?->format('Y-m-d H:i:s'))->toBe('2020-05-01 12:00:00');
    expect($em->flushCount)->toBe(1);
});

test('syncWhois updates registered_at when the registrar returns a registration timestamp', function (): void {
    $em = makeEntityManagerSpy();

    $whois = new Registrar_Domain();
    $whois->setContactRegistrar(makeWhoisContact());
    $whois->setRegistrationTime(1_551_676_767); // 2019-03-04 05:19:27 UTC

    $service = makeSyncWhoisService($whois, $em);

    $model = new ServiceDomain();
    $model->setRegisteredAt(new DateTime('2020-05-01 12:00:00'));

    $service->syncWhois($model, new Box\Mod\Order\Entity\Order());

    expect($model->getRegisteredAt()?->format('Y-m-d H:i:s'))->toBe('2019-03-04 05:19:27');
});

test('syncWhois persists nameservers reported by the registrar', function (): void {
    $em = makeEntityManagerSpy();

    $whois = new Registrar_Domain();
    $whois->setContactRegistrar(makeWhoisContact());
    $whois->setNs1('ns1.registrar.test');
    $whois->setNs2('ns2.registrar.test');
    $whois->setNs3('ns3.registrar.test');
    $whois->setNs4('ns4.registrar.test');

    $service = makeSyncWhoisService($whois, $em);

    $model = new ServiceDomain();

    $service->syncWhois($model, new Box\Mod\Order\Entity\Order());

    expect($model->getNs1())->toBe('ns1.registrar.test');
    expect($model->getNs2())->toBe('ns2.registrar.test');
    expect($model->getNs3())->toBe('ns3.registrar.test');
    expect($model->getNs4())->toBe('ns4.registrar.test');
});

test('syncWhois keeps stored nameservers the registrar does not report', function (): void {
    $em = makeEntityManagerSpy();

    $whois = new Registrar_Domain();
    $whois->setContactRegistrar(makeWhoisContact());
    $whois->setNs1('ns1.registrar.test');
    // ns2-ns4 not reported by the registrar.

    $service = makeSyncWhoisService($whois, $em);

    $model = new ServiceDomain();
    $model->setNs1('ns1.old.test');
    $model->setNs2('ns2.old.test');
    $model->setNs3('ns3.old.test');
    $model->setNs4('ns4.old.test');

    $service->syncWhois($model, new Box\Mod\Order\Entity\Order());

    expect($model->getNs1())->toBe('ns1.registrar.test');
    expect($model->getNs2())->toBe('ns2.old.test');
    expect($model->getNs3())->toBe('ns3.old.test');
    expect($model->getNs4())->toBe('ns4.old.test');
});

test('syncWhois still refreshes expires_at and synced_at', function (): void {
    $em = makeEntityManagerSpy();

    $whois = new Registrar_Domain();
    $whois->setContactRegistrar(makeWhoisContact());
    $whois->setExpirationTime(1_800_000_000); // 2027-01-15 08:00:00 UTC

    $service = makeSyncWhoisService($whois, $em);

    $model = new ServiceDomain();

    $service->syncWhois($model, new Box\Mod\Order\Entity\Order());

    expect($model->getExpiresAt()?->format('Y-m-d H:i:s'))->toBe('2027-01-15 08:00:00');
    expect($model->getSyncedAt())->toBeInstanceOf(DateTime::class);
    expect($em->flushCount)->toBe(1);
});

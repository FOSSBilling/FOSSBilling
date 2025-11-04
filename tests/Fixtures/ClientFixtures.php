<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Tests\Fixtures;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientGroup;
use Doctrine\Persistence\ObjectManager;

/**
 * Minimal fixture set for Client module testing.
 *
 * NO RedBean dependencies - uses Doctrine entities only.
 */
class ClientFixtures
{
    /**
     * Load minimal client fixtures into database.
     *
     * @param ObjectManager $manager Doctrine object manager
     */
    public function load(ObjectManager $manager): void
    {
        // Create default client group
        $defaultGroup = new ClientGroup();
        $defaultGroup->setTitle('Default');
        $manager->persist($defaultGroup);

        // Create test client
        $client = new Client();
        $client->setEmail('test@fossbilling.org');
        $client->setPass('89e495e7941cf9e40e6980d14a16bf023ccd4c91'); // SHA1 hash of 'password'
        $client->setFirstName('Test');
        $client->setLastName('Client');
        $client->setClientGroupId(1); // Will be set after group is persisted
        $client->setStatus(Client::STATUS_ACTIVE);
        $client->setPhoneCc('1');
        $client->setPhone('555-1234');
        $client->setCompany('Test Company');
        $client->setAddress1('123 Test Street');
        $client->setCity('Test City');
        $client->setCountry('US');
        $client->setCurrency('USD');
        $client->setPostcode('12345');

        $manager->persist($client);

        // Create additional test clients for list testing
        for ($i = 2; $i <= 5; ++$i) {
            $testClient = new Client();
            $testClient->setEmail(sprintf('client%d@fossbilling.org', $i));
            $testClient->setPass('89e495e7941cf9e40e6980d14a16bf023ccd4c91');
            $testClient->setFirstName('Client');
            $testClient->setLastName(sprintf('Number %d', $i));
            $testClient->setClientGroupId(1);
            $testClient->setStatus(Client::STATUS_ACTIVE);
            $testClient->setCountry('US');
            $testClient->setCurrency('USD');

            $manager->persist($testClient);
        }

        $manager->flush();
    }
}

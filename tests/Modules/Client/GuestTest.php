<?php

declare(strict_types=1);

it('can create and delete a client account', function () {
    $clientId = createTestClient();

    expect($clientId)->toBeInt()->toBeGreaterThan(0);

    deleteTestClient($clientId);
});

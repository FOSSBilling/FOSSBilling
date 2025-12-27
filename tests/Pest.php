<?php

declare(strict_types=1);

use APIHelper\Response;

pest()->project()->github('FOSSBilling/FOSSBilling');

/*
|--------------------------------------------------------------------------
| Custom Expectations for API Responses
|--------------------------------------------------------------------------
|
| These custom expectations make testing API responses more expressive
| and easier to read. They handle common patterns when testing the
| FOSSBilling API.
|
*/

expect()->extend('toBeSuccessfulResponse', function () {
    /** @var Response $response */
    $response = $this->value;

    expect($response->wasSuccessful())
        ->toBeTrue($response->generatePHPUnitMessage());

    return $this;
});

expect()->extend('toBeFailedResponse', function () {
    /** @var Response $response */
    $response = $this->value;

    expect($response->wasSuccessful())
        ->toBeFalse();

    return $this;
});

expect()->extend('toHaveResult', function () {
    /** @var Response $response */
    $response = $this->value;

    expect($response->wasSuccessful())
        ->toBeTrue($response->generatePHPUnitMessage());

    // Update the expectation value to the result
    $this->value = $response->getResult();

    return $this;
});

expect()->extend('toHaveErrorMessage', function (string $expectedMessage) {
    /** @var Response $response */
    $response = $this->value;

    expect($response->wasSuccessful())
        ->toBeFalse();

    expect($response->getErrorMessage())
        ->toBe($expectedMessage);

    return $this;
});

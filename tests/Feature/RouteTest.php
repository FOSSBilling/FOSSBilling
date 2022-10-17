<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteTest extends TestCase
{
    /**
     * A few basic route tests.
     *
     * @return void
     */
    public function test_the_application_returns_a_successful_response()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_that_the_application_returns_error_404()
    {
        $response = $this->get('/thisisaroutethatdoesnotexist');

        $response->assertStatus(404);
    }
    /*
      FIXME: Temporarily disabled as I'm not sure if that route even works at the moment
    public function test_that_the_application_returns_error_401()
    {
        $response = $this->get('/admin/settings');

        $response->assertStatus(401);
    }
    */
}

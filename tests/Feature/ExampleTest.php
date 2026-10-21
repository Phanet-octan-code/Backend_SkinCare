<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test verifying that guest visitors to root are redirected to login.
     */
    public function test_the_application_redirects_guest_to_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');

        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
    }
}

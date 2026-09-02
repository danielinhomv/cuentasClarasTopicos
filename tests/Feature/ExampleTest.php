<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_redirects_guests_from_home_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertRedirect(route('login', absolute: false));
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_redirects_guests_from_home_to_viajes(): void
    {
        $this->get('/')
            ->assertRedirect('/viajes');

        $this->get('/dashboard')
            ->assertRedirect('/viajes');
    }
}

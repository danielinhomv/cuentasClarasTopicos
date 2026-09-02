<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => 'Test Name',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_profile_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'beto@example.com']);
        $this->actingAs($user = User::factory()->create(['email' => 'ana@example.com']));

        $response = $this->put('/user/profile-information', [
            'name' => $user->name,
            'email' => 'beto@example.com',
        ]);

        $response->assertSessionHasErrors(['email'], errorBag: 'updateProfileInformation');
        $this->assertEquals('ana@example.com', $user->fresh()->email);
    }
}

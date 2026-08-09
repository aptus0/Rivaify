<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurrentUserEndpointTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_receives_an_unauthenticated_payload_without_a_401(): void
    {
        $this->getJson('/api/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'authenticated' => false,
                'user' => null,
                'store' => null,
            ]]);
    }

    public function test_sanctum_user_is_resolved_by_the_optional_auth_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.test',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.user.id', $user->ulid)
            ->assertJsonPath('data.user.email', 'merchant@example.test')
            ->assertJsonPath('data.store', null);
    }
}

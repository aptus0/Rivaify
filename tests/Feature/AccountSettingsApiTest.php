<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
    }

    public function test_authenticated_user_can_update_profile_without_store_membership(): void
    {
        $user = User::factory()->create([
            'name' => 'Eski Ad',
            'email' => 'account-profile@example.test',
        ]);

        $this->withValidCsrf()->actingAs($user)
            ->putJson('https://app.rivaify.com/user/profile-information', [
                'name' => 'Yeni Ad',
                'email' => 'account-profile@example.test',
            ])
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('Yeni Ad', $user->name);
        $this->assertSame('account-profile@example.test', $user->email);
        $this->assertNull($user->merchant()->first());
    }

    public function test_profile_and_password_validation_errors_use_the_fields_expected_by_settings_ui(): void
    {
        User::factory()->create(['email' => 'already-used@example.test']);
        $user = User::factory()->create([
            'email' => 'account-validation@example.test',
            'password' => Hash::make('current-secure-password'),
        ]);
        $this->withValidCsrf()->actingAs($user);

        $this->putJson('https://app.rivaify.com/user/profile-information', [
            'name' => '',
            'email' => 'already-used@example.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $this->putJson('https://app.rivaify.com/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->putJson('https://app.rivaify.com/user/password', [
            'current_password' => 'current-secure-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-confirmation',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->putJson('https://app.rivaify.com/user/password', [
            'current_password' => 'current-secure-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_account_mutations_require_authentication(): void
    {
        $this->withValidCsrf();

        $this->putJson('https://app.rivaify.com/user/profile-information', [
            'name' => 'Guest',
            'email' => 'guest@example.test',
        ])->assertUnauthorized();

        $this->putJson('https://app.rivaify.com/user/password', [
            'current_password' => 'guest-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnauthorized();
    }

    public function test_account_mutations_are_csrf_protected(): void
    {
        $user = User::factory()->create();
        $testEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->actingAs($user)
                ->putJson('https://app.rivaify.com/user/profile-information', [
                    'name' => 'CSRF Test',
                    'email' => $user->email,
                ])
                ->assertStatus(419);
        } finally {
            $this->app['env'] = $testEnvironment;
        }
    }

    private function withValidCsrf(): static
    {
        $token = 'account-settings-csrf-token';

        return $this->withSession(['_token' => $token])->withHeader('X-CSRF-TOKEN', $token);
    }
}

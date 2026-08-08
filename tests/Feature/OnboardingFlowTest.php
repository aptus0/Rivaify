<?php

namespace Tests\Feature;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Store\Actions\CreateStore;
use Modules\Store\DTOs\CreateStoreData;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Verification\Actions\SubmitVerificationRequest;
use Tests\TestCase;

/**
 * End-to-end coverage of the brief's target Sprint 01 flow (§ "İlk hedefimiz"):
 * register -> verify email -> create store -> business info -> tax info ->
 * verification request -> admin approval -> store active.
 *
 * Split into two tests along the same line a real deployment would: the
 * merchant-side steps happen in one browser session, the admin review
 * happens in a completely separate one (different person, different
 * machine). Simulating both personas' authenticated sessions inside a
 * single PHPUnit method — sharing one cookie jar — triggered a stale
 * session-guard artifact that isn't representative of two independent
 * browsers, so each persona gets its own test instead.
 */
class OnboardingFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // statefulApi()'s EnsureFrontendRequestsAreStateful only starts a
        // session for requests it recognizes as coming from the SPA (via
        // Referer/Origin matching SANCTUM_STATEFUL_DOMAINS).
        $this->withHeader('Referer', 'https://app.rivaify.com');
    }

    public function test_merchant_can_complete_onboarding_up_to_verification_submission(): void
    {
        // 1. Register
        // Fortify's routes are domain-scoped to config('fortify.domain') =
        // app.rivaify.com. A relative '/register' would resolve via
        // url('register') against APP_URL (rivaify.com, the marketing
        // site) — Symfony\Request::create() takes the host from an
        // absolute URL string over any HTTP_HOST server override, so the
        // full URL has to be passed explicitly here.
        $response = $this->postJson('https://app.rivaify.com/register', [
            'name' => 'Ayşe Yasemin',
            'email' => 'ayse@example.test',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);
        $response->assertSuccessful();

        $user = User::query()->where('email', 'ayse@example.test')->firstOrFail();

        // 2. Email verification (bypasses the signed-URL click for test
        // simplicity — Fortify's own test suite covers the signed link).
        $user->markEmailAsVerified();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 3. Create store
        $response = $this->postJson('/api/stores', ['name' => 'Yasemin Giyim']);
        $response->assertCreated();
        $response->assertJsonPath('data.onboarding_status', OnboardingStatus::BusinessInformation->value);

        // 4. Business information
        $response = $this->postJson('/api/store/business-profile', [
            'legal_name' => 'Yasemin Tekstil Ltd. Şti.',
            'contact_email' => 'info@yasemin.test',
            'addresses' => [[
                'type' => 'registered',
                'line1' => 'Örnek Mah. 1. Sk. No:5',
                'city' => 'İstanbul',
                'country_code' => 'TR',
            ]],
        ]);
        $response->assertOk();

        // 5. Tax information
        $response = $this->postJson('/api/store/tax-profile', [
            'tax_number' => '1234567890',
            'legal_entity_name' => 'Yasemin Tekstil Ltd. Şti.',
        ]);
        $response->assertOk();

        $me = $this->getJson('/api/me')->assertOk();
        $this->assertSame(OnboardingStatus::Documents->value, $me->json('data.store.onboarding_status'));

        // 6. Submit verification request (no document upload in this test —
        // that needs a real/mocked R2 bucket, covered separately)
        $response = $this->postJson('/api/store/verification-request');
        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');

        $me = $this->getJson('/api/me')->assertOk();
        $this->assertSame(OnboardingStatus::VerificationPending->value, $me->json('data.store.onboarding_status'));
    }

    public function test_admin_can_approve_a_pending_verification_request(): void
    {
        $merchantUser = User::factory()->create();
        $store = (new CreateStore)->handle($merchantUser, new CreateStoreData(name: 'Approve Me Store'));
        app(CurrentStore::class)->set($store);
        $verificationRequest = (new SubmitVerificationRequest)->handle($store->merchant, $store);

        $admin = User::factory()->create(['is_rivaify_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/verification-requests');
        $response->assertOk();
        $response->assertJsonPath('data.0.id', $verificationRequest->ulid);

        $response = $this->postJson("/api/admin/verification-requests/{$verificationRequest->ulid}/approve");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');

        $store->refresh();
        $this->assertSame('active', $store->status->value);
        $this->assertSame(OnboardingStatus::Completed->value, $store->onboarding_status->value);
        $this->assertSame('active', $store->merchant->fresh()->status->value);
    }

    public function test_admin_can_reject_a_pending_verification_request_with_a_reason(): void
    {
        $merchantUser = User::factory()->create();
        $store = (new CreateStore)->handle($merchantUser, new CreateStoreData(name: 'Reject Me Store'));
        app(CurrentStore::class)->set($store);
        $verificationRequest = (new SubmitVerificationRequest)->handle($store->merchant, $store);

        $admin = User::factory()->create(['is_rivaify_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/verification-requests/{$verificationRequest->ulid}/reject", [
            'reason' => 'Vergi levhası okunamıyor, lütfen tekrar yükleyin.',
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');

        $store->refresh();
        $this->assertSame(OnboardingStatus::Documents->value, $store->onboarding_status->value);
        $this->assertSame('draft', $store->merchant->fresh()->status->value);
    }

    public function test_non_admin_cannot_approve_verification_requests(): void
    {
        $merchant = User::factory()->create();

        Sanctum::actingAs($merchant);
        $response = $this->postJson('/api/admin/verification-requests/some-ulid/approve');

        $response->assertForbidden();
    }
}

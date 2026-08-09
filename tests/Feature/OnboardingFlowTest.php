<?php

namespace Tests\Feature;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Merchant\Actions\SubmitBusinessProfile;
use Modules\Merchant\Actions\SubmitTaxProfile;
use Modules\Merchant\DTOs\BusinessAddressData;
use Modules\Merchant\DTOs\SubmitBusinessProfileData;
use Modules\Merchant\DTOs\SubmitTaxProfileData;
use Modules\Store\Actions\CreateStore;
use Modules\Store\DTOs\CreateStoreData;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Verification\Actions\SubmitVerificationRequest;
use Modules\Verification\Actions\UploadVerificationDocument;
use Modules\Verification\Enums\DocumentType;
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

        // 6. Upload verification documents against a faked R2 disk — this is
        // the exact path that shipped broken twice in production: once for
        // missing R2 credentials, once for a null `status` on the freshly
        // created model (DB column default never reflected back into the
        // in-memory instance). Asserting the response body's `status` field
        // is what would have caught the second bug before it reached users.
        Storage::fake('r2');

        $response = $this->postJson('/api/store/verification-documents', [
            'type' => 'tax_certificate',
            'file' => UploadedFile::fake()->create('vergi-levhasi.pdf', 100, 'application/pdf'),
        ]);
        $response->assertCreated();
        $response->assertJsonPath('data.type', 'tax_certificate');
        $response->assertJsonPath('data.status', 'pending');

        $response = $this->postJson('/api/store/verification-documents', [
            'type' => 'identity',
            'file' => UploadedFile::fake()->create('kimlik.pdf', 100, 'application/pdf'),
        ]);
        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');

        $response = $this->getJson('/api/store/verification-documents');
        $response->assertOk();
        $this->assertCount(2, $response->json('data.documents'));

        // 7. Submit verification request
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

        // Admin routes require Host: ins.rivaify.com (EnsurePrivateAdminAccess
        // 404s on any other host) — a relative path would resolve against
        // APP_URL (rivaify.com) instead, same reasoning as the /register
        // call above.
        $response = $this->getJson('https://ins.rivaify.com/api/admin/verification-requests');
        $response->assertOk();
        // Membership, not data.0 — this suite runs against a shared,
        // persistent database (see class docblock / project memory), so
        // other genuinely pending requests may already be queued.
        $this->assertNotNull(
            collect($response->json('data'))->firstWhere('id', $verificationRequest->ulid),
            'Newly submitted verification request should appear in the admin queue',
        );

        $response = $this->postJson("https://ins.rivaify.com/api/admin/verification-requests/{$verificationRequest->ulid}/approve");
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

        $response = $this->postJson("https://ins.rivaify.com/api/admin/verification-requests/{$verificationRequest->ulid}/reject", [
            'reason' => 'Vergi levhası okunamıyor, lütfen tekrar yükleyin.',
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');

        $store->refresh();
        $this->assertSame(OnboardingStatus::Documents->value, $store->onboarding_status->value);
        $this->assertSame('draft', $store->merchant->fresh()->status->value);
    }

    /**
     * Covers the admin verification review screen (ins.rivaify.com)
     * end-to-end: builds a merchant with real business/tax profile data
     * and a real uploaded document, then asserts the admin dossier
     * endpoint returns all of it (VerificationReviewController::present()
     * reads merchant.businessProfile/taxProfile/owner, which the other
     * admin tests' bare CreateStore/SubmitVerificationRequest fixture
     * never populates). Deliberately builds the merchant side via direct
     * Action calls rather than real HTTP requests — this class's own
     * docblock already documents why: sharing one cookie jar between a
     * merchant's real session login and a later Sanctum::actingAs(:admin)
     * call causes AuthenticateSession to see a stale session/guard
     * mismatch and force a logout, so the following admin request 401s.
     */
    public function test_admin_dossier_includes_real_business_tax_and_document_data(): void
    {
        Storage::fake('r2');

        $merchantUser = User::factory()->create(['name' => 'Kerem Şahin', 'email' => 'kerem@example.test']);
        $store = (new CreateStore)->handle($merchantUser, new CreateStoreData(name: 'Kerem Elektronik'));
        app(CurrentStore::class)->set($store);

        (new SubmitBusinessProfile)->handle($store->merchant, $store, new SubmitBusinessProfileData(
            legalName: 'Kerem Elektronik Ltd. Şti.',
            addresses: [new BusinessAddressData(line1: 'Sanayi Cad. No:12', city: 'Ankara')],
            contactEmail: 'info@kerem-elektronik.test',
        ));
        (new SubmitTaxProfile)->handle($store->merchant, $store, new SubmitTaxProfileData(
            taxNumber: '9876543210',
            legalEntityName: 'Kerem Elektronik Ltd. Şti.',
            taxOffice: 'Çankaya',
        ));

        $verificationRequest = $store->verificationRequests()->firstOrCreate(['merchant_id' => $store->merchant->id]);
        (new UploadVerificationDocument)->handle(
            $verificationRequest,
            UploadedFile::fake()->create('vergi-levhasi.pdf', 100, 'application/pdf'),
            DocumentType::TaxCertificate,
        );
        $verificationRequest = (new SubmitVerificationRequest)->handle($store->merchant, $store);
        $verificationUlid = $verificationRequest->ulid;

        $admin = User::factory()->create(['is_rivaify_admin' => true]);
        Sanctum::actingAs($admin);

        // Asserts membership + fields rather than data.0 — this suite runs
        // against a shared, persistent database (see class docblock
        // elsewhere in this file / project memory), so other genuinely
        // pending requests may already be queued ahead of this one.
        $list = $this->getJson('https://ins.rivaify.com/api/admin/verification-requests');
        $list->assertOk();
        $myListEntry = collect($list->json('data'))->firstWhere('id', $verificationUlid);
        $this->assertNotNull($myListEntry, 'Newly submitted verification request should appear in the admin queue');
        $this->assertSame('Kerem Elektronik Ltd. Şti.', $myListEntry['business']['legal_name']);

        $dossier = $this->getJson("https://ins.rivaify.com/api/admin/verification-requests/{$verificationUlid}");
        $dossier->assertOk();
        $dossier->assertJsonPath('data.merchant.owner.email', 'kerem@example.test');
        $dossier->assertJsonPath('data.business.address.city', 'Ankara');
        $dossier->assertJsonPath('data.documents.0.type', 'tax_certificate');
        // Sensitive fields come back masked by default — only the trailing
        // 4 characters and never the raw value, until explicitly revealed.
        $this->assertStringEndsWith('3210', $dossier->json('data.tax.tax_number'));
        $this->assertStringNotContainsString('987654', $dossier->json('data.tax.tax_number'));
        $documentUlid = $dossier->json('data.documents.0.id');
        $this->assertStringContainsString(
            "/api/admin/verification-requests/{$verificationUlid}/documents/{$documentUlid}/view",
            $dossier->json('data.documents.0.view_url'),
        );

        $reveal = $this->postJson("https://ins.rivaify.com/api/admin/verification-requests/{$verificationUlid}/sensitive-fields/reveal", [
            'field' => 'tax_number',
        ]);
        $reveal->assertOk();
        $reveal->assertJsonPath('data.field', 'tax_number');
        $reveal->assertJsonPath('data.value', '9876543210');

        $view = $this->get("https://ins.rivaify.com/api/admin/verification-requests/{$verificationUlid}/documents/{$documentUlid}/view");
        $view->assertOk();
        $view->assertHeader('content-disposition');

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'internal.sensitive_field_revealed',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'internal.verification_document_viewed',
            'user_id' => $admin->id,
        ]);

        $approve = $this->postJson("https://ins.rivaify.com/api/admin/verification-requests/{$verificationUlid}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'approved');
    }

    /**
     * ins.rivaify.com has its own login (InternalAuthController), separate
     * from Fortify's app.rivaify.com one — see that controller's docblock.
     * Real HTTP login throughout (no Sanctum::actingAs mixed in), so this
     * doesn't hit the cookie-jar collision documented on the class above.
     */
    public function test_internal_admin_login_rejects_non_admin_and_accepts_admin(): void
    {
        $this->withHeader('Referer', 'https://ins.rivaify.com');

        $merchant = User::factory()->create(['password' => 'a-strong-password']);
        $rejected = $this->postJson('https://ins.rivaify.com/login', [
            'email' => $merchant->email,
            'password' => 'a-strong-password',
        ]);
        $rejected->assertUnprocessable();

        $me = $this->getJson('https://ins.rivaify.com/api/me')->assertOk();
        $this->assertFalse($me->json('data.authenticated'));

        $admin = User::factory()->create(['is_rivaify_admin' => true, 'password' => 'a-strong-password']);
        $loggedIn = $this->postJson('https://ins.rivaify.com/login', [
            'email' => $admin->email,
            'password' => 'a-strong-password',
        ]);
        $loggedIn->assertOk();

        $me = $this->getJson('https://ins.rivaify.com/api/me')->assertOk();
        $this->assertTrue($me->json('data.authenticated'));
        $this->assertTrue($me->json('data.user.is_rivaify_admin'));

        // assertGuest(), not a follow-up /api/me round trip: within one
        // PHPUnit test process, Laravel's test harness resolves "the
        // current session" for each simulated request more directly than
        // real cookie semantics, so a genuinely rotated session cookie
        // (verified separately below) doesn't reliably make the *next*
        // simulated request see a logged-out guard the way a real second
        // browser request would. assertGuest() checks the guard state
        // directly instead, which is what actually matters here.
        $logoutResponse = $this->postJson('https://ins.rivaify.com/logout')->assertOk();
        $this->assertGuest('web');

        // The response must still carry a rotated (not reused) session
        // cookie, since that's what makes a *real* subsequent browser
        // request come back unauthenticated. Array access, not dot-notation
        // config() — the host key itself contains literal dots.
        $sessionCookieName = config('session_hardening.hosts')['ins.rivaify.com']['cookie'];
        $newSessionCookie = collect($logoutResponse->headers->all('set-cookie'))
            ->first(fn (string $cookie) => str_starts_with($cookie, $sessionCookieName.'='));
        $this->assertNotNull($newSessionCookie, 'Logout response should set a rotated session cookie');
    }

    public function test_non_admin_cannot_approve_verification_requests(): void
    {
        $merchant = User::factory()->create();

        Sanctum::actingAs($merchant);
        $response = $this->postJson('https://ins.rivaify.com/api/admin/verification-requests/some-ulid/approve');

        $response->assertForbidden();
    }
}

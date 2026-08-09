<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Checkout\CheckoutFieldRequirement;
use Modules\Commerce\Enums\Payment\PaymentAccountPayoutStatus;
use Modules\Commerce\Enums\Payment\PaymentAccountVerificationStatus;
use Modules\Commerce\Enums\Payment\PaymentMethodStatus;
use Modules\Commerce\Enums\Payment\StorePaymentAccountStatus;
use Modules\Commerce\Models\Checkout\CheckoutSetting;
use Modules\Commerce\Models\Checkout\CheckoutSettingVersion;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Payment\PaymentMethod;
use Modules\Commerce\Models\Payment\StorePaymentAccount;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

/**
 * Sprint 6 (checkout & payments core) — schema-level coverage for the
 * three tables built this session on top of the already-live
 * payments/checkout_sessions system (payment_methods, checkout_settings +
 * checkout_setting_versions, store_payment_accounts). Asserts each
 * model's DB-level defaults are mirrored in `protected $attributes` (the
 * exact class of bug fixed in [[project-rivaify-verification-upload-fix]]
 * this session — a create() that omits a defaulted column must not leave
 * the in-memory model with a null value for it) and that the uniqueness
 * rules the design relies on are actually enforced by the database, not
 * just assumed.
 */
class CheckoutPaymentsCoreSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_method_defaults_and_relations(): void
    {
        $store = $this->makeStore('Payment Method Store');
        $this->setCurrentStore($store);
        $customer = $this->makeCustomer();

        $method = PaymentMethod::create([
            'customer_id' => $customer->id,
            'provider' => 'paytr',
            'provider_customer_token' => 'utoken_abc',
            'provider_card_token' => 'ctoken_abc',
            'brand' => 'visa',
            'last4' => '4242',
        ]);

        // DB-default-vs-Eloquent gotcha: these were never passed to create(),
        // so this only passes if protected $attributes mirrors the migration.
        $this->assertSame(PaymentMethodStatus::Active, $method->status);
        $this->assertFalse($method->is_default);
        $this->assertTrue($method->customer->is($customer));
        $this->assertSame($store->id, $method->store_id);
    }

    public function test_payment_method_provider_card_token_must_be_unique(): void
    {
        $store = $this->makeStore('Duplicate Card Store');
        $this->setCurrentStore($store);
        $customer = $this->makeCustomer();

        PaymentMethod::create([
            'customer_id' => $customer->id,
            'provider' => 'paytr',
            'provider_customer_token' => 'utoken_1',
            'provider_card_token' => 'ctoken_dupe',
        ]);

        $this->expectException(QueryException::class);
        PaymentMethod::create([
            'customer_id' => $customer->id,
            'provider' => 'paytr',
            'provider_customer_token' => 'utoken_2',
            'provider_card_token' => 'ctoken_dupe',
        ]);
    }

    public function test_checkout_setting_defaults_and_one_per_store(): void
    {
        $store = $this->makeStore('Checkout Branding Store');
        $this->setCurrentStore($store);

        $settings = CheckoutSetting::create([]);

        $this->assertSame('modern', $settings->layout);
        $this->assertSame('#111111', $settings->primary_color);
        $this->assertSame(CheckoutFieldRequirement::Required, $settings->phone_requirement);
        $this->assertSame(CheckoutFieldRequirement::Hidden, $settings->company_requirement);
        $this->assertTrue($settings->marketing_consent_enabled);
        $this->assertSame(0, $settings->current_version);
        $this->assertSame($store->id, $settings->store_id);

        $this->expectException(QueryException::class);
        CheckoutSetting::create([]);
    }

    public function test_publishing_creates_an_immutable_version_snapshot(): void
    {
        $store = $this->makeStore('Publish Store');
        $this->setCurrentStore($store);
        $admin = User::factory()->create();

        $settings = CheckoutSetting::create(['primary_color' => '#FF6B00']);

        $version = $settings->versions()->create([
            'version' => 1,
            'snapshot' => $settings->only(['layout', 'primary_color', 'background_color', 'phone_requirement']),
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);
        $settings->update(['current_version' => 1]);

        $this->assertTrue($version->checkoutSetting->is($settings));
        $this->assertTrue($version->publisher->is($admin));
        $this->assertSame('#FF6B00', $version->snapshot['primary_color']);
        $this->assertSame(1, $settings->fresh()->current_version);

        $this->expectException(QueryException::class);
        $settings->versions()->create([
            'version' => 1,
            'snapshot' => [],
            'published_at' => now(),
        ]);
    }

    public function test_store_payment_account_defaults_and_one_per_provider(): void
    {
        $store = $this->makeStore('PayTR Submerchant Store');
        $this->setCurrentStore($store);

        $account = StorePaymentAccount::create(['provider' => 'paytr']);

        $this->assertSame(StorePaymentAccountStatus::PendingVerification, $account->status);
        $this->assertSame(PaymentAccountVerificationStatus::NotStarted, $account->verification_status);
        $this->assertSame(PaymentAccountPayoutStatus::Ineligible, $account->payout_status);
        $this->assertSame($store->id, $account->store_id);

        $this->expectException(QueryException::class);
        StorePaymentAccount::create(['provider' => 'paytr']);
    }

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }

    private function setCurrentStore(Store $store): void
    {
        app(CurrentStore::class)->set($store);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer-'.uniqid().'@example.test',
        ]);
    }
}

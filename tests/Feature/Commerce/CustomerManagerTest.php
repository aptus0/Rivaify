<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Exceptions\Customer\CrossStoreCustomerException;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class CustomerManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_normalizes_email_and_reuses_the_store_customer(): void
    {
        $store = $this->makeStore('Customer Email Store');
        $this->setCurrentStore($store);
        $manager = app(CustomerManager::class);

        $customer = $manager->findOrCreate(new UpsertCustomerData(
            email: ' Ahmet@Example.COM ',
            firstName: 'Ahmet',
        ));
        $sameCustomer = $manager->findOrCreate(new UpsertCustomerData(
            email: 'ahmet@example.com',
            lastName: 'Yilmaz',
        ));

        $this->assertSame($customer->id, $sameCustomer->id);
        $this->assertSame('ahmet@example.com', $sameCustomer->email);
        $this->assertSame('Ahmet', $sameCustomer->first_name);
        $this->assertSame('Yilmaz', $sameCustomer->last_name);
    }

    public function test_same_email_creates_separate_customers_for_different_stores(): void
    {
        $storeA = $this->makeStore('Customer Store A');
        $storeB = $this->makeStore('Customer Store B');
        $manager = app(CustomerManager::class);

        $this->setCurrentStore($storeA);
        $customerA = $manager->findOrCreate(new UpsertCustomerData(email: 'same@example.com'));

        $this->setCurrentStore($storeB);
        $customerB = $manager->findOrCreate(new UpsertCustomerData(email: 'same@example.com'));

        $this->assertNotSame($customerA->id, $customerB->id);
        $this->assertSame($storeA->id, $customerA->store_id);
        $this->assertSame($storeB->id, $customerB->store_id);
    }

    public function test_explicit_default_address_replaces_the_previous_default_for_the_same_type(): void
    {
        $store = $this->makeStore('Customer Address Store');
        $this->setCurrentStore($store);
        $manager = app(CustomerManager::class);
        $customer = $manager->findOrCreate(new UpsertCustomerData(email: 'address@example.com'));

        $firstAddress = $manager->createAddress($customer, $this->shippingAddressData('Birinci Adres'));
        $secondAddress = $manager->createAddress($customer, $this->shippingAddressData('Ikinci Adres', true));

        $this->assertFalse($firstAddress->fresh()->is_default);
        $this->assertTrue($secondAddress->fresh()->is_default);
        $this->assertSame(1, $customer->addresses()
            ->where('type', CustomerAddressType::Shipping->value)
            ->where('is_default', true)
            ->count());
    }

    public function test_addresses_cannot_be_created_for_a_customer_from_another_store(): void
    {
        $storeA = $this->makeStore('Customer Address Store A');
        $storeB = $this->makeStore('Customer Address Store B');
        $manager = app(CustomerManager::class);

        $this->setCurrentStore($storeA);
        $customer = $manager->findOrCreate(new UpsertCustomerData(email: 'cross-store@example.com'));

        $this->setCurrentStore($storeB);
        $this->expectException(CrossStoreCustomerException::class);

        $manager->createAddress($customer, $this->shippingAddressData('Guvenli Adres'));
    }

    private function shippingAddressData(string $addressLine, bool $isDefault = false): CustomerAddressData
    {
        return new CustomerAddressData(
            type: CustomerAddressType::Shipping,
            firstName: 'Ahmet',
            lastName: 'Yilmaz',
            countryCode: 'tr',
            addressLine1: $addressLine,
            province: 'Bursa',
            district: 'Karacabey',
            postalCode: '16700',
            isDefault: $isDefault,
        );
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
}
<?php

namespace Modules\Commerce\Services\Customer;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Events\Customer\CustomerCreated;
use Modules\Commerce\Exceptions\Customer\CrossStoreCustomerException;
use Modules\Commerce\Exceptions\Customer\InvalidCustomerDataException;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Customer\CustomerAddress;

class CustomerManager
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function findOrCreate(UpsertCustomerData $data): Customer
    {
        $email = $this->normalizeEmail($data->email);

        return DB::transaction(function () use ($data, $email) {
            $customer = Customer::withTrashed()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                $customer = Customer::query()->create($this->customerAttributes($data, $email));
                CustomerCreated::dispatch($customer);

                return $customer;
            }

            if ($customer->trashed()) {
                $customer->restore();
            }

            $customer->fill($this->customerAttributes($data, $email))->save();

            return $customer;
        });
    }

    public function createAddress(Customer $customer, CustomerAddressData $data): CustomerAddress
    {
        $this->assertCustomerBelongsToCurrentStore($customer);
        $countryCode = $this->normalizeCountryCode($data->countryCode);

        return DB::transaction(function () use ($customer, $data, $countryCode) {
            $customer = Customer::query()->lockForUpdate()->find($customer->id);
            if ($customer === null) {
                throw new CrossStoreCustomerException('Customer does not belong to the current store.');
            }

            $hasDefaultAddress = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->where('type', $data->type->value)
                ->where('is_default', true)
                ->exists();
            $isDefault = $data->isDefault || ! $hasDefaultAddress;

            if ($isDefault) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->where('type', $data->type->value)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return $customer->addresses()->create([
                'type' => $data->type,
                'first_name' => $this->requiredText($data->firstName, 'first name'),
                'last_name' => $this->requiredText($data->lastName, 'last name'),
                'company' => $this->nullableText($data->company),
                'phone' => $this->nullableText($data->phone),
                'country_code' => $countryCode,
                'province' => $this->nullableText($data->province),
                'district' => $this->nullableText($data->district),
                'address_line_1' => $this->requiredText($data->addressLine1, 'address line 1'),
                'address_line_2' => $this->nullableText($data->addressLine2),
                'postal_code' => $this->nullableText($data->postalCode),
                'is_default' => $isDefault,
            ]);
        });
    }

    public function setDefaultAddress(Customer $customer, CustomerAddress $address): CustomerAddress
    {
        $this->assertCustomerBelongsToCurrentStore($customer);

        return DB::transaction(function () use ($customer, $address) {
            $customer = Customer::query()->lockForUpdate()->find($customer->id);
            $address = CustomerAddress::query()
                ->where('customer_id', $customer?->id)
                ->lockForUpdate()
                ->find($address->id);

            if ($customer === null || $address === null) {
                throw new CrossStoreCustomerException('Address does not belong to this customer.');
            }

            CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->where('type', $address->type->value)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            return $address->refresh();
        });
    }

    private function customerAttributes(UpsertCustomerData $data, string $email): array
    {
        return array_filter([
            'email' => $email,
            'first_name' => $this->nullableText($data->firstName),
            'last_name' => $this->nullableText($data->lastName),
            'phone' => $this->nullableText($data->phone),
            'accepts_marketing' => $data->acceptsMarketing,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function assertCustomerBelongsToCurrentStore(Customer $customer): void
    {
        if ($customer->store_id !== $this->currentStore->id()) {
            throw new CrossStoreCustomerException('Customer does not belong to the current store.');
        }
    }

    private function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidCustomerDataException('A valid customer email is required.');
        }

        return $email;
    }

    private function normalizeCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new InvalidCustomerDataException('A valid ISO country code is required.');
        }

        return $countryCode;
    }

    private function requiredText(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidCustomerDataException("Customer {$field} is required.");
        }

        return $value;
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
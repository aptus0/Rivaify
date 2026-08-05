<?php

namespace Modules\Merchant\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Merchant\Actions\SubmitBusinessProfile;
use Modules\Merchant\Actions\SubmitTaxProfile;
use Modules\Merchant\DTOs\BusinessAddressData;
use Modules\Merchant\DTOs\SubmitBusinessProfileData;
use Modules\Merchant\DTOs\SubmitTaxProfileData;

class MerchantOnboardingController extends Controller
{
    public function submitBusinessProfile(Request $request, CurrentStore $currentStore, SubmitBusinessProfile $action): JsonResponse
    {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'addresses' => ['required', 'array', 'min:1'],
            'addresses.*.type' => ['required', 'string', 'in:registered,billing,shipping'],
            'addresses.*.line1' => ['required', 'string', 'max:255'],
            'addresses.*.line2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['required', 'string', 'max:255'],
            'addresses.*.state' => ['nullable', 'string', 'max:255'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:32'],
            'addresses.*.country_code' => ['required', 'string', 'size:2'],
        ]);

        $store = $currentStore->store();

        $profile = $action->handle(
            $store->merchant,
            $store,
            new SubmitBusinessProfileData(
                legalName: $validated['legal_name'],
                addresses: array_map(
                    fn (array $a) => new BusinessAddressData(
                        line1: $a['line1'],
                        city: $a['city'],
                        line2: $a['line2'] ?? null,
                        state: $a['state'] ?? null,
                        postalCode: $a['postal_code'] ?? null,
                        countryCode: $a['country_code'],
                        type: $a['type'],
                    ),
                    $validated['addresses'],
                ),
                tradeName: $validated['trade_name'] ?? null,
                registrationNumber: $validated['registration_number'] ?? null,
                contactEmail: $validated['contact_email'] ?? null,
                contactPhone: $validated['contact_phone'] ?? null,
            ),
        );

        return response()->json(['data' => ['id' => $profile->ulid]], 200);
    }

    public function submitTaxProfile(Request $request, CurrentStore $currentStore, SubmitTaxProfile $action): JsonResponse
    {
        $validated = $request->validate([
            'tax_number' => ['required', 'string', 'max:255'],
            'legal_entity_name' => ['required', 'string', 'max:255'],
            'tax_office' => ['nullable', 'string', 'max:255'],
        ]);

        $store = $currentStore->store();

        $profile = $action->handle(
            $store->merchant,
            $store,
            new SubmitTaxProfileData(
                taxNumber: $validated['tax_number'],
                legalEntityName: $validated['legal_entity_name'],
                taxOffice: $validated['tax_office'] ?? null,
            ),
        );

        return response()->json(['data' => ['id' => $profile->ulid]], 200);
    }
}

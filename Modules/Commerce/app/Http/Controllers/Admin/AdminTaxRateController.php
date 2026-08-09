<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Enums\Tax\TaxRateStatus;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Store\Models\Store;

class AdminTaxRateController extends Controller
{
    public function index(CurrentStore $currentStore): JsonResponse
    {
        $rates = TaxRate::query()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('country_code')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $rates->map(fn (TaxRate $rate): array => $this->present($rate, $currentStore->store()))->values(),
            'default_country_code' => $currentStore->store()->country_code,
            'summary' => [
                'all' => $rates->count(),
                'active' => $rates->where('status', TaxRateStatus::Active)->count(),
                'inactive' => $rates->where('status', TaxRateStatus::Inactive)->count(),
            ],
        ]);
    }

    public function store(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $store = $currentStore->store();
        $rate = DB::transaction(function () use ($validated, $currentStore): TaxRate {
            $this->lockStore($currentStore);

            return TaxRate::query()->create($validated);
        });

        return response()->json(['data' => $this->present($rate, $store)], 201);
    }

    public function update(Request $request, string $ulid, CurrentStore $currentStore): JsonResponse
    {
        $rate = TaxRate::query()->where('ulid', $ulid)->firstOrFail();
        $validated = $this->validatePayload($request, false);

        [$rate, $store] = DB::transaction(function () use ($rate, $validated, $currentStore): array {
            $store = $this->lockStore($currentStore);
            $rate = TaxRate::query()->lockForUpdate()->findOrFail($rate->id);
            $nextCountryCode = $validated['country_code'] ?? $rate->country_code;
            $nextStatus = TaxRateStatus::from($validated['status'] ?? $rate->status->value);
            $currentlyProtectsDefault = $rate->status === TaxRateStatus::Active
                && $rate->country_code === $store->country_code;
            $willProtectDefault = $nextStatus === TaxRateStatus::Active
                && $nextCountryCode === $store->country_code;
            if ($currentlyProtectsDefault && ! $willProtectDefault) {
                $this->assertAnotherDefaultCountryRateExists($rate, $store);
            }
            $rate->update($validated);

            return [$rate->refresh(), $store];
        });

        return response()->json(['data' => $this->present($rate, $store)]);
    }

    public function destroy(string $ulid, CurrentStore $currentStore): JsonResponse
    {
        $rate = TaxRate::query()->where('ulid', $ulid)->firstOrFail();
        DB::transaction(function () use ($rate, $currentStore): void {
            $store = $this->lockStore($currentStore);
            $rate = TaxRate::query()->lockForUpdate()->findOrFail($rate->id);
            if ($rate->status === TaxRateStatus::Active && $rate->country_code === $store->country_code) {
                $this->assertAnotherDefaultCountryRateExists($rate, $store);
            }
            $rate->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $request->merge([
            ...($request->has('name') ? ['name' => trim((string) $request->input('name'))] : []),
            ...($request->has('country_code') ? ['country_code' => mb_strtoupper(trim((string) $request->input('country_code')))] : []),
            ...($request->has('status') ? ['status' => mb_strtolower((string) $request->input('status'))] : []),
        ]);

        return $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:255'],
            'country_code' => [$required, 'string', 'size:2', 'alpha'],
            'rate' => [$required, 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'is_inclusive' => [$required, 'boolean'],
            'status' => ['sometimes', 'string', Rule::enum(TaxRateStatus::class)],
        ]);
    }

    private function assertAnotherDefaultCountryRateExists(TaxRate $rate, Store $store): void
    {
        if (! TaxRate::query()
            ->whereKeyNot($rate->id)
            ->whereRaw('upper(country_code) = ?', [mb_strtoupper($store->country_code)])
            ->where('status', TaxRateStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'tax_rate' => ['Checkout için mağaza ülkesine ait en az bir aktif vergi oranı gereklidir.'],
            ]);
        }
    }

    private function lockStore(CurrentStore $currentStore): Store
    {
        return Store::query()->lockForUpdate()->findOrFail($currentStore->id());
    }

    /** @return array<string, mixed> */
    private function present(TaxRate $rate, Store $store): array
    {
        return [
            'id' => $rate->ulid,
            'name' => $rate->name,
            'country_code' => $rate->country_code,
            'rate' => $rate->rate,
            'is_inclusive' => $rate->is_inclusive,
            'status' => $rate->status->value,
            'applies_to_default_country' => $rate->country_code === $store->country_code,
            'created_at' => $rate->created_at?->toIso8601String(),
            'updated_at' => $rate->updated_at?->toIso8601String(),
        ];
    }
}

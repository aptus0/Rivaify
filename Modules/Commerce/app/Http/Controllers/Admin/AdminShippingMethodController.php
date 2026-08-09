<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Shipping\ShippingZone;
use Modules\Store\Models\Store;

class AdminShippingMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $methods = ShippingMethod::query()
            ->with('zone.regions')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
        $zones = ShippingZone::query()->with('regions')->orderBy('name')->get();

        return response()->json([
            'data' => $methods->map(fn (ShippingMethod $method): array => $this->present($method))->values(),
            'zones' => $zones->map(fn (ShippingZone $zone): array => $this->presentZone($zone))->values(),
            'summary' => [
                'all' => $methods->count(),
                'active' => $methods->where('status', ShippingMethodStatus::Active)->count(),
                'inactive' => $methods->where('status', ShippingMethodStatus::Inactive)->count(),
            ],
        ]);
    }

    public function store(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $method = DB::transaction(function () use ($validated, $currentStore): ShippingMethod {
            $this->lockStore($currentStore);

            return ShippingMethod::query()->create($this->attributes($validated));
        });

        return response()->json(['data' => $this->present($method->load('zone.regions'))], 201);
    }

    public function update(Request $request, string $ulid, CurrentStore $currentStore): JsonResponse
    {
        $method = ShippingMethod::query()->where('ulid', $ulid)->firstOrFail();
        $validated = $this->validatePayload($request, $method);

        $method = DB::transaction(function () use ($method, $validated, $currentStore): ShippingMethod {
            $this->lockStore($currentStore);
            $method = ShippingMethod::query()->lockForUpdate()->findOrFail($method->id);
            $attributes = $this->attributes($validated, $method);
            $nextStatus = ShippingMethodStatus::from($attributes['status'] ?? $method->status->value);
            if ($method->status === ShippingMethodStatus::Active && $nextStatus === ShippingMethodStatus::Inactive) {
                $this->assertAnotherActiveMethodExists($method);
            }
            $method->update($attributes);

            return $method->refresh()->load('zone.regions');
        });

        return response()->json(['data' => $this->present($method)]);
    }

    public function destroy(string $ulid, CurrentStore $currentStore): JsonResponse
    {
        $method = ShippingMethod::query()->where('ulid', $ulid)->firstOrFail();
        DB::transaction(function () use ($method, $currentStore): void {
            $this->lockStore($currentStore);
            $method = ShippingMethod::query()->lockForUpdate()->findOrFail($method->id);
            if ($method->status === ShippingMethodStatus::Active) {
                $this->assertAnotherActiveMethodExists($method);
            }
            $method->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, ?ShippingMethod $method = null): array
    {
        $creating = $method === null;
        $required = $creating ? 'required' : 'sometimes';
        $request->merge([
            ...($request->has('name') ? ['name' => trim((string) $request->input('name'))] : []),
            ...($request->has('type') ? ['type' => mb_strtolower((string) $request->input('type'))] : []),
            ...($request->has('status') ? ['status' => mb_strtolower((string) $request->input('status'))] : []),
            ...($request->has('shipping_zone_id') && $request->input('shipping_zone_id') === '' ? ['shipping_zone_id' => null] : []),
        ]);
        $validated = $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:255'],
            'type' => [$required, 'string', Rule::enum(ShippingMethodType::class)],
            'price' => [$required, 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'minimum_order' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'maximum_order' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'estimated_days_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'estimated_days_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['sometimes', 'string', Rule::enum(ShippingMethodStatus::class)],
            'shipping_zone_id' => ['sometimes', 'nullable', 'string', 'size:26'],
        ]);

        $minimumOrder = array_key_exists('minimum_order', $validated) ? $validated['minimum_order'] : $method?->minimum_order;
        $maximumOrder = array_key_exists('maximum_order', $validated) ? $validated['maximum_order'] : $method?->maximum_order;
        if ($minimumOrder !== null && $maximumOrder !== null && (float) $maximumOrder < (float) $minimumOrder) {
            throw ValidationException::withMessages([
                'maximum_order' => ['Maksimum sipariş tutarı minimum sipariş tutarından küçük olamaz.'],
            ]);
        }
        $estimatedMin = array_key_exists('estimated_days_min', $validated) ? $validated['estimated_days_min'] : $method?->estimated_days_min;
        $estimatedMax = array_key_exists('estimated_days_max', $validated) ? $validated['estimated_days_max'] : $method?->estimated_days_max;
        if ($estimatedMin !== null && $estimatedMax !== null && $estimatedMax < $estimatedMin) {
            throw ValidationException::withMessages([
                'estimated_days_max' => ['Maksimum teslimat günü minimum teslimat gününden küçük olamaz.'],
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?ShippingMethod $method = null): array
    {
        $attributes = [];
        foreach ([
            'name', 'type', 'price', 'minimum_order', 'maximum_order',
            'estimated_days_min', 'estimated_days_max', 'status',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }
        $effectiveType = $attributes['type'] ?? $method?->type->value;
        if ($effectiveType === ShippingMethodType::FreeShipping->value) {
            $attributes['price'] = '0.00';
        }
        if (array_key_exists('shipping_zone_id', $validated)) {
            $attributes['shipping_zone_id'] = $this->resolveZoneId($validated['shipping_zone_id']);
        }

        return $attributes;
    }

    private function resolveZoneId(?string $zoneUlid): ?int
    {
        if ($zoneUlid === null) {
            return null;
        }
        $zone = ShippingZone::query()->where('ulid', $zoneUlid)->first();
        if ($zone === null) {
            throw ValidationException::withMessages([
                'shipping_zone_id' => ['Kargo bölgesi bu mağazada bulunamadı.'],
            ]);
        }

        return $zone->id;
    }

    private function assertAnotherActiveMethodExists(ShippingMethod $method): void
    {
        if (! ShippingMethod::query()
            ->whereKeyNot($method->id)
            ->where('status', ShippingMethodStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'shipping_method' => ['Checkout için en az bir aktif kargo yöntemi gereklidir.'],
            ]);
        }
    }

    private function lockStore(CurrentStore $currentStore): Store
    {
        return Store::query()->lockForUpdate()->findOrFail($currentStore->id());
    }

    /** @return array<string, mixed> */
    private function present(ShippingMethod $method): array
    {
        $method->loadMissing('zone.regions');

        return [
            'id' => $method->ulid,
            'name' => $method->name,
            'type' => $method->type->value,
            'price' => $method->price,
            'minimum_order' => $method->minimum_order,
            'maximum_order' => $method->maximum_order,
            'estimated_days_min' => $method->estimated_days_min,
            'estimated_days_max' => $method->estimated_days_max,
            'status' => $method->status->value,
            'zone' => $method->zone === null ? null : $this->presentZone($method->zone),
            'created_at' => $method->created_at?->toIso8601String(),
            'updated_at' => $method->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentZone(ShippingZone $zone): array
    {
        $zone->loadMissing('regions');

        return [
            'id' => $zone->ulid,
            'name' => $zone->name,
            'regions' => $zone->regions->map(fn ($region): array => [
                'country_code' => $region->country_code,
                'province' => $region->province,
            ])->values(),
        ];
    }
}

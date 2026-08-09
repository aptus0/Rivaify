<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Discount\DiscountConditionType;
use Modules\Commerce\Enums\Discount\DiscountStatus;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Models\Discount\Discount;

class AdminDiscountController extends Controller
{
    public function index(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (DiscountStatus $status) => $status->value, DiscountStatus::cases()))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $summary = [
            'all' => Discount::query()->count(),
            'active' => Discount::query()->where('status', DiscountStatus::Active->value)->count(),
            'inactive' => Discount::query()->where('status', DiscountStatus::Inactive->value)->count(),
            'total_usage' => (int) Discount::query()->sum('usage_count'),
        ];

        $discounts = Discount::query()
            ->with('conditions')
            ->when(isset($validated['q']), function ($query) use ($validated): void {
                $search = trim($validated['q']);
                if ($search !== '') {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%");
                    });
                }
            })
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $discounts->getCollection()->map(fn (Discount $discount): array => $this->present($discount))->values(),
            'currency' => $currentStore->store()->default_currency,
            'summary' => $summary,
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'last_page' => $discounts->lastPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
            ],
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $discount = Discount::query()->with('conditions')->where('ulid', $ulid)->firstOrFail();

        return response()->json(['data' => $this->present($discount)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $discount = DB::transaction(function () use ($validated) {
            $code = isset($validated['code']) ? mb_strtoupper(trim($validated['code'])) : null;
            if ($code !== null && Discount::query()->where('code', $code)->exists()) {
                abort(422, 'discount_code_already_exists');
            }

            $discount = Discount::query()->create([
                'name' => trim($validated['name']),
                'code' => $code,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'status' => $validated['status'] ?? DiscountStatus::Active->value,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'minimum_purchase' => $validated['minimum_purchase'] ?? null,
            ]);
            $this->syncConditions($discount, $validated['conditions'] ?? []);

            return $discount->load('conditions');
        });

        return response()->json(['data' => $this->present($discount)], 201);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $discount = Discount::query()->where('ulid', $ulid)->firstOrFail();
        $validated = $this->validatePayload($request, false, $discount->type);

        $discount = DB::transaction(function () use ($discount, $validated) {
            $attributes = [];
            foreach (['name', 'type', 'value', 'status', 'starts_at', 'ends_at', 'usage_limit', 'minimum_purchase'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $attributes[$field] = $validated[$field];
                }
            }
            if (array_key_exists('code', $validated)) {
                $code = $validated['code'] === null ? null : mb_strtoupper(trim($validated['code']));
                if ($code !== null && Discount::query()->where('code', $code)->whereKeyNot($discount->id)->exists()) {
                    abort(422, 'discount_code_already_exists');
                }
                $attributes['code'] = $code;
            }
            $discount->update($attributes);
            if (array_key_exists('conditions', $validated)) {
                $discount->conditions()->delete();
                $this->syncConditions($discount, $validated['conditions']);
            }

            return $discount->fresh()->load('conditions');
        });

        return response()->json(['data' => $this->present($discount)]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $discount = Discount::query()->where('ulid', $ulid)->firstOrFail();
        $isAttachedToCheckout = DB::table('carts')->where('discount_id', $discount->id)->exists()
            || DB::table('checkout_sessions')->where('discount_id', $discount->id)->exists();
        if ($discount->usage_count > 0 || $discount->usages()->exists() || $isAttachedToCheckout) {
            return response()->json(['message' => 'discount_has_usage'], 409);
        }
        $discount->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating = true, ?DiscountType $currentType = null): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $valuePresence = $creating ? 'required' : 'required_with:type';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => [$required, 'string', 'in:'.implode(',', array_map(fn (DiscountType $type) => $type->value, DiscountType::cases()))],
            'value' => [$valuePresence, 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail) use ($request, $currentType): void {
                $type = $request->input('type', $currentType?->value);
                if ($type === DiscountType::Percentage->value && (float) $value > 100) {
                    $fail('Yüzde indirimi 100 değerini aşamaz.');
                }
                if ($type === DiscountType::FreeShipping->value && (float) $value !== 0.0) {
                    $fail('Ücretsiz kargo indiriminin değeri 0 olmalıdır.');
                }
            }],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_map(fn (DiscountStatus $status) => $status->value, DiscountStatus::cases()))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'minimum_purchase' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*.type' => ['required_with:conditions', 'string', 'in:'.implode(',', array_map(fn (DiscountConditionType $type) => $type->value, DiscountConditionType::cases()))],
            'conditions.*.operator' => ['nullable', 'string', 'in:>,>=,<,<=,=,gt,gte,lt,lte,eq'],
            'conditions.*.value' => ['required_with:conditions', 'array'],
            'conditions.*.value.amount' => ['nullable', 'numeric', 'min:0'],
            'conditions.*.value.product_ids' => ['nullable', 'array'],
            'conditions.*.value.product_ids.*' => ['integer'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     */
    private function syncConditions(Discount $discount, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $discount->conditions()->create([
                'type' => $condition['type'],
                'operator' => $condition['operator'] ?? null,
                'value' => $condition['value'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Discount $discount): array
    {
        return [
            'id' => $discount->ulid,
            'name' => $discount->name,
            'code' => $discount->code,
            'type' => $discount->type->value,
            'value' => $discount->value,
            'status' => $discount->status->value,
            'availability' => $this->availability($discount),
            'starts_at' => $discount->starts_at?->toIso8601String(),
            'ends_at' => $discount->ends_at?->toIso8601String(),
            'usage_limit' => $discount->usage_limit,
            'usage_count' => $discount->usage_count,
            'minimum_purchase' => $discount->minimum_purchase,
            'created_at' => $discount->created_at?->toIso8601String(),
            'updated_at' => $discount->updated_at?->toIso8601String(),
            'conditions' => $discount->conditions->map(fn ($condition): array => [
                'type' => $condition->type->value,
                'operator' => $condition->operator,
                'value' => $condition->value,
            ])->values(),
        ];
    }

    private function availability(Discount $discount): string
    {
        if ($discount->status === DiscountStatus::Inactive) {
            return 'inactive';
        }
        if ($discount->starts_at?->isFuture()) {
            return 'scheduled';
        }
        if ($discount->ends_at?->isPast()) {
            return 'expired';
        }
        if ($discount->usage_limit !== null && $discount->usage_count >= $discount->usage_limit) {
            return 'usage_limit_reached';
        }

        return 'active';
    }
}

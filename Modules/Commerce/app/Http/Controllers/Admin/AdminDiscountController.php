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
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $discounts = Discount::query()->with('conditions')->orderByDesc('created_at')->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $discounts->getCollection()->map(fn (Discount $discount): array => $this->present($discount))->values(),
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'last_page' => $discounts->lastPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
            ],
        ]);
    }

    public function store(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $discount = DB::transaction(function () use ($validated, $currentStore) {
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
        $validated = $this->validatePayload($request, false);

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

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:128'],
            'type' => [$required, 'string', 'in:'.implode(',', array_map(fn (DiscountType $type) => $type->value, DiscountType::cases()))],
            'value' => [$required, 'numeric', 'min:0'],
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
            'starts_at' => $discount->starts_at?->toIso8601String(),
            'ends_at' => $discount->ends_at?->toIso8601String(),
            'usage_limit' => $discount->usage_limit,
            'usage_count' => $discount->usage_count,
            'minimum_purchase' => $discount->minimum_purchase,
            'conditions' => $discount->conditions->map(fn ($condition): array => [
                'type' => $condition->type->value,
                'operator' => $condition->operator,
                'value' => $condition->value,
            ])->values(),
        ];
    }
}
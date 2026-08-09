<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Presenters\CustomerPresenter;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use App\Core\Tenancy\CurrentStore;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
{
    public function store(Request $request, CustomerManager $customers): JsonResponse
    {
        $validated = $this->validateCustomer($request);
        $customer = $customers->findOrCreate(new UpsertCustomerData(
            email: $validated['email'], firstName: $validated['first_name'] ?? null,
            lastName: $validated['last_name'] ?? null, phone: $validated['phone'] ?? null,
            acceptsMarketing: $validated['accepts_marketing'] ?? false,
        ));
        return response()->json(['data' => CustomerPresenter::summary($customer)], 201);
    }

    public function update(Request $request, string $ulid, CurrentStore $currentStore): JsonResponse
    {
        $customer = Customer::query()->where('ulid', $ulid)->firstOrFail();
        $validated = $request->validate([
            'email' => ['sometimes', 'email:rfc', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)->where(fn ($query) => $query->where('store_id', $currentStore->id()))],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'], 'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:64'], 'accepts_marketing' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,disabled,blocked'],
        ]);
        if (isset($validated['email'])) $validated['email'] = mb_strtolower(trim($validated['email']));
        $customer->update($validated);
        return response()->json(['data' => CustomerPresenter::summary($customer->fresh())]);
    }
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $customers = Customer::query()->orderByDesc('last_order_at');
        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $customers->where(function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
            });
        }
        $customers = $customers->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $customers->getCollection()->map(fn (Customer $customer): array => CustomerPresenter::summary($customer))->values(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $customer = Customer::query()
            ->with(['addresses', 'orders.customer', 'events'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        return response()->json(['data' => CustomerPresenter::detail($customer)]);
    }

    private function validateCustomer(Request $request): array
    {
        return $request->validate(['email' => ['required', 'email:rfc', 'max:255'], 'first_name' => ['nullable', 'string', 'max:255'], 'last_name' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:64'], 'accepts_marketing' => ['nullable', 'boolean']]);
    }
}

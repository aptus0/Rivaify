<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Presenters\CustomerPresenter;
use Modules\Commerce\Models\Customer\Customer;

class AdminCustomerController extends Controller
{
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
}
<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreRolePermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Dashboard\MerchantDashboardAudience;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\StoreUser;

class AdminWorkspaceController extends Controller
{
    public function search(Request $request, CurrentStore $currentStore): JsonResponse
    {
        if (is_string($request->input('q'))) {
            $request->merge(['q' => trim((string) $request->input('q'))]);
        }
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $query = mb_strtolower($validated['q']);
        $like = '%'.$this->escapeLike($query).'%';
        $role = $this->role($request, $currentStore);
        $results = collect();

        if (StoreRolePermissions::allows($role, 'products.view')) {
            $products = Product::query()
                ->where(function ($builder) use ($like): void {
                    $builder->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$like])
                        ->orWhereHas('variants', function ($variantQuery) use ($like): void {
                            $variantQuery->whereRaw('LOWER(sku) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(barcode) LIKE ?', [$like]);
                        });
                })
                ->latest('updated_at')
                ->limit(5)
                ->get(['id', 'ulid', 'title', 'status'])
                ->map(fn (Product $product): array => [
                    'id' => $product->ulid,
                    'type' => 'product',
                    'title' => $product->title,
                    'description' => 'Ürün · '.match ($product->status->value) {
                        'active' => 'Aktif',
                        'archived' => 'Arşivlenmiş',
                        default => 'Taslak',
                    },
                    'path' => '/products/'.$product->ulid,
                ]);
            $results = $results->concat($products);
        }

        if (StoreRolePermissions::allows($role, 'orders.view')) {
            $canViewCustomers = StoreRolePermissions::allows($role, 'customers.view');
            $ordersQuery = Order::query()
                ->where(function ($builder) use ($like, $canViewCustomers): void {
                    $builder->whereRaw('LOWER(order_number) LIKE ?', [$like]);
                    if ($canViewCustomers) {
                        $builder->orWhereRaw('LOWER(customer_email) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(customer_phone) LIKE ?', [$like])
                            ->orWhereHas('customer', function ($customerQuery) use ($like): void {
                                $customerQuery->whereRaw('LOWER(first_name) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                            });
                    }
                })
                ->latest('placed_at')
                ->limit(5);
            if ($canViewCustomers) {
                $ordersQuery->with('customer:id,ulid,first_name,last_name,email');
            }
            $orders = $ordersQuery->get()->map(fn (Order $order): array => [
                'id' => $order->ulid,
                'type' => 'order',
                'title' => $order->order_number,
                'description' => $canViewCustomers
                    ? 'Sipariş · '.($order->customer?->email ?? $order->customer_email ?? 'Misafir')
                    : 'Sipariş · Operasyon kaydı',
                'path' => '/orders/'.$order->ulid,
            ]);
            $results = $results->concat($orders);
        }

        if (StoreRolePermissions::allows($role, 'customers.view')) {
            $customers = Customer::query()
                ->where(function ($builder) use ($like): void {
                    $builder->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
                })
                ->latest('updated_at')
                ->limit(5)
                ->get(['id', 'ulid', 'first_name', 'last_name', 'email'])
                ->map(function (Customer $customer): array {
                    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));

                    return [
                        'id' => $customer->ulid,
                        'type' => 'customer',
                        'title' => $name !== '' ? $name : $customer->email,
                        'description' => 'Müşteri · '.$customer->email,
                        'path' => '/customers/'.$customer->ulid,
                    ];
                });
            $results = $results->concat($customers);
        }

        return response()->json(['data' => $results->values()]);
    }

    public function notifications(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $store = $currentStore->store();
        $audience = MerchantDashboardAudience::fromRole($this->role($request, $currentStore));
        $items = collect();

        if ($audience->canViewOrders()) {
            $orderColumns = ['id', 'ulid', 'order_number', 'placed_at'];
            if ($audience->canViewSales()) {
                array_push($orderColumns, 'grand_total', 'currency');
            }
            Order::query()
                ->where('payment_status', 'paid')
                ->latest('placed_at')
                ->limit(4)
                ->get($orderColumns)
                ->each(function (Order $order) use ($items, $audience): void {
                    $items->push([
                        'id' => 'order-'.$order->ulid,
                        'type' => 'order',
                        'title' => 'Yeni ödenmiş sipariş',
                        'description' => $audience->canViewSales()
                            ? $order->order_number.' · '.$order->grand_total.' '.$order->currency
                            : $order->order_number.' · Operasyon kaydı',
                        'path' => '/orders/'.$order->ulid,
                        'tone' => 'success',
                        'created_at' => $order->placed_at?->toISOString(),
                    ]);
                });

            $paymentQuery = Payment::query()
                ->where('status', 'failed')
                ->latest('failed_at')
                ->limit(4);
            $paymentColumns = ['id', 'ulid', 'failed_at'];
            if ($audience->canViewSales()) {
                $paymentQuery->with('checkout:id,ulid,email');
                array_push($paymentColumns, 'checkout_id', 'amount', 'currency');
            }
            $paymentQuery->get($paymentColumns)
                ->each(function (Payment $payment) use ($items, $audience): void {
                    $items->push([
                        'id' => 'payment-'.$payment->ulid,
                        'type' => 'payment',
                        'title' => 'Ödeme başarısız',
                        'description' => $audience->canViewSales()
                            ? ($payment->checkout?->email ?? 'Müşteri').' · '.$payment->amount.' '.$payment->currency
                            : 'Ödeme ayrıntıları rolünüz için gizlendi.',
                        'path' => $audience->canViewSales() ? '/analytics' : '/orders?payment_status=failed',
                        'tone' => 'danger',
                        'created_at' => $payment->failed_at?->toISOString(),
                    ]);
                });
        }

        if ($audience->canViewInventory()) {
            $lowStock = DB::table('inventory_levels')
                ->join('inventory_items', 'inventory_items.id', '=', 'inventory_levels.inventory_item_id')
                ->join('product_variants', 'product_variants.id', '=', 'inventory_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('inventory_levels.store_id', $store->id)
                ->where('inventory_items.store_id', $store->id)
                ->where('product_variants.store_id', $store->id)
                ->where('products.store_id', $store->id)
                ->where('inventory_items.is_tracked', true)
                ->whereNull('product_variants.deleted_at')
                ->whereNull('products.deleted_at')
                ->selectRaw('products.ulid as product_ulid, products.title, product_variants.title as variant_title, SUM(inventory_levels.available_quantity - inventory_levels.reserved_quantity) as sellable, MAX(inventory_levels.updated_at) as updated_at')
                ->groupBy('products.ulid', 'products.title', 'product_variants.id', 'product_variants.title')
                ->havingRaw('SUM(inventory_levels.available_quantity - inventory_levels.reserved_quantity) <= 5')
                ->orderBy('sellable')
                ->limit(5)
                ->get();

            foreach ($lowStock as $row) {
                $sellable = (int) $row->sellable;
                $items->push([
                    'id' => 'stock-'.$row->product_ulid.'-'.sha1((string) $row->variant_title),
                    'type' => 'inventory',
                    'title' => $sellable <= 0 ? 'Stok tükendi' : 'Stok azalıyor',
                    'description' => $row->title.' · '.$row->variant_title.' · '.$sellable.' adet',
                    'path' => '/inventory',
                    'tone' => $sellable <= 0 ? 'danger' : 'warning',
                    'created_at' => $row->updated_at,
                ]);
            }
        }

        if ($audience->canViewStoreOperations() && (! config('commerce.payments.paytr.merchant_id') || ! config('commerce.payments.paytr.merchant_key') || ! config('commerce.payments.paytr.merchant_salt'))) {
            $items->push([
                'id' => 'integration-paytr',
                'type' => 'integration',
                'title' => 'PayTR kurulumu tamamlanmadı',
                'description' => 'Canlı ödeme almak için PayTR mağaza bilgilerini sunucu ortamına ekleyin.',
                'path' => '/settings',
                'tone' => 'warning',
                'created_at' => null,
            ]);
        }

        return response()->json([
            'data' => $items->sortByDesc('created_at')->values()->take(12)->values(),
            'meta' => ['total' => $items->count()],
        ]);
    }

    private function role(Request $request, CurrentStore $currentStore): StoreUserRole
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $membership = StoreUser::query()
            ->where('store_id', $currentStore->id())
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->first();
        if ($membership === null) {
            abort(403);
        }

        return $membership->role;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

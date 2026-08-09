<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Enums\Shipping\ShipmentStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Finance\FinancialTransaction;
use Modules\Commerce\Models\Inventory\InventoryItem;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\Refund;
use Modules\Commerce\Services\Finance\FinanceLedger;
use Modules\Commerce\Services\Fulfillment\FulfillmentManager;
use Modules\Commerce\Services\Returns\ReturnManager;
use Modules\Commerce\Services\Shipping\ShippingManager;
use Modules\Commerce\ValueObjects\Money;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class FulfillmentReturnsFinanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(CurrentStore::class)->clear();

        parent::tearDown();
    }

    public function test_split_fulfillment_barcode_shipping_and_duplicate_shipment_are_safe(): void
    {
        [, $store] = $this->makeStoreWithUser('Sprint 7 Fulfillment');
        $this->inStore($store, function (): void {
            [$order, $variant] = $this->paidOrderWithInventory(quantity: 2, available: 5);
            $bursa = InventoryLocation::query()->create(['name' => 'Bursa Depo', 'code' => 'BUR']);
            $istanbul = InventoryLocation::query()->create(['name' => 'Istanbul Depo', 'code' => 'IST']);
            $manager = app(FulfillmentManager::class);

            $first = $manager->createForOrder($order, $bursa, [[
                'order_item_id' => $order->items->first()->ulid,
                'quantity' => 1,
            ]]);
            $second = $manager->createForOrder($order->fresh('items'), $istanbul, [[
                'order_item_id' => $order->items->first()->ulid,
                'quantity' => 1,
            ]]);
            $this->assertNotSame($first->ulid, $second->ulid);
            $this->assertSame(2, $order->fresh()->fulfillments()->count());

            $manager->start($first);
            try {
                $manager->scanBarcode($first, 'WRONG-BARCODE');
                $this->fail('Wrong barcode should be rejected.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('Bu ürün siparişe ait değil.', $exception->getMessage());
            }
            $picked = $manager->scanBarcode($first, (string) $variant->barcode);
            $this->assertSame('picked', $picked->items->first()->status->value);
            $ready = $manager->pack($picked, ['type' => 'small_box', 'weight' => '1.2']);
            $shipping = app(ShippingManager::class);
            $shipment = $shipping->createShipment($ready, 'yurtici');
            $duplicate = $shipping->createShipment($ready, 'yurtici');
            $this->assertSame($shipment->id, $duplicate->id);
            $delivered = $shipping->updateStatus($shipment, ShipmentStatus::Delivered, 'Teslim edildi.', 'evt-delivered');
            $again = $shipping->updateStatus($delivered, ShipmentStatus::Delivered, 'Tekrar teslim.', 'evt-delivered');
            $this->assertSame(2, $again->events()->count());
        });
    }

    public function test_return_quantity_restock_refund_idempotency_and_finance_ledger(): void
    {
        [, $store] = $this->makeStoreWithUser('Sprint 7 Returns');
        $this->inStore($store, function (): void {
            [$order, $variant, $payment, $level] = $this->paidOrderWithInventory(quantity: 1, available: 0);
            app(FinanceLedger::class)->recordSale($order);
            $returns = app(ReturnManager::class);

            try {
                $returns->request($order, [[
                    'order_item_id' => $order->items->first()->ulid,
                    'quantity' => 2,
                ]]);
                $this->fail('Return quantity above purchase should be rejected.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('İade miktarı satın alınan miktarı aşamaz.', $exception->getMessage());
            }
            $return = $returns->request($order, [[
                'order_item_id' => $order->items->first()->ulid,
                'quantity' => 1,
                'reason_code' => 'wrong_size',
            ]], 'wrong_size', 'Beden büyük geldi.');
            $returns->approve($return);
            $returns->receive($return);
            $returns->inspect($return, [[
                'return_item_id' => $return->items->first()->ulid,
                'condition' => 'opened',
                'restock' => true,
            ]], $level->inventory_location_id);
            $this->assertSame(1, $level->fresh()->available_quantity);

            $refund = $returns->refund($return->fresh('order'), Money::fromDecimal('100.00', 'TRY'), 'ret-'.$return->ulid);
            $duplicate = $returns->refund($return->fresh('order'), Money::fromDecimal('100.00', 'TRY'), 'ret-'.$return->ulid);
            $this->assertSame($refund->id, $duplicate->id);
            $this->assertSame('refunded', $payment->fresh()->status->value);
            $this->assertSame(2, FinancialTransaction::query()->count());

            try {
                app(\Modules\Commerce\Services\Payment\RefundManager::class)->refund($order->fresh(), Money::fromDecimal('1.00', 'TRY'), 'over-refund', payment: $payment->fresh());
                $this->fail('Over-refund should be rejected.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('İade tutarı ödeme bakiyesini aşamaz.', $exception->getMessage());
            }
        });
    }

    /** @return array{0: Order, 1: ProductVariant, 2: Payment, 3: InventoryLevel} */
    private function paidOrderWithInventory(int $quantity, int $available): array
    {
        $customer = Customer::query()->create([
            'first_name' => 'Yasemin',
            'last_name' => 'Giyim',
            'email' => 'yasemin-'.str()->lower(str()->random(6)).'@example.com',
        ]);
        $product = Product::query()->create([
            'title' => 'Nike Air Max',
            'slug' => 'nike-air-max-'.str()->lower(str()->random(6)),
            'product_type' => ProductType::Physical,
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Black / 42',
            'sku' => 'NK-AM-BLK-42',
            'barcode' => '8691234567890',
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
        $location = InventoryLocation::query()->create(['name' => 'Ana Depo', 'code' => 'MAIN']);
        $inventory = InventoryItem::query()->create(['product_variant_id' => $variant->id]);
        $level = InventoryLevel::query()->create([
            'inventory_item_id' => $inventory->id,
            'inventory_location_id' => $location->id,
            'available_quantity' => $available,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'order_number' => 'RV-S7-'.str()->upper(str()->random(6)),
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'TRY',
            'subtotal' => (string) (100 * $quantity).'.00',
            'grand_total' => (string) (100 * $quantity).'.00',
            'customer_email' => $customer->email,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'product_title' => $product->title,
            'variant_title' => $variant->title,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price' => '100.00',
            'line_total' => (string) (100 * $quantity).'.00',
        ]);
        $cart = Cart::query()->create(['token' => 'cart-'.str()->ulid(), 'currency' => 'TRY']);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'checkout-'.str()->ulid(),
            'email' => $customer->email,
            'currency' => 'TRY',
            'grand_total' => $order->grand_total,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'checkout_id' => $checkout->id,
            'provider' => 'manual',
            'provider_payment_id' => 'pay-'.str()->ulid(),
            'status' => PaymentStatus::Paid,
            'amount' => $order->grand_total,
            'currency' => 'TRY',
            'paid_at' => now(),
        ]);

        return [$order->load('items'), $variant, $payment, $level];
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithUser(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(8)),
        ]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => StoreUserRole::Owner,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$user, $store];
    }

    private function inStore(Store $store, callable $callback): mixed
    {
        $currentStore = app(CurrentStore::class);
        $currentStore->set($store);

        try {
            return $callback();
        } finally {
            $currentStore->clear();
        }
    }
}

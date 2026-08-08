<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Jobs\Notification\ProcessOrderNotification;
use Modules\Commerce\Mail\MerchantNewOrderMail;
use Modules\Commerce\Mail\OrderConfirmationMail;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class OrderNotificationOutboxTest extends TestCase
{
    use DatabaseTransactions;

    public function test_processor_sends_customer_confirmation_and_marks_outbox_record_sent(): void
    {
        [$store, $order] = $this->makeStoreAndOrder();
        $outbox = OrderNotificationOutbox::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'type' => 'customer_order_confirmation',
        ]);
        Mail::fake();

        (new ProcessOrderNotification($outbox->id))->handle(app(CurrentStore::class));

        Mail::assertSent(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail) => $mail->order->is($order));
        $this->assertSame('sent', $outbox->fresh()->status);
        $this->assertNotNull($outbox->fresh()->sent_at);
    }

    public function test_processor_sends_merchant_notification(): void
    {
        [$store, $order, $owner] = $this->makeStoreAndOrder();
        $outbox = OrderNotificationOutbox::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'type' => 'merchant_new_order',
        ]);
        Mail::fake();

        (new ProcessOrderNotification($outbox->id))->handle(app(CurrentStore::class));

        Mail::assertSent(MerchantNewOrderMail::class, fn (MerchantNewOrderMail $mail) => $mail->order->is($order));
        $this->assertSame('sent', $outbox->fresh()->status);
        $this->assertSame('owner@example.com', $owner->email);
    }

    /**
     * @return array{0: Store, 1: Order, 2: User}
     */
    private function makeStoreAndOrder(): array
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $merchant = Merchant::create(['owner_user_id' => $owner->id]);
        $store = $merchant->stores()->create(['name' => 'Notification Store', 'slug' => 'notification-store']);
        app(CurrentStore::class)->set($store);
        $order = Order::query()->create([
            'order_number' => 'RV-1001',
            'status' => OrderStatus::Open,
            'currency' => 'TRY',
            'grand_total' => '100.00',
            'customer_email' => 'customer@example.com',
            'placed_at' => now(),
        ]);
        app(CurrentStore::class)->clear();

        return [$store, $order, $owner];
    }
}
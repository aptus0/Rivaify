import { useEffect, useState, type FormEvent } from "react";
import {
    ArrowLeft,
    Ban,
    CreditCard,
    MapPin,
    Package,
    RotateCcw,
    UserRound,
    X,
} from "lucide-react";
import { Link, useParams } from "react-router-dom";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { Button } from "../../../components/ui/Button";
import { Card } from "../../../components/ui/Card";
import { ApiError } from "../../../lib/api";
import {
    cancelOrder,
    getOrder,
    refundOrderPayment,
    updateOrderFulfillment,
    type AdminOrderDetail,
    type FulfillmentStatus,
} from "../../commerce/api/adminCommerceApi";
import { formatDate, formatMoney } from "../../../utils/commerceFormat";
import {
    FulfillmentStatusBadge,
    PaymentStatusBadge,
} from "../components/OrderStatusBadge";

function addressLabel(
    address: AdminOrderDetail["addresses"][number] | undefined,
): string {
    if (!address) return "-";

    return [
        address.address_line_1,
        address.address_line_2,
        address.district,
        address.province,
        address.postal_code,
    ]
        .filter(Boolean)
        .join(", ");
}

export function OrderDetailPage() {
    usePageTitle("Sipariş Detayı");
    const { orderId } = useParams();
    const [order, setOrder] = useState<AdminOrderDetail | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [cancelling, setCancelling] = useState(false);
    const [updatingFulfillment, setUpdatingFulfillment] = useState(false);
    const [refundTarget, setRefundTarget] = useState<
        AdminOrderDetail["payments"][number] | null
    >(null);
    const [refundAmount, setRefundAmount] = useState("");
    const [refunding, setRefunding] = useState(false);
    async function changeFulfillment(status: FulfillmentStatus) {
        if (!order) return;
        setUpdatingFulfillment(true);
        setError(null);
        try {
            const response = await updateOrderFulfillment(order.id, status);
            setOrder(response.data);
        } catch {
            setError("Gönderim durumu güncellenemedi.");
        } finally {
            setUpdatingFulfillment(false);
        }
    }

    useEffect(() => {
        if (!orderId) return;
        let active = true;
        setLoading(true);
        void getOrder(orderId)
            .then((response) => {
                if (active) setOrder(response.data);
            })
            .catch((requestError: unknown) => {
                if (active)
                    setError(
                        requestError instanceof ApiError
                            ? "Sipariş yüklenemedi."
                            : "Beklenmeyen bir hata oluştu.",
                    );
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [orderId]);

    async function handleCancel() {
        if (!order) return;
        const warning = ["paid", "partially_paid"].includes(
            order.payment_status,
        )
            ? " Stok geri alınır; ödeme otomatik iade edilmez. Ödemeyi ayrıca iade etmelisiniz."
            : "";
        if (
            !window.confirm(
                `${order.number} numaralı sipariş iptal edilsin mi?${warning}`,
            )
        )
            return;
        setCancelling(true);
        setError(null);
        try {
            const response = await cancelOrder(order.id);
            setOrder(response.data);
        } catch (requestError) {
            setError(
                requestError instanceof ApiError
                    ? "Sipariş iptal edilemedi."
                    : "Beklenmeyen bir hata oluştu.",
            );
        } finally {
            setCancelling(false);
        }
    }

    async function submitRefund(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!order || !refundTarget) return;
        setRefunding(true);
        setError(null);
        try {
            const response = await refundOrderPayment(
                order.id,
                refundTarget.id,
                refundAmount,
            );
            setOrder(response.data);
            setRefundTarget(null);
            setRefundAmount("");
        } catch (requestError) {
            setError(
                requestError instanceof ApiError
                    ? "İade tamamlanamadı. Tutarı ve PayTR yapılandırmasını kontrol edin."
                    : "Beklenmeyen bir hata oluştu.",
            );
        } finally {
            setRefunding(false);
        }
    }

    if (loading)
        return <p className="text-sm text-muted">Sipariş yükleniyor...</p>;
    if (!order)
        return (
            <p className="text-sm text-red-600">
                {error || "Sipariş bulunamadı."}
            </p>
        );

    const shippingAddress = order.addresses.find(
        (address) => address.type === "shipping",
    );
    const billingAddress = order.addresses.find(
        (address) => address.type === "billing",
    );

    return (
        <div className="mx-auto max-w-6xl space-y-5">
            <Link
                to="/orders"
                className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"
            >
                <ArrowLeft size={16} /> Siparişler
            </Link>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-xl font-semibold text-dark">
                            {order.number}
                        </h2>
                        <PaymentStatusBadge status={order.payment_status} />
                        <FulfillmentStatusBadge
                            status={order.fulfillment_status}
                        />
                    </div>
                    <p className="mt-1 text-sm text-muted">
                        {formatDate(order.placed_at)}
                    </p>
                </div>
                {order.status !== "cancelled" && (
                    <div className="flex flex-wrap gap-2">
                        <select
                            disabled={updatingFulfillment}
                            value={order.fulfillment_status}
                            onChange={(e) =>
                                void changeFulfillment(
                                    e.target.value as FulfillmentStatus,
                                )
                            }
                            className="rounded-md border border-border bg-card px-3 py-2 text-sm font-medium text-dark"
                        >
                            <option value="unfulfilled">Hazırlanacak</option>
                            <option value="partial">Kısmi gönderildi</option>
                            <option value="fulfilled">Gönderildi</option>
                            <option value="returned">İade edildi</option>
                        </select>
                        <Button
                            fullWidth={false}
                            variant="secondary"
                            onClick={handleCancel}
                            disabled={cancelling}
                        >
                            <Ban size={16} />{" "}
                            {cancelling
                                ? "İptal ediliyor..."
                                : "Siparişi İptal Et"}
                        </Button>
                    </div>
                )}
            </div>

            {error && <p className="text-sm text-red-600">{error}</p>}

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.85fr)]">
                <div className="space-y-5">
                    <Card>
                        <div className="mb-4 flex items-center gap-2">
                            <Package size={18} className="text-muted" />
                            <h3 className="font-medium text-dark">
                                {order.items.length} ürün
                            </h3>
                        </div>
                        <div className="divide-y divide-border">
                            {order.items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-start justify-between gap-4 py-4 first:pt-0 last:pb-0"
                                >
                                    <div>
                                        <p className="font-medium text-dark">
                                            {item.product_title}
                                        </p>
                                        {item.variant_title && (
                                            <p className="text-sm text-muted">
                                                {item.variant_title}
                                            </p>
                                        )}
                                        <p className="mt-1 text-sm text-muted">
                                            {item.quantity} ×{" "}
                                            {formatMoney(
                                                item.unit_price,
                                                order.currency,
                                            )}
                                        </p>
                                    </div>
                                    <p className="shrink-0 font-medium text-dark">
                                        {formatMoney(
                                            item.line_total,
                                            order.currency,
                                        )}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-medium text-dark">
                            Sipariş zaman çizelgesi
                        </h3>
                        <ol className="space-y-4 border-l border-border pl-4">
                            {order.timeline.map((event) => (
                                <li key={event.id} className="relative text-sm">
                                    <span className="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-primary" />
                                    <p className="font-medium text-dark">
                                        {event.message}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted">
                                        {formatDate(event.created_at)}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card>
                        <div className="mb-3 flex items-center gap-2">
                            <UserRound size={18} className="text-muted" />
                            <h3 className="font-medium text-dark">Müşteri</h3>
                        </div>
                        <p className="font-medium text-dark">
                            {order.customer.name || "Misafir müşteri"}
                        </p>
                        <p className="mt-1 text-sm text-muted">
                            {order.customer.email}
                        </p>
                        {order.customer_phone && (
                            <p className="mt-1 text-sm text-muted">
                                {order.customer_phone}
                            </p>
                        )}
                    </Card>

                    <Card>
                        <div className="mb-3 flex items-center gap-2">
                            <CreditCard size={18} className="text-muted" />
                            <h3 className="font-medium text-dark">Ödeme</h3>
                        </div>
                        {order.payments.length === 0 ? (
                            <p className="text-sm text-muted">
                                Henüz ödeme kaydı yok.
                            </p>
                        ) : (
                            order.payments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="border-b border-border py-3 first:pt-0 last:border-0 last:pb-0"
                                >
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <div>
                                            <p className="font-medium capitalize text-dark">
                                                {payment.provider}
                                            </p>
                                            <p className="text-muted">
                                                {payment.payment_method_type ||
                                                    "Ödeme yöntemi"}{" "}
                                                ·{" "}
                                                {formatMoney(
                                                    payment.amount,
                                                    payment.currency,
                                                )}
                                            </p>
                                            {Number(payment.refunded_amount) >
                                                0 && (
                                                <p className="mt-1 text-xs text-muted">
                                                    İade edilen:{" "}
                                                    {formatMoney(
                                                        payment.refunded_amount,
                                                        payment.currency,
                                                    )}{" "}
                                                    · Kalan:{" "}
                                                    {formatMoney(
                                                        payment.refundable_amount,
                                                        payment.currency,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <PaymentStatusBadge
                                            status={payment.status}
                                        />
                                    </div>
                                    {["paid", "partially_refunded"].includes(
                                        payment.status,
                                    ) &&
                                        Number(payment.refundable_amount) >
                                            0 && (
                                            <button
                                                onClick={() => {
                                                    setRefundTarget(payment);
                                                    setRefundAmount(
                                                        payment.refundable_amount,
                                                    );
                                                }}
                                                className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-primary-hover"
                                            >
                                                <RotateCcw size={13} />
                                                Ödeme iadesi oluştur
                                            </button>
                                        )}
                                </div>
                            ))
                        )}
                    </Card>

                    <Card>
                        <div className="mb-3 flex items-center gap-2">
                            <MapPin size={18} className="text-muted" />
                            <h3 className="font-medium text-dark">
                                Teslimat adresi
                            </h3>
                        </div>
                        <p className="text-sm font-medium text-dark">
                            {shippingAddress
                                ? `${shippingAddress.first_name} ${shippingAddress.last_name}`
                                : "-"}
                        </p>
                        <p className="mt-1 text-sm text-muted">
                            {addressLabel(shippingAddress)}
                        </p>
                        <p className="mt-4 text-xs font-semibold uppercase tracking-wide text-muted">
                            Fatura adresi
                        </p>
                        <p className="mt-1 text-sm text-muted">
                            {addressLabel(billingAddress)}
                        </p>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-medium text-dark">
                            Finansal özet
                        </h3>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between text-muted">
                                <dt>Ara toplam</dt>
                                <dd>
                                    {formatMoney(
                                        order.subtotal,
                                        order.currency,
                                    )}
                                </dd>
                            </div>
                            <div className="flex justify-between text-muted">
                                <dt>İndirim</dt>
                                <dd>
                                    -
                                    {formatMoney(
                                        order.discount_total,
                                        order.currency,
                                    )}
                                </dd>
                            </div>
                            <div className="flex justify-between text-muted">
                                <dt>Kargo</dt>
                                <dd>
                                    {formatMoney(
                                        order.shipping_total,
                                        order.currency,
                                    )}
                                </dd>
                            </div>
                            <div className="flex justify-between text-muted">
                                <dt>Vergi</dt>
                                <dd>
                                    {formatMoney(
                                        order.tax_total,
                                        order.currency,
                                    )}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-3 font-semibold text-dark">
                                <dt>Toplam</dt>
                                <dd>
                                    {formatMoney(
                                        order.grand_total,
                                        order.currency,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </Card>
                </div>
            </div>
            {refundTarget && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-dark/40 p-4">
                    <form
                        onSubmit={submitRefund}
                        className="w-full max-w-md rounded-2xl bg-card p-6 shadow-spectrum"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h3 className="font-semibold text-dark">
                                    PayTR ödeme iadesi
                                </h3>
                                <p className="mt-1 text-sm text-muted">
                                    İade talebi doğrudan ödeme sağlayıcısına
                                    gönderilir.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRefundTarget(null)}
                                aria-label="Kapat"
                            >
                                <X size={19} />
                            </button>
                        </div>
                        <label className="mt-5 block text-sm font-medium text-dark">
                            İade tutarı ({refundTarget.currency})
                            <input
                                autoFocus
                                required
                                type="number"
                                min="0.01"
                                max={refundTarget.refundable_amount}
                                step="0.01"
                                value={refundAmount}
                                onChange={(event) =>
                                    setRefundAmount(event.target.value)
                                }
                                className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 outline-none focus:border-primary"
                            />
                        </label>
                        <p className="mt-2 text-xs leading-5 text-muted">
                            En fazla{" "}
                            {formatMoney(
                                refundTarget.refundable_amount,
                                refundTarget.currency,
                            )}{" "}
                            iade edebilirsiniz. İşlem geri alınamaz; göndermeden
                            önce tutarı kontrol edin.
                        </p>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                type="button"
                                fullWidth={false}
                                variant="secondary"
                                onClick={() => setRefundTarget(null)}
                                disabled={refunding}
                            >
                                Vazgeç
                            </Button>
                            <Button fullWidth={false} disabled={refunding}>
                                {refunding
                                    ? "İade gönderiliyor…"
                                    : "İadeyi onayla"}
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}

import { useEffect, useState, type FormEvent } from "react";
import {
    ChevronLeft,
    ChevronRight,
    Plus,
    Search,
    Trash2,
    X,
} from "lucide-react";
import { Link, useSearchParams } from "react-router-dom";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { Button } from "../../../components/ui/Button";
import { Card } from "../../../components/ui/Card";
import { EmptyState } from "../../../components/ui/EmptyState";
import { ApiError } from "../../../lib/api";
import {
    listOrders,
    createManualOrder,
    getManualOrderOptions,
    type AdminOrderSummary,
    type FulfillmentStatus,
    type PaginationMeta,
    type PaymentStatus,
    type ManualOrderOptions,
} from "../../commerce/api/adminCommerceApi";
import { formatDate, formatMoney } from "../../../utils/commerceFormat";
import {
    FulfillmentStatusBadge,
    PaymentStatusBadge,
} from "../components/OrderStatusBadge";

const EMPTY_META: PaginationMeta = {
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
};

function messageFor(error: unknown): string {
    return error instanceof ApiError
        ? "Siparişler yüklenemedi."
        : "Beklenmeyen bir hata oluştu.";
}

export function OrdersPage() {
    usePageTitle("Siparişler");
    const [searchParams, setSearchParams] = useSearchParams();
    const [draftQuery, setDraftQuery] = useState(searchParams.get("q") ?? "");
    const [query, setQuery] = useState(searchParams.get("q") ?? "");
    const [paymentStatus, setPaymentStatus] = useState(
        searchParams.get("payment_status") ?? "",
    );
    const [fulfillmentStatus, setFulfillmentStatus] = useState(
        searchParams.get("fulfillment_status") ?? "",
    );
    const [orders, setOrders] = useState<AdminOrderSummary[]>([]);
    const [meta, setMeta] = useState<PaginationMeta>(EMPTY_META);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [options, setOptions] = useState<ManualOrderOptions | null>(null);
    const [creating, setCreating] = useState(false);
    const [manual, setManual] = useState({
        customer_id: "",
        shipping_total: "0.00",
        notes: "",
        items: [{ variant_id: "", quantity: 1 }],
    });

    async function openCreate() {
        setCreateOpen(true);
        if (!options) {
            try {
                const response = await getManualOrderOptions();
                setOptions(response.data);
            } catch {
                setError("Sipariş seçenekleri yüklenemedi.");
            }
        }
    }
    async function submitManual(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setCreating(true);
        setError(null);
        try {
            const response = await createManualOrder({
                customer_id: manual.customer_id || null,
                items: manual.items,
                shipping_total: manual.shipping_total,
                notes: manual.notes || null,
            });
            setCreateOpen(false);
            setManual({
                customer_id: "",
                shipping_total: "0.00",
                notes: "",
                items: [{ variant_id: "", quantity: 1 }],
            });
            window.location.assign(`/orders/${response.data.id}`);
        } catch {
            setError(
                "Manuel sipariş oluşturulamadı. Ürün ve miktarları kontrol edin.",
            );
        } finally {
            setCreating(false);
        }
    }

    useEffect(() => {
        if (searchParams.get("create") === "1") void openCreate();
        // The create query is an entry command; it only needs to be consumed once.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);

        void listOrders({
            q: query || undefined,
            payment_status: paymentStatus || undefined,
            fulfillment_status: fulfillmentStatus || undefined,
            page: String(meta.current_page),
        })
            .then((response) => {
                if (!active) return;
                setOrders(response.data);
                setMeta(response.meta);
            })
            .catch((requestError: unknown) => {
                if (active) setError(messageFor(requestError));
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [fulfillmentStatus, meta.current_page, paymentStatus, query]);

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setMeta((current) => ({ ...current, current_page: 1 }));
        setQuery(draftQuery.trim());
        setSearchParams((current) => {
            const next = new URLSearchParams(current);
            draftQuery.trim()
                ? next.set("q", draftQuery.trim())
                : next.delete("q");
            return next;
        });
    }

    function changePage(page: number) {
        setMeta((current) => ({ ...current, current_page: page }));
    }

    return (
        <div className="mx-auto max-w-6xl space-y-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-dark">
                        Siparişler
                    </h2>
                    <p className="text-sm text-muted">{meta.total} kayıt</p>
                </div>
                <Button fullWidth={false} onClick={() => void openCreate()}>
                    <Plus size={16} />
                    Sipariş Oluştur
                </Button>
            </div>

            <Card className="p-0">
                <form
                    onSubmit={submitSearch}
                    className="grid gap-3 border-b border-border p-4 lg:grid-cols-[minmax(0,1fr)_10rem_11rem_auto]"
                >
                    <label className="relative">
                        <span className="sr-only">
                            Sipariş, müşteri veya e-posta ara
                        </span>
                        <Search
                            size={17}
                            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"
                        />
                        <input
                            value={draftQuery}
                            onChange={(event) =>
                                setDraftQuery(event.target.value)
                            }
                            placeholder="Sipariş, müşteri veya e-posta ara..."
                            className="w-full rounded-md border border-border bg-card py-2 pl-9 pr-3 text-sm text-dark outline-none focus:border-primary"
                        />
                    </label>
                    <select
                        value={paymentStatus}
                        onChange={(event) => {
                            setMeta((current) => ({
                                ...current,
                                current_page: 1,
                            }));
                            setPaymentStatus(event.target.value);
                            setSearchParams((current) => {
                                const next = new URLSearchParams(current);
                                event.target.value
                                    ? next.set(
                                          "payment_status",
                                          event.target.value,
                                      )
                                    : next.delete("payment_status");
                                return next;
                            });
                        }}
                        aria-label="Ödeme durumu"
                        className="rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
                    >
                        <option value="">Tüm ödemeler</option>
                        <option value="paid">Ödendi</option>
                        <option value="pending">Bekliyor</option>
                        <option value="failed">Başarısız</option>
                        <option value="refunded">İade edildi</option>
                    </select>
                    <select
                        value={fulfillmentStatus}
                        onChange={(event) => {
                            setMeta((current) => ({
                                ...current,
                                current_page: 1,
                            }));
                            setFulfillmentStatus(event.target.value);
                            setSearchParams((current) => {
                                const next = new URLSearchParams(current);
                                event.target.value
                                    ? next.set(
                                          "fulfillment_status",
                                          event.target.value,
                                      )
                                    : next.delete("fulfillment_status");
                                return next;
                            });
                        }}
                        aria-label="Teslimat durumu"
                        className="rounded-md border border-border bg-card px-3 py-2 text-sm text-dark outline-none focus:border-primary"
                    >
                        <option value="">Tüm teslimatlar</option>
                        <option value="unfulfilled">Hazırlanıyor</option>
                        <option value="partial">Kısmi gönderildi</option>
                        <option value="fulfilled">Gönderildi</option>
                        <option value="returned">İade edildi</option>
                    </select>
                    <Button fullWidth={false} type="submit">
                        Ara
                    </Button>
                </form>

                {error && (
                    <p className="border-b border-border px-4 py-3 text-sm text-red-600">
                        {error}
                    </p>
                )}
                {loading ? (
                    <div className="p-8 text-sm text-muted">
                        Siparişler yükleniyor...
                    </div>
                ) : orders.length === 0 ? (
                    <EmptyState
                        icon={Search}
                        title="Sipariş bulunamadı"
                        description="Filtrelerini değiştirerek tekrar dene."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-[760px] w-full text-left text-sm">
                            <thead className="border-b border-border bg-app-bg text-xs font-semibold uppercase tracking-wide text-muted">
                                <tr>
                                    <th className="px-4 py-3">Sipariş</th>
                                    <th className="px-4 py-3">Tarih</th>
                                    <th className="px-4 py-3">Müşteri</th>
                                    <th className="px-4 py-3 text-right">
                                        Toplam
                                    </th>
                                    <th className="px-4 py-3">Ödeme</th>
                                    <th className="px-4 py-3">Teslimat</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orders.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="hover:bg-app-bg/70"
                                    >
                                        <td className="px-4 py-3 font-medium text-dark">
                                            <Link
                                                to={`/orders/${order.id}`}
                                                className="hover:text-primary-hover"
                                            >
                                                {order.number}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-muted">
                                            {formatDate(order.placed_at, {
                                                year: undefined,
                                            })}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-dark">
                                                {order.customer.name ||
                                                    "Misafir müşteri"}
                                            </p>
                                            <p className="text-xs text-muted">
                                                {order.customer.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium text-dark">
                                            {formatMoney(
                                                order.grand_total,
                                                order.currency,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <PaymentStatusBadge
                                                status={
                                                    order.payment_status as PaymentStatus
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <FulfillmentStatusBadge
                                                status={
                                                    order.fulfillment_status as FulfillmentStatus
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-4 py-3">
                        <p className="text-sm text-muted">
                            Sayfa {meta.current_page} / {meta.last_page}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                fullWidth={false}
                                variant="secondary"
                                disabled={meta.current_page === 1}
                                onClick={() =>
                                    changePage(meta.current_page - 1)
                                }
                                aria-label="Önceki sayfa"
                            >
                                <ChevronLeft size={16} />
                            </Button>
                            <Button
                                fullWidth={false}
                                variant="secondary"
                                disabled={meta.current_page === meta.last_page}
                                onClick={() =>
                                    changePage(meta.current_page + 1)
                                }
                                aria-label="Sonraki sayfa"
                            >
                                <ChevronRight size={16} />
                            </Button>
                        </div>
                    </div>
                )}
            </Card>
            {createOpen && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-dark/40 p-4">
                    <form
                        onSubmit={submitManual}
                        className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-card p-6 shadow-spectrum"
                    >
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    Manuel sipariş oluştur
                                </h3>
                                <p className="text-sm text-muted">
                                    Fiyatlar güvenli biçimde ürün kataloğundan
                                    alınır.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCreateOpen(false)}
                            >
                                <X size={20} />
                            </button>
                        </div>
                        <div className="mt-6 space-y-5">
                            <label className="block text-sm font-medium">
                                Müşteri
                                <select
                                    value={manual.customer_id}
                                    onChange={(e) =>
                                        setManual({
                                            ...manual,
                                            customer_id: e.target.value,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                >
                                    <option value="">Misafir müşteri</option>
                                    {options?.customers.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} · {c.email}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <p className="text-sm font-semibold">
                                        Ürünler
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setManual({
                                                ...manual,
                                                items: [
                                                    ...manual.items,
                                                    {
                                                        variant_id: "",
                                                        quantity: 1,
                                                    },
                                                ],
                                            })
                                        }
                                        className="text-xs font-semibold text-primary-hover"
                                    >
                                        + Satır ekle
                                    </button>
                                </div>
                                <div className="space-y-2">
                                    {manual.items.map((item, index) => (
                                        <div
                                            key={index}
                                            className="grid grid-cols-[minmax(0,1fr)_6rem_auto] gap-2"
                                        >
                                            <select
                                                required
                                                value={item.variant_id}
                                                onChange={(e) =>
                                                    setManual({
                                                        ...manual,
                                                        items: manual.items.map(
                                                            (row, i) =>
                                                                i === index
                                                                    ? {
                                                                          ...row,
                                                                          variant_id:
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    })
                                                }
                                                className="rounded-md border border-border px-3 py-2.5 text-sm"
                                            >
                                                <option value="">
                                                    Ürün varyantı seç
                                                </option>
                                                {options?.variants.map((v) => (
                                                    <option
                                                        key={v.id}
                                                        value={v.id}
                                                    >
                                                        {v.title} ·{" "}
                                                        {v.variant_title} ·{" "}
                                                        {formatMoney(
                                                            v.price,
                                                            options.currency,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                            <input
                                                required
                                                type="number"
                                                min="1"
                                                max="1000"
                                                value={item.quantity}
                                                onChange={(e) =>
                                                    setManual({
                                                        ...manual,
                                                        items: manual.items.map(
                                                            (row, i) =>
                                                                i === index
                                                                    ? {
                                                                          ...row,
                                                                          quantity:
                                                                              Number(
                                                                                  e
                                                                                      .target
                                                                                      .value,
                                                                              ),
                                                                      }
                                                                    : row,
                                                        ),
                                                    })
                                                }
                                                className="rounded-md border border-border px-3 py-2.5 text-sm"
                                                aria-label="Miktar"
                                            />
                                            <button
                                                type="button"
                                                disabled={
                                                    manual.items.length === 1
                                                }
                                                onClick={() =>
                                                    setManual({
                                                        ...manual,
                                                        items: manual.items.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    })
                                                }
                                                className="rounded-md p-2 text-red-600 disabled:opacity-30"
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="text-sm font-medium">
                                    Kargo tutarı ({options?.currency ?? "TRY"})
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={manual.shipping_total}
                                        onChange={(e) =>
                                            setManual({
                                                ...manual,
                                                shipping_total: e.target.value,
                                            })
                                        }
                                        className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                    />
                                </label>
                                <label className="text-sm font-medium sm:col-span-2">
                                    Sipariş notu
                                    <textarea
                                        rows={3}
                                        value={manual.notes}
                                        onChange={(e) =>
                                            setManual({
                                                ...manual,
                                                notes: e.target.value,
                                            })
                                        }
                                        className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                    />
                                </label>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                type="button"
                                fullWidth={false}
                                variant="secondary"
                                onClick={() => setCreateOpen(false)}
                            >
                                Vazgeç
                            </Button>
                            <Button
                                disabled={creating || !options}
                                fullWidth={false}
                            >
                                {creating
                                    ? "Oluşturuluyor..."
                                    : "Siparişi oluştur"}
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}

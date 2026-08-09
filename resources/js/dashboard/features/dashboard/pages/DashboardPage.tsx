import { useEffect, useMemo, useState } from "react";
import {
    AlertTriangle,
    ArrowRight,
    Box,
    CalendarDays,
    ChevronDown,
    CreditCard,
    ExternalLink,
    Package,
    Percent,
    RefreshCw,
    Settings2,
    ShoppingBag,
    ShoppingCart,
    TrendingUp,
    Users,
    Wallet,
} from "lucide-react";
import { Link } from "react-router-dom";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { useAuth } from "../../../app/providers/AuthProvider";
import { Card } from "../../../components/ui/Card";
import { EmptyState } from "../../../components/ui/EmptyState";
import {
    getDashboardMetrics,
    type DashboardMetrics,
} from "../../commerce/api/adminCommerceApi";
import { formatMoney } from "../../../utils/commerceFormat";

const PERIODS = [
    { value: "today", label: "Bugün" },
    { value: "7d", label: "Son 7 gün" },
    { value: "30d", label: "Son 30 gün" },
] as const;

function Stat({
    label,
    value,
    icon: Icon,
    loading,
    note,
}: {
    label: string;
    value: string;
    icon: typeof Wallet;
    loading: boolean;
    note: string;
}) {
    return (
        <Card className="relative overflow-hidden">
            <div className="absolute right-0 top-0 h-20 w-20 rounded-bl-full bg-surface-orange/70" />
            <div className="relative flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-muted">{label}</p>
                    {loading ? (
                        <div className="mt-3 h-8 w-24 animate-pulse rounded bg-border" />
                    ) : (
                        <p className="mt-2 text-2xl font-semibold tracking-tight text-dark">
                            {value}
                        </p>
                    )}
                    <p className="mt-2 text-xs text-muted">{note}</p>
                </div>
                <span className="rounded-xl bg-white p-2.5 text-primary shadow-sm">
                    <Icon size={19} />
                </span>
            </div>
        </Card>
    );
}

function changeNote(change: number | null | undefined): string {
    if (change === null) return "Önceki dönemde karşılaştırılabilir veri yok";
    if (change === undefined) return "Önceki dönemle karşılaştırılıyor";
    if (change === 0) return "Önceki dönemle aynı";

    return `Önceki döneme göre %${Math.abs(change).toLocaleString("tr-TR")} ${change > 0 ? "artış" : "azalış"}`;
}

function SalesChart({
    data,
    currency,
}: {
    data: DashboardMetrics["sales_series"];
    currency: string;
}) {
    if (!data.length)
        return (
            <EmptyState
                icon={TrendingUp}
                title="Henüz satış veriniz yok"
                description="İlk ödemeniz tamamlandığında satış eğriniz burada görünmeye başlayacak."
            />
        );
    const values = data.map((point) => Number(point.sales));
    const max = Math.max(...values, 0);
    const min = Math.min(...values, 0);
    const span = Math.max(max - min, 1);
    const zeroY = 92 - ((0 - min) / span) * 78;
    const points = data
        .map(
            (point, index) =>
                `${(index / Math.max(data.length - 1, 1)) * 100},${92 - ((Number(point.sales) - min) / span) * 78}`,
        )
        .join(" ");
    return (
        <div>
            <div className="h-64 rounded-xl bg-gradient-to-b from-surface-orange/50 to-white p-4">
                <svg
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    className="h-full w-full overflow-visible"
                >
                    <defs>
                        <linearGradient
                            id="sales-fill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0"
                                stopColor="#ff6b00"
                                stopOpacity=".24"
                            />
                            <stop
                                offset="1"
                                stopColor="#ff6b00"
                                stopOpacity="0"
                            />
                        </linearGradient>
                    </defs>
                    <polygon
                        points={`0,${zeroY} ${points} 100,${zeroY}`}
                        fill="url(#sales-fill)"
                    />
                    <polyline
                        points={points}
                        fill="none"
                        stroke="#ff6b00"
                        strokeWidth="2"
                        vectorEffect="non-scaling-stroke"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </div>
            <div className="mt-3 flex justify-between text-xs text-muted">
                <span>{data[0]?.date}</span>
                <span>En yüksek {formatMoney(String(max), currency)}</span>
                <span>{data[data.length - 1]?.date}</span>
            </div>
        </div>
    );
}

export function DashboardPage() {
    usePageTitle("Ana Sayfa");
    const { user, store } = useAuth();
    const [range, setRange] = useState<DashboardMetrics["range"]>("7d");
    const [data, setData] = useState<DashboardMetrics | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const load = () => {
        setLoading(true);
        setError(false);
        void getDashboardMetrics(range)
            .then((r) => setData(r.data))
            .catch(() => setError(true))
            .finally(() => setLoading(false));
    };
    useEffect(load, [range]);
    const date = useMemo(
        () =>
            new Intl.DateTimeFormat("tr-TR", { dateStyle: "full" }).format(
                new Date(),
            ),
        [],
    );
    if (!user || !store) return null;
    const currency = data?.currency ?? "TRY";
    const actionCount =
        (data?.order_status.unfulfilled ?? 0) +
        (data?.order_status.payment_pending ?? 0) +
        (data?.order_status.failed_payments ?? 0) +
        (data?.inventory.low ?? 0) +
        (data?.inventory.out ?? 0);
    return (
        <div className="mx-auto max-w-[1440px] space-y-6">
            <section className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p className="text-sm text-muted">{date}</p>
                    <h2 className="mt-1 text-2xl font-semibold text-dark">
                        İyi günler, {user.name.split(" ")[0]}
                    </h2>
                    <p className="mt-1 text-sm text-muted">
                        <span className="font-medium text-dark">
                            {store.name}
                        </span>{" "}
                        · Mağazanda bugün neler oluyor?
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {store.status === "active" ? (
                        <a
                            href={`https://${store.slug}.rivaify.com`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-sm font-medium text-dark hover:bg-app-bg"
                        >
                            Mağazayı gör <ExternalLink size={15} />
                        </a>
                    ) : (
                        <Link
                            to="/settings#store"
                            className="inline-flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-sm font-medium text-dark hover:bg-app-bg"
                        >
                            Yayın ayarlarını aç <Settings2 size={15} />
                        </Link>
                    )}
                    <details className="relative">
                        <summary className="cursor-pointer list-none rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">
                            + Hızlı işlem
                        </summary>
                        <div className="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-border bg-card p-2 shadow-spectrum">
                            {[
                                [Package, "Ürün ekle", "/products/create"],
                                [
                                    ShoppingCart,
                                    "Sipariş oluştur",
                                    "/orders?create=1",
                                ],
                                [Users, "Müşteri ekle", "/customers?create=1"],
                                [
                                    Percent,
                                    "İndirim oluştur",
                                    "/discounts?create=1",
                                ],
                                [Box, "Stok güncelle", "/inventory"],
                                [Settings2, "Mağaza ayarları", "/settings"],
                            ].map(([Icon, label, path]) => (
                                <Link
                                    key={label as string}
                                    to={path as string}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-dark hover:bg-app-bg"
                                >
                                    <Icon size={16} className="text-primary" />
                                    {label as string}
                                </Link>
                            ))}
                        </div>
                    </details>
                    <label className="relative">
                        <CalendarDays
                            size={15}
                            className="absolute left-3 top-1/2 -translate-y-1/2 text-muted"
                        />
                        <select
                            value={range}
                            onChange={(e) =>
                                setRange(
                                    e.target.value as DashboardMetrics["range"],
                                )
                            }
                            className="appearance-none rounded-md border border-border bg-card py-2 pl-9 pr-9 text-sm font-medium text-dark outline-none"
                        >
                            <>
                                {PERIODS.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.label}
                                    </option>
                                ))}
                            </>
                        </select>
                        <ChevronDown
                            size={14}
                            className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted"
                        />
                    </label>
                </div>
            </section>
            {error && (
                <div className="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <span>Dashboard verileri geçici olarak alınamadı.</span>
                    <button
                        onClick={load}
                        className="flex items-center gap-2 font-semibold"
                    >
                        <RefreshCw size={15} />
                        Tekrar dene
                    </button>
                </div>
            )}
            <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <Stat
                    label="Net satış"
                    value={formatMoney(data?.sales ?? "0", currency)}
                    icon={Wallet}
                    loading={loading}
                    note={changeNote(data?.changes.sales)}
                />
                <Stat
                    label="Sipariş"
                    value={String(data?.orders ?? 0)}
                    icon={ShoppingCart}
                    loading={loading}
                    note={changeNote(data?.changes.orders)}
                />
                <Stat
                    label="Ortalama sipariş"
                    value={formatMoney(data?.average_order ?? "0", currency)}
                    icon={ShoppingBag}
                    loading={loading}
                    note={changeNote(data?.changes.average_order)}
                />
                <Stat
                    label="Yeni müşteri"
                    value={String(data?.customers ?? 0)}
                    icon={Users}
                    loading={loading}
                    note={changeNote(data?.changes.customers)}
                />
            </section>
            <section className="grid gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-8">
                    <div className="mb-5 flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Satış performansı
                            </h3>
                            <p className="text-sm text-muted">
                                Ödenmiş siparişlerden net satış
                            </p>
                        </div>
                        <Link
                            to="/analytics"
                            className="text-sm font-semibold text-primary-hover"
                        >
                            Detaylı analiz{" "}
                            <ArrowRight size={14} className="inline" />
                        </Link>
                    </div>
                    {loading ? (
                        <div className="h-64 animate-pulse rounded-xl bg-app-bg" />
                    ) : (
                        <SalesChart
                            data={data?.sales_series ?? []}
                            currency={currency}
                        />
                    )}
                </Card>
                <Card className="xl:col-span-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Online mağaza
                            </h3>
                            <p className="text-sm text-muted">
                                Mağaza ve domain durumu
                            </p>
                        </div>
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${store.status === "active" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"}`}
                        >
                            {store.status === "active"
                                ? "● Yayında"
                                : "● Yapılandırılıyor"}
                        </span>
                    </div>
                    <div className="my-5 rounded-xl bg-gradient-to-br from-dark to-soft-dark p-5 text-white">
                        <p className="text-xs text-white/60">
                            Rivaify Online Store
                        </p>
                        <p className="mt-6 text-lg font-semibold">
                            {store.name}
                        </p>
                        <p className="mt-1 text-sm text-white/60">
                            {store.slug}.rivaify.com
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <div className="rounded-lg bg-app-bg p-3">
                            <p className="text-xs text-muted">SSL</p>
                            <p className="mt-1 font-semibold text-emerald-700">
                                Platform SSL
                            </p>
                        </div>
                        <div className="rounded-lg bg-app-bg p-3">
                            <p className="text-xs text-muted">Durum</p>
                            <p className="mt-1 font-semibold text-dark">
                                {store.status === "active" ? "Aktif" : "Taslak"}
                            </p>
                        </div>
                    </div>
                </Card>
            </section>
            <section className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Dikkatinizi gerektirenler
                            </h3>
                            <p className="text-sm text-muted">
                                Öncelikli operasyon görevleri
                            </p>
                        </div>
                        <span className="rounded-full bg-surface-orange px-2.5 py-1 text-xs font-bold text-primary-hover">
                            {actionCount}
                        </span>
                    </div>
                    <div className="divide-y divide-border">
                        {[
                            [
                                `${data?.order_status.unfulfilled ?? 0} sipariş hazırlanmayı bekliyor`,
                                "/orders?fulfillment_status=unfulfilled",
                                ShoppingCart,
                            ],
                            [
                                `${data?.inventory.low ?? 0} ürünün stoğu azaldı`,
                                "/inventory",
                                AlertTriangle,
                            ],
                            [
                                `${data?.order_status.payment_pending ?? 0} ödeme bekliyor`,
                                "/orders?payment_status=pending",
                                Wallet,
                            ],
                            [
                                `${data?.order_status.failed_payments ?? 0} başarısız ödeme var`,
                                "/orders?payment_status=failed",
                                CreditCard,
                            ],
                        ].map(([text, href, Icon]) => (
                            <Link
                                key={text as string}
                                to={href as string}
                                className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="rounded-lg bg-app-bg p-2 text-primary">
                                    <Icon size={17} />
                                </span>
                                <span className="flex-1 text-sm font-medium text-dark">
                                    {text as string}
                                </span>
                                <ArrowRight size={16} className="text-muted" />
                            </Link>
                        ))}
                    </div>
                </Card>
                <Card>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Envanter durumu
                            </h3>
                            <p className="text-sm text-muted">
                                Satılabilir ürün görünümü
                            </p>
                        </div>
                        <Box size={20} className="text-primary" />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        {[
                            [
                                "Stokta",
                                data?.inventory.available ?? 0,
                                "text-dark",
                            ],
                            [
                                "Az stok",
                                data?.inventory.low ?? 0,
                                "text-amber-700",
                            ],
                            [
                                "Stokta yok",
                                data?.inventory.out ?? 0,
                                "text-red-700",
                            ],
                        ].map(([label, value, color]) => (
                            <div
                                key={label as string}
                                className="rounded-xl bg-app-bg p-4"
                            >
                                <p className="text-xs text-muted">
                                    {label as string}
                                </p>
                                <p
                                    className={`mt-2 text-2xl font-semibold ${color}`}
                                >
                                    {value}
                                </p>
                            </div>
                        ))}
                    </div>
                    <Link
                        to="/inventory"
                        className="mt-4 flex items-center justify-center gap-2 rounded-md border border-border py-2 text-sm font-semibold text-dark hover:bg-app-bg"
                    >
                        Envanteri gör <ArrowRight size={15} />
                    </Link>
                </Card>
            </section>
            <section className="grid gap-4 xl:grid-cols-12">
                <Card className="p-0 xl:col-span-8">
                    <div className="flex items-center justify-between border-b border-border p-5">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Son siparişler
                            </h3>
                            <p className="text-sm text-muted">
                                En yeni müşteri siparişleri
                            </p>
                        </div>
                        <Link
                            to="/orders"
                            className="text-sm font-semibold text-primary-hover"
                        >
                            Tüm siparişler{" "}
                            <ArrowRight size={14} className="inline" />
                        </Link>
                    </div>
                    {!data?.recent_orders.length ? (
                        <EmptyState
                            icon={ShoppingCart}
                            title="Henüz sipariş yok"
                            description="İlk sipariş geldiğinde burada görünecek."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[650px] text-sm">
                                <thead className="bg-app-bg text-left text-xs uppercase text-muted">
                                    <tr>
                                        <th className="px-5 py-3">Sipariş</th>
                                        <th className="px-5 py-3">Müşteri</th>
                                        <th className="px-5 py-3">Ödeme</th>
                                        <th className="px-5 py-3 text-right">
                                            Tutar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.recent_orders.slice(0, 6).map((o) => (
                                        <tr key={o.id}>
                                            <td className="px-5 py-3 font-semibold">
                                                <Link to={`/orders/${o.id}`}>
                                                    {o.number}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3 text-muted">
                                                {o.customer.name ||
                                                    o.customer.email ||
                                                    "Misafir"}
                                            </td>
                                            <td className="px-5 py-3">
                                                <span
                                                    className={`rounded-full px-2 py-1 text-xs font-semibold ${o.payment_status === "paid" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"}`}
                                                >
                                                    {o.payment_status === "paid"
                                                        ? "Ödendi"
                                                        : "Bekliyor"}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-right font-semibold">
                                                {formatMoney(
                                                    o.grand_total,
                                                    o.currency,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
                <Card className="xl:col-span-4">
                    <h3 className="font-semibold text-dark">Sipariş durumu</h3>
                    <p className="mb-4 text-sm text-muted">
                        Tüm açık operasyonlar
                    </p>
                    <div className="space-y-2">
                        {[
                            [
                                "Hazırlanacak",
                                data?.order_status.unfulfilled ?? 0,
                                "/orders?fulfillment_status=unfulfilled",
                            ],
                            [
                                "Kargoya verilecek",
                                data?.order_status.shipping ?? 0,
                                "/orders?fulfillment_status=partial",
                            ],
                            [
                                "Ödeme bekleyen",
                                data?.order_status.payment_pending ?? 0,
                                "/orders?payment_status=pending",
                            ],
                            [
                                "Başarısız ödeme",
                                data?.order_status.failed_payments ?? 0,
                                "/orders?payment_status=failed",
                            ],
                            [
                                "İade talebi",
                                data?.order_status.returns ?? 0,
                                "/orders?fulfillment_status=returned",
                            ],
                        ].map(([label, value, path]) => (
                            <Link
                                key={label as string}
                                to={path as string}
                                className="flex items-center justify-between rounded-lg bg-app-bg px-4 py-3 hover:bg-surface-orange"
                            >
                                <span className="text-sm text-muted">
                                    {label as string}
                                </span>
                                <strong className="text-dark">{value}</strong>
                            </Link>
                        ))}
                    </div>
                </Card>
            </section>
            <section className="grid gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-4">
                    <div className="flex items-start justify-between">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Müşteri özeti
                            </h3>
                            <p className="text-sm text-muted">
                                Seçili dönemde satın alanlar
                            </p>
                        </div>
                        <Users size={19} className="text-primary" />
                    </div>
                    <div className="mt-5 grid grid-cols-2 gap-3">
                        <div className="rounded-xl bg-app-bg p-4">
                            <p className="text-xs text-muted">Toplam müşteri</p>
                            <p className="mt-2 text-2xl font-semibold text-dark">
                                {data?.customer_summary.total_customers ?? 0}
                            </p>
                        </div>
                        <div className="rounded-xl bg-app-bg p-4">
                            <p className="text-xs text-muted">
                                Geri dönüş oranı
                            </p>
                            <p className="mt-2 text-2xl font-semibold text-dark">
                                %{data?.customer_summary.returning_rate ?? 0}
                            </p>
                        </div>
                    </div>
                    <Link
                        to="/customers"
                        className="mt-4 flex items-center justify-center gap-2 rounded-md border border-border py-2 text-sm font-semibold text-dark hover:bg-app-bg"
                    >
                        Müşterileri gör <ArrowRight size={15} />
                    </Link>
                </Card>
                <Card className="p-0 xl:col-span-8">
                    <div className="flex items-center justify-between border-b border-border p-5">
                        <div>
                            <h3 className="font-semibold text-dark">
                                Yeni müşteriler
                            </h3>
                            <p className="text-sm text-muted">
                                En son oluşan müşteri kayıtları
                            </p>
                        </div>
                        <Link
                            to="/customers"
                            className="text-sm font-semibold text-primary-hover"
                        >
                            Tümünü gör{" "}
                            <ArrowRight size={14} className="inline" />
                        </Link>
                    </div>
                    {!data?.customer_summary.recent_customers.length ? (
                        <EmptyState
                            icon={Users}
                            title="Henüz müşteri yok"
                            description="Müşteriler oluştuğunda burada listelenecek."
                        />
                    ) : (
                        <div className="divide-y divide-border px-5">
                            {data.customer_summary.recent_customers.map(
                                (customer) => (
                                    <Link
                                        key={customer.id}
                                        to={`/customers/${customer.id}`}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-dark">
                                                {customer.name ||
                                                    customer.email}
                                            </p>
                                            <p className="truncate text-xs text-muted">
                                                {customer.email}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-sm font-semibold text-dark">
                                                {formatMoney(
                                                    customer.total_spent,
                                                    currency,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted">
                                                {customer.total_orders} sipariş
                                            </p>
                                        </div>
                                    </Link>
                                ),
                            )}
                        </div>
                    )}
                </Card>
            </section>
            <section className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold">
                                En çok satan ürünler
                            </h3>
                            <p className="text-sm text-muted">
                                Gelire göre sıralama
                            </p>
                        </div>
                        <Package size={19} className="text-primary" />
                    </div>
                    {!data?.top_products.length ? (
                        <EmptyState
                            icon={Package}
                            title="Henüz yeterli veri yok"
                            description="Ürün satışları burada sıralanacak."
                        />
                    ) : (
                        <div className="divide-y divide-border">
                            {data.top_products.map((p, i) => (
                                <div
                                    key={p.title}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-app-bg text-xs font-bold">
                                        {i + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">
                                            {p.title}
                                        </p>
                                        <p className="text-xs text-muted">
                                            {p.quantity} satış
                                        </p>
                                    </div>
                                    <strong className="text-sm">
                                        {formatMoney(p.revenue, currency)}
                                    </strong>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
                <Card>
                    <h3 className="font-semibold">Satış kanalları</h3>
                    <p className="mb-4 text-sm text-muted">
                        Doğrulanmış entegrasyon durumları
                    </p>
                    <div className="space-y-3">
                        {[
                            [
                                "Online Mağaza",
                                store.status === "active"
                                    ? "Aktif"
                                    : "Yapılandırma gerekli",
                                store.status === "active"
                                    ? "bg-emerald-500"
                                    : "bg-amber-400",
                            ],
                            ["Instagram", "Desteklenmiyor", "bg-slate-300"],
                            ["TikTok", "Desteklenmiyor", "bg-slate-300"],
                        ].map(([name, status, color]) => (
                            <div
                                key={name}
                                className="flex items-center rounded-xl border border-border p-4"
                            >
                                <span
                                    className={`mr-3 h-2.5 w-2.5 rounded-full ${color}`}
                                />
                                <div className="flex-1">
                                    <p className="text-sm font-semibold">
                                        {name}
                                    </p>
                                    <p className="text-xs text-muted">
                                        {status}
                                    </p>
                                </div>
                                <Link
                                    to="/channels"
                                    className="text-xs font-semibold text-primary-hover"
                                >
                                    Durumu gör
                                </Link>
                            </div>
                        ))}
                    </div>
                </Card>
            </section>
        </div>
    );
}

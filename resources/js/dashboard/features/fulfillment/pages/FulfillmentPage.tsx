import { useEffect, useState } from "react";
import { Barcode, Box, CheckCircle2, PackageCheck, Play, RefreshCw, Truck } from "lucide-react";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { Button } from "../../../components/ui/Button";
import { Card } from "../../../components/ui/Card";
import {
    createShipment,
    getFulfillmentCenter,
    packFulfillment,
    scanFulfillmentBarcode,
    startFulfillment,
    updateShipmentStatus,
    type FulfillmentCenterResponse,
    type FulfillmentRecord,
} from "../../commerce/api/adminCommerceApi";
import { formatDate } from "../../../utils/commerceFormat";

const labels: Record<string, string> = {
    unfulfilled: "Hazırlanacak",
    processing: "Hazırlanıyor",
    picking: "Toplanıyor",
    packing: "Paketleniyor",
    ready_to_ship: "Kargoya hazır",
    shipped: "Kargoda",
    in_transit: "Yolda",
    out_for_delivery: "Dağıtımda",
    delivered: "Teslim edildi",
    returned: "İade",
};

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <Card className="p-4">
            <p className="text-xs font-medium uppercase text-muted">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-dark">{value}</p>
        </Card>
    );
}

export function FulfillmentPage() {
    usePageTitle("Fulfillment");
    const [data, setData] = useState<FulfillmentCenterResponse | null>(null);
    const [selected, setSelected] = useState<FulfillmentRecord | null>(null);
    const [barcode, setBarcode] = useState("");
    const [message, setMessage] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    async function load() {
        setLoading(true);
        const response = await getFulfillmentCenter();
        setData(response.data);
        setSelected((current) => response.data.fulfillments.find((item) => item.id === current?.id) ?? response.data.fulfillments[0] ?? null);
        setLoading(false);
    }

    useEffect(() => {
        void load().catch(() => {
            setMessage("Fulfillment merkezi yüklenemedi.");
            setLoading(false);
        });
    }, []);

    async function mutate(action: () => Promise<{ data: FulfillmentRecord }>, success: string) {
        if (!selected) return;
        setMessage(null);
        try {
            const response = await action();
            setSelected(response.data);
            setMessage(success);
            await load();
        } catch {
            setMessage("İşlem tamamlanamadı.");
        }
    }

    async function ship() {
        if (!selected) return;
        setMessage(null);
        try {
            await createShipment(selected.id, { provider: "yurtici", service_code: "standard", package: { weight: selected.package?.weight ?? "1" } });
            setMessage("Gönderi oluşturuldu.");
            await load();
        } catch {
            setMessage("Gönderi oluşturulamadı.");
        }
    }

    const summary = data?.summary;

    return (
        <div className="mx-auto max-w-7xl space-y-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-dark">Fulfillment</h2>
                    <p className="text-sm text-muted">Hazırlama, barkod doğrulama, paketleme ve gönderi takibi.</p>
                </div>
                <Button fullWidth={false} variant="secondary" onClick={() => void load()} disabled={loading}>
                    <RefreshCw size={16} /> Yenile
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                <Metric label="Hazırlanacak" value={summary?.unfulfilled ?? 0} />
                <Metric label="Paketleniyor" value={summary?.processing ?? 0} />
                <Metric label="Kargoya Hazır" value={summary?.ready_to_ship ?? 0} />
                <Metric label="Kargoda" value={summary?.shipped ?? 0} />
                <Metric label="Yolda" value={summary?.in_transit ?? 0} />
                <Metric label="Teslim Edildi" value={summary?.delivered ?? 0} />
                <Metric label="İade Talebi" value={summary?.return_requests ?? 0} />
            </div>

            {message && <p className="rounded-md border border-border bg-card px-4 py-3 text-sm text-dark">{message}</p>}

            <div className="grid gap-5 lg:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.2fr)]">
                <Card className="p-0">
                    <div className="border-b border-border p-4">
                        <h3 className="font-medium text-dark">Fulfillment kuyruğu</h3>
                    </div>
                    <div className="divide-y divide-border">
                        {loading && <p className="p-4 text-sm text-muted">Yükleniyor...</p>}
                        {!loading && data?.fulfillments.length === 0 && <p className="p-4 text-sm text-muted">Aktif fulfillment kaydı yok.</p>}
                        {data?.fulfillments.map((fulfillment) => (
                            <button
                                key={fulfillment.id}
                                onClick={() => setSelected(fulfillment)}
                                className={`block w-full px-4 py-3 text-left hover:bg-app-bg ${selected?.id === fulfillment.id ? "bg-surface-orange" : ""}`}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-medium text-dark">{fulfillment.order?.number ?? fulfillment.id}</p>
                                    <span className="rounded-full bg-app-bg px-2 py-1 text-xs font-medium text-muted">{labels[fulfillment.status] ?? fulfillment.status}</span>
                                </div>
                                <p className="mt-1 text-sm text-muted">{fulfillment.items.length} ürün · {fulfillment.location?.name ?? "Depo seçilmedi"}</p>
                            </button>
                        ))}
                    </div>
                </Card>

                <Card>
                    {!selected ? (
                        <p className="text-sm text-muted">Bir fulfillment seç.</p>
                    ) : (
                        <div className="space-y-5">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-dark">{selected.order?.number}</h3>
                                    <p className="text-sm text-muted">{labels[selected.status] ?? selected.status} · {selected.location?.name ?? "Varsayılan depo"}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button fullWidth={false} variant="secondary" onClick={() => void mutate(() => startFulfillment(selected.id), "Hazırlama başladı.")}><Play size={16} /> Başla</Button>
                                    <Button fullWidth={false} variant="secondary" onClick={() => void mutate(() => packFulfillment(selected.id, { type: "medium_box", weight: "1.20", width: "30", height: "20", length: "15" }), "Paket bilgisi kaydedildi.")}><Box size={16} /> Paketle</Button>
                                    <Button fullWidth={false} onClick={() => void ship()}><Truck size={16} /> Gönderi Oluştur</Button>
                                </div>
                            </div>

                            <div className="rounded-md border border-border">
                                <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                    <PackageCheck size={17} className="text-muted" />
                                    <p className="font-medium text-dark">Pick list</p>
                                </div>
                                <div className="divide-y divide-border">
                                    {selected.items.map((item) => (
                                        <div key={item.id} className="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_7rem_7rem]">
                                            <div>
                                                <p className="font-medium text-dark">{item.title}</p>
                                                <p className="text-sm text-muted">{item.variant_title ?? "Standart"} · SKU {item.sku ?? "-"}</p>
                                                <p className="text-xs text-muted">Barkod {item.barcode ?? "-"}</p>
                                            </div>
                                            <p className="text-sm text-muted">Beklenen<br /><strong className="text-dark">{item.quantity}</strong></p>
                                            <p className="text-sm text-muted">Toplanan<br /><strong className="text-dark">{item.picked_quantity}</strong></p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <form
                                className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    void mutate(() => scanFulfillmentBarcode(selected.id, barcode), "Ürün doğrulandı.").then(() => setBarcode(""));
                                }}
                            >
                                <label className="relative">
                                    <Barcode size={17} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                                    <input value={barcode} onChange={(event) => setBarcode(event.target.value)} placeholder="Barkod tara veya SKU gir" className="w-full rounded-md border border-border bg-card py-2 pl-9 pr-3 text-sm text-dark outline-none focus:border-primary" />
                                </label>
                                <Button fullWidth={false}><CheckCircle2 size={16} /> Doğrula</Button>
                            </form>

                            {selected.shipments.length > 0 && (
                                <div className="space-y-3">
                                    <h4 className="font-medium text-dark">Gönderiler</h4>
                                    {selected.shipments.map((shipment) => (
                                        <div key={shipment.id} className="rounded-md border border-border p-4">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-medium text-dark">{shipment.provider} · {shipment.tracking_number}</p>
                                                <Button fullWidth={false} variant="secondary" onClick={() => void updateShipmentStatus(shipment.id, shipment.status === "delivered" ? "delivered" : "delivered").then(() => load())}>Teslim Edildi</Button>
                                            </div>
                                            <div className="mt-3 space-y-2">
                                                {shipment.events.map((event) => <p key={event.id} className="text-sm text-muted">{formatDate(event.occurred_at)} · {event.message}</p>)}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
}

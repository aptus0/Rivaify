import { useEffect, useState } from "react";
import { Check, PackageOpen, RefreshCw, RotateCcw, WalletCards } from "lucide-react";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { Button } from "../../../components/ui/Button";
import { Card } from "../../../components/ui/Card";
import {
    approveReturn,
    getReturnsCenter,
    inspectReturn,
    receiveReturn,
    refundReturn,
    type ReturnRecord,
    type ReturnsCenterResponse,
} from "../../commerce/api/adminCommerceApi";
import { formatDate, formatMoney } from "../../../utils/commerceFormat";

const statusLabel: Record<string, string> = {
    requested: "Yeni talep",
    under_review: "İnceleniyor",
    return_shipping: "Kargo bekleniyor",
    inspection: "İnceleme",
    refund_pending: "Refund bekliyor",
    refunded: "Refund tamamlandı",
    rejected: "Reddedildi",
};

function Metric({ label, value }: { label: string; value: number }) {
    return <Card className="p-4"><p className="text-xs font-medium uppercase text-muted">{label}</p><p className="mt-2 text-2xl font-semibold text-dark">{value}</p></Card>;
}

export function ReturnsPage() {
    usePageTitle("İadeler");
    const [data, setData] = useState<ReturnsCenterResponse | null>(null);
    const [selected, setSelected] = useState<ReturnRecord | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    async function load() {
        setLoading(true);
        const response = await getReturnsCenter();
        setData(response.data);
        setSelected((current) => response.data.returns.find((item) => item.id === current?.id) ?? response.data.returns[0] ?? null);
        setLoading(false);
    }
    useEffect(() => { void load().catch(() => { setMessage("İadeler yüklenemedi."); setLoading(false); }); }, []);

    async function mutate(action: () => Promise<{ data: ReturnRecord }>, success: string) {
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

    return (
        <div className="mx-auto max-w-7xl space-y-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-dark">Return Center</h2>
                    <p className="text-sm text-muted">İade talebi, inceleme, stok geri alma ve refund akışı.</p>
                </div>
                <Button fullWidth={false} variant="secondary" onClick={() => void load()} disabled={loading}><RefreshCw size={16} /> Yenile</Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Metric label="Yeni İade Talebi" value={data?.summary.requested ?? 0} />
                <Metric label="İnceleniyor" value={data?.summary.under_review ?? 0} />
                <Metric label="Kargo Bekleniyor" value={data?.summary.return_shipping ?? 0} />
                <Metric label="Teslim Alındı" value={data?.summary.received ?? 0} />
                <Metric label="Refund Bekleyen" value={data?.summary.refund_pending ?? 0} />
            </div>
            {message && <p className="rounded-md border border-border bg-card px-4 py-3 text-sm text-dark">{message}</p>}
            <div className="grid gap-5 lg:grid-cols-[minmax(20rem,0.75fr)_minmax(0,1.25fr)]">
                <Card className="p-0">
                    <div className="border-b border-border p-4"><h3 className="font-medium text-dark">İade kuyruğu</h3></div>
                    <div className="divide-y divide-border">
                        {!loading && data?.returns.length === 0 && <p className="p-4 text-sm text-muted">Aktif iade talebi yok.</p>}
                        {data?.returns.map((ret) => (
                            <button key={ret.id} onClick={() => setSelected(ret)} className={`block w-full px-4 py-3 text-left hover:bg-app-bg ${selected?.id === ret.id ? "bg-surface-orange" : ""}`}>
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-medium text-dark">{ret.number}</p>
                                    <span className="rounded-full bg-app-bg px-2 py-1 text-xs font-medium text-muted">{statusLabel[ret.status] ?? ret.status}</span>
                                </div>
                                <p className="mt-1 text-sm text-muted">{ret.order.number} · {formatDate(ret.requested_at)}</p>
                            </button>
                        ))}
                    </div>
                </Card>
                <Card>
                    {!selected ? <p className="text-sm text-muted">Bir iade seç.</p> : (
                        <div className="space-y-5">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-dark">{selected.number}</h3>
                                    <p className="text-sm text-muted">{selected.order.number} · {statusLabel[selected.status] ?? selected.status}</p>
                                    <p className="mt-1 text-sm text-muted">{selected.customer_note ?? selected.reason ?? "Not yok"}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button fullWidth={false} variant="secondary" onClick={() => void mutate(() => approveReturn(selected.id), "İade onaylandı.")}><Check size={16} /> Onayla</Button>
                                    <Button fullWidth={false} variant="secondary" onClick={() => void mutate(() => receiveReturn(selected.id), "İade teslim alındı.")}><PackageOpen size={16} /> Teslim Al</Button>
                                    <Button fullWidth={false} variant="secondary" onClick={() => void mutate(() => inspectReturn(selected.id, selected.items.map((item) => ({ return_item_id: item.id, condition: "opened", restock: true }))), "İnceleme tamamlandı.")}><RotateCcw size={16} /> Stoka Al</Button>
                                    <Button fullWidth={false} onClick={() => void mutate(() => refundReturn(selected.id, selected.order.grand_total, `return-${selected.id}`), "Refund tamamlandı.")}><WalletCards size={16} /> Refund</Button>
                                </div>
                            </div>
                            <div className="rounded-md border border-border">
                                <div className="grid grid-cols-[minmax(0,1fr)_5rem_8rem] border-b border-border px-4 py-3 text-xs font-medium uppercase text-muted">
                                    <span>Ürün</span><span>Adet</span><span>Çözüm</span>
                                </div>
                                {selected.items.map((item) => (
                                    <div key={item.id} className="grid grid-cols-[minmax(0,1fr)_5rem_8rem] px-4 py-3 text-sm">
                                        <span><strong className="text-dark">{item.title}</strong><br /><span className="text-muted">{item.reason_code} · {item.condition ?? "bekliyor"}</span></span>
                                        <span className="text-dark">{item.quantity}</span>
                                        <span className="text-muted">{item.resolution}</span>
                                    </div>
                                ))}
                            </div>
                            <div>
                                <h4 className="font-medium text-dark">Refunds</h4>
                                {selected.refunds.length === 0 ? <p className="mt-2 text-sm text-muted">Henüz refund yok. Maksimum {formatMoney(selected.order.grand_total, selected.order.currency)}</p> : selected.refunds.map((refund) => <p key={refund.id} className="mt-2 text-sm text-muted">{formatMoney(refund.amount, refund.currency)} · {refund.status}</p>)}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
}

import { useEffect, useState } from "react";
import { RefreshCw, WalletCards } from "lucide-react";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { Button } from "../../../components/ui/Button";
import { Card } from "../../../components/ui/Card";
import { getFinance, type FinanceResponse } from "../../commerce/api/adminCommerceApi";
import { formatDate, formatMoney } from "../../../utils/commerceFormat";

function Metric({ label, value, muted = false }: { label: string; value: string; muted?: boolean }) {
    return <Card className="p-4"><p className="text-xs font-medium uppercase text-muted">{label}</p><p className={`mt-2 text-2xl font-semibold ${muted ? "text-red-700" : "text-dark"}`}>{value}</p></Card>;
}

export function FinancePage() {
    usePageTitle("Finans");
    const [data, setData] = useState<FinanceResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    async function load() {
        setLoading(true);
        const response = await getFinance();
        setData(response.data);
        setLoading(false);
    }
    useEffect(() => { void load().catch(() => { setError("Finans verileri yüklenemedi."); setLoading(false); }); }, []);
    const currency = data?.currency ?? "TRY";

    return (
        <div className="mx-auto max-w-7xl space-y-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-dark">Finans</h2>
                    <p className="text-sm text-muted">Brüt satış, iadeler, net satış ve payout durumu.</p>
                </div>
                <Button fullWidth={false} variant="secondary" onClick={() => void load()} disabled={loading}><RefreshCw size={16} /> Yenile</Button>
            </div>
            {error && <p className="rounded-md border border-border bg-card px-4 py-3 text-sm text-red-700">{error}</p>}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Metric label="Brüt Satış" value={formatMoney(data?.gross_sales ?? "0.00", currency)} />
                <Metric label="İadeler" value={`-${formatMoney(data?.refunds ?? "0.00", currency)}`} muted />
                <Metric label="Rivaify Ücretleri" value={`-${formatMoney(data?.platform_fees ?? "0.00", currency)}`} muted />
                <Metric label="Provider Maliyetleri" value={`-${formatMoney(data?.provider_fees ?? "0.00", currency)}`} muted />
                <Metric label="Net Satış" value={formatMoney(data?.net_sales ?? "0.00", currency)} />
            </div>
            <div className="grid gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                <Card>
                    <div className="mb-4 flex items-center gap-2"><WalletCards size={18} className="text-muted" /><h3 className="font-medium text-dark">Payouts</h3></div>
                    <div className="space-y-3">
                        <p className="flex justify-between text-sm"><span className="text-muted">Bekleyen</span><strong className="text-dark">{formatMoney(data?.payouts.pending ?? "0.00", currency)}</strong></p>
                        <p className="flex justify-between text-sm"><span className="text-muted">İşleniyor</span><strong className="text-dark">{formatMoney(data?.payouts.processing ?? "0.00", currency)}</strong></p>
                        <p className="flex justify-between text-sm"><span className="text-muted">Ödendi</span><strong className="text-dark">{formatMoney(data?.payouts.paid ?? "0.00", currency)}</strong></p>
                    </div>
                </Card>
                <Card className="p-0">
                    <div className="border-b border-border p-4"><h3 className="font-medium text-dark">Settlement reconciliation</h3></div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b border-border text-xs uppercase text-muted">
                                <tr><th className="px-4 py-3">Provider</th><th className="px-4 py-3">Dönem</th><th className="px-4 py-3">Net</th><th className="px-4 py-3">Beklenen</th><th className="px-4 py-3">Fark</th><th className="px-4 py-3">Durum</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {data?.settlements.length === 0 && <tr><td colSpan={6} className="px-4 py-6 text-muted">Settlement kaydı yok.</td></tr>}
                                {data?.settlements.map((settlement) => (
                                    <tr key={settlement.id}>
                                        <td className="px-4 py-3 font-medium text-dark">{settlement.provider}</td>
                                        <td className="px-4 py-3 text-muted">{formatDate(settlement.period_start, { hour: undefined, minute: undefined })} - {formatDate(settlement.period_end, { hour: undefined, minute: undefined })}</td>
                                        <td className="px-4 py-3 text-dark">{formatMoney(settlement.net, currency)}</td>
                                        <td className="px-4 py-3 text-muted">{settlement.expected_net ? formatMoney(settlement.expected_net, currency) : "-"}</td>
                                        <td className="px-4 py-3 text-muted">{settlement.difference ? formatMoney(settlement.difference, currency) : "-"}</td>
                                        <td className="px-4 py-3 text-muted">{settlement.status}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>
    );
}

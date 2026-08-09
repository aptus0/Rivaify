import { useEffect, useState, type FormEvent } from "react";
import {
    CalendarClock,
    Megaphone,
    Pause,
    Pencil,
    Play,
    Plus,
    Trash2,
    X,
} from "lucide-react";
import { usePageTitle } from "../../../app/layouts/AppLayout";
import { Button } from "../../../components/ui/Button";
import { Card } from "../../../components/ui/Card";
import {
    createCampaign,
    deleteCampaign,
    listCampaigns,
    updateCampaign,
    type Campaign,
    type CampaignPayload,
} from "../api/marketingApi";
import { formatMoney } from "../../../utils/commerceFormat";
const EMPTY: CampaignPayload = {
    name: "",
    channel: "online_store",
    objective: "sales",
    status: "draft",
    budget: null,
    starts_at: null,
    ends_at: null,
    content: { message: "" },
};
const channelNames: Record<string, string> = {
    online_store: "Online Mağaza",
    email: "E-posta",
    instagram: "Instagram",
    facebook: "Facebook",
    tiktok: "TikTok",
};
export function MarketingPage() {
    usePageTitle("Pazarlama");
    const [items, setItems] = useState<Campaign[]>([]);
    const [summary, setSummary] = useState({
        total: 0,
        active: 0,
        scheduled: 0,
        attribution_available: false,
    });
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Campaign | null>(null);
    const [form, setForm] = useState<CampaignPayload>(EMPTY);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const load = () =>
        listCampaigns()
            .then((r) => {
                setItems(r.data);
                setSummary(r.summary);
            })
            .catch(() => setError("Kampanyalar yüklenemedi."));
    useEffect(() => {
        void load();
    }, []);
    function openCreate() {
        setEditing(null);
        setForm(EMPTY);
        setOpen(true);
    }
    function openEdit(item: Campaign) {
        setEditing(item);
        setForm({
            name: item.name,
            channel: item.channel,
            objective: item.objective,
            status: item.status,
            budget: item.budget === null ? null : Number(item.budget),
            starts_at: item.starts_at?.slice(0, 16) ?? null,
            ends_at: item.ends_at?.slice(0, 16) ?? null,
            content: { message: item.message ?? "" },
        });
        setOpen(true);
    }
    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            if (editing) await updateCampaign(editing.id, form);
            else await createCampaign(form);
            setForm(EMPTY);
            setOpen(false);
            await load();
        } catch {
            setError("Kampanya kaydedilemedi. Kanal ve yayın alanlarını kontrol edin.");
        } finally {
            setBusy(false);
        }
    }
    async function status(item: Campaign, next: string) {
        setError(null);
        try {
            await updateCampaign(item.id, { status: next });
            await load();
        } catch {
            setError("Kampanya yayınlanamadı. Online mağaza kampanyasında duyuru mesajı zorunludur.");
        }
    }
    async function remove(id: string) {
        if (!confirm("Bu kampanya silinsin mi?")) return;
        try {
            await deleteCampaign(id);
            await load();
        } catch {
            setError("Kampanya silinemedi.");
        }
    }
    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-widest text-primary">
                        Büyüme merkezi
                    </p>
                    <h2 className="mt-1 text-2xl font-semibold">Pazarlama</h2>
                    <p className="mt-1 text-sm text-muted">
                        Kampanyalarını planla, kanala göre yönet ve yayın
                        durumunu takip et.
                    </p>
                </div>
                <Button fullWidth={false} onClick={openCreate}>
                    <Plus size={16} />
                    Kampanya oluştur
                </Button>
            </div>
            {error && (
                <p className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {error}
                </p>
            )}
            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <p className="text-sm text-muted">Toplam kampanya</p>
                    <p className="mt-3 text-2xl font-semibold">
                        {summary.total}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-muted">Aktif</p>
                    <p className="mt-3 text-2xl font-semibold text-emerald-700">
                        {summary.active}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-muted">Planlandı</p>
                    <p className="mt-3 text-2xl font-semibold text-amber-700">
                        {summary.scheduled}
                    </p>
                </Card>
            </div>
            <Card className="p-0">
                <div className="border-b border-border p-5">
                    <h3 className="font-semibold">Kampanyalar</h3>
                    <p className="text-sm text-muted">
                        Gelir ilişkilendirmesi tracking aktif olduğunda
                        gösterilir; sahte ROAS üretilmez.
                    </p>
                </div>
                {items.length === 0 ? (
                    <div className="grid min-h-64 place-items-center p-8 text-center">
                        <div>
                            <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-surface-orange text-primary">
                                <Megaphone />
                            </span>
                            <h3 className="mt-4 font-semibold">
                                İlk kampanyanı oluştur
                            </h3>
                            <p className="mt-1 text-sm text-muted">
                                Kanal, bütçe ve yayın tarihlerini tek yerden
                                yönet.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-app-bg text-left text-xs uppercase text-muted">
                                <tr>
                                    <th className="px-5 py-3">Kampanya</th>
                                    <th className="px-5 py-3">Kanal</th>
                                    <th className="px-5 py-3">Durum</th>
                                    <th className="px-5 py-3">Bütçe</th>
                                    <th className="px-5 py-3 text-right">
                                        İşlem
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-5 py-4">
                                            <p className="font-semibold">
                                                {item.name}
                                            </p>
                                            <p className="text-xs text-muted">
                                                {item.objective}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            {channelNames[item.channel] ??
                                                item.channel}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="rounded-full bg-app-bg px-2.5 py-1 text-xs font-semibold">
                                                {item.status}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {item.budget
                                                ? formatMoney(
                                                      item.budget,
                                                      item.currency,
                                                  )
                                                : "Bütçe yok"}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    onClick={() => openEdit(item)}
                                                    className="rounded-md p-2 hover:bg-app-bg"
                                                    title="Düzenle"
                                                >
                                                    <Pencil size={16} />
                                                </button>
                                                {item.status === "active" ? (
                                                    <button
                                                        onClick={() =>
                                                            void status(
                                                                item,
                                                                "paused",
                                                            )
                                                        }
                                                        className="rounded-md p-2 hover:bg-app-bg"
                                                        title="Duraklat"
                                                    >
                                                        <Pause size={16} />
                                                    </button>
                                                ) : item.channel === "online_store" ? (
                                                    <button
                                                        onClick={() =>
                                                            void status(
                                                                item,
                                                                "active",
                                                            )
                                                        }
                                                        className="rounded-md p-2 hover:bg-app-bg"
                                                        title="Başlat"
                                                    >
                                                        <Play size={16} />
                                                    </button>
                                                ) : (
                                                    <span className="px-2 py-2 text-xs font-medium text-muted" title="Bu kanal bu sürümde yalnızca planlama içindir">
                                                        Plan
                                                    </span>
                                                )}
                                                <button
                                                    onClick={() =>
                                                        void remove(item.id)
                                                    }
                                                    className="rounded-md p-2 text-red-600 hover:bg-red-50"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
            {open && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-dark/40 p-4">
                    <form
                        onSubmit={submit}
                        className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-card p-6 shadow-spectrum"
                    >
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    {editing ? "Kampanyayı düzenle" : "Yeni kampanya"}
                                </h3>
                                <p className="text-sm text-muted">
                                    Kampanyanın çalışma koşullarını tanımla.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                            >
                                <X />
                            </button>
                        </div>
                        <div className="mt-6 grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-medium sm:col-span-2">
                                Kampanya adı
                                <input
                                    required
                                    value={form.name}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            name: e.target.value,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5 outline-none focus:border-primary"
                                />
                            </label>
                            <label className="text-sm font-medium">
                                Kanal
                                <select
                                    value={form.channel}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            channel: e.target.value,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                >
                                    {Object.entries(channelNames).map(
                                        ([v, l]) => (
                                            <option key={v} value={v}>
                                                {l}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>
                            <label className="text-sm font-medium">
                                Hedef
                                <select
                                    value={form.objective}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            objective: e.target.value,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                >
                                    <option value="sales">Satış</option>
                                    <option value="traffic">Trafik</option>
                                    <option value="awareness">
                                        Bilinirlik
                                    </option>
                                    <option value="retention">
                                        Müşteri geri kazanımı
                                    </option>
                                </select>
                            </label>
                            <label className="text-sm font-medium">
                                Bütçe
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.budget ?? ""}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            budget: e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                />
                            </label>
                            <label className="text-sm font-medium">
                                Başlangıç
                                <input
                                    type="datetime-local"
                                    value={form.starts_at ?? ""}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            starts_at: e.target.value || null,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                />
                            </label>
                            <label className="text-sm font-medium">
                                Bitiş
                                <input
                                    type="datetime-local"
                                    min={form.starts_at ?? undefined}
                                    value={form.ends_at ?? ""}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            ends_at: e.target.value || null,
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                />
                            </label>
                            <label className="text-sm font-medium sm:col-span-2">
                                Mesaj
                                <textarea
                                    rows={4}
                                    value={form.content.message}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            content: {
                                                message: e.target.value,
                                            },
                                        })
                                    }
                                    className="mt-1.5 w-full rounded-md border border-border px-3 py-2.5"
                                />
                            </label>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                fullWidth={false}
                                onClick={() => setOpen(false)}
                            >
                                Vazgeç
                            </Button>
                            <Button disabled={busy} fullWidth={false}>
                                <CalendarClock size={16} />
                                {busy ? "Kaydediliyor..." : editing ? "Değişiklikleri kaydet" : "Taslak oluştur"}
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}

import React, { useCallback, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
  Activity,
  AlertTriangle,
  BadgeCheck,
  CheckCircle2,
  ClipboardList,
  CreditCard,
  Database,
  Eye,
  FileText,
  History,
  Home,
  Loader2,
  LogOut,
  Menu,
  MessageSquare,
  PackageCheck,
  RefreshCcw,
  Search,
  Settings,
  ShieldAlert,
  ShieldCheck,
  Store,
  Truck,
  Users,
  Workflow,
  X,
  type LucideIcon,
} from 'lucide-react';
import {
  ApiError,
  approveVerificationRequest,
  getVerificationRequest,
  getInternalDashboard,
  listVerificationRequests,
  listOperationCases,
  login,
  logout,
  me,
  revealSensitiveField,
  rejectVerificationRequest,
  type DocumentType,
  type InternalDashboard,
  type InternalDashboardAction,
  type OperationCaseSummary,
  type OperationCaseTab,
  type SensitiveVerificationField,
  type VerificationRequestSummary,
} from './api';
import rivaifyLogoUrl from '../../images/rivaify-logo-horizontal.png';

const DOCUMENT_LABELS: Record<DocumentType, string> = {
  tax_certificate: 'Vergi Levhası',
  identity: 'Kimlik',
  signature_circular: 'İmza Sirküleri',
  business_license: 'İşletme Ruhsatı',
  other: 'Diğer',
};

const MERCHANT_TYPE_LABELS: Record<string, string> = {
  individual: 'Bireysel',
  sole_proprietorship: 'Şahıs Şirketi',
  limited_company: 'Limited Şirket',
  joint_stock_company: 'Anonim Şirket',
  other: 'Diğer',
};

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' });
}

function formatBytes(bytes: number | null): string {
  if (!bytes) return '';
  const mb = bytes / (1024 * 1024);
  return mb >= 0.1 ? `${mb.toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`;
}

// ---- Auth gate -------------------------------------------------------
//
// ins.rivaify.com has its own host-only session cookie, separate from
// app.rivaify.com's (config/session_hardening.php) — a merchant dashboard
// login never carries over here, so this is a real, independent login
// against InternalAuthController, not a redirect.

type AuthState =
  | { status: 'loading' }
  | { status: 'guest' }
  | { status: 'unauthorized'; email: string }
  | { status: 'authorized'; name: string };

function useAuthGate() {
  const [state, setState] = useState<AuthState>({ status: 'loading' });

  const refresh = useCallback(async () => {
    try {
      const { data } = await me();
      if (!data.authenticated || !data.staff) {
        setState({ status: 'guest' });
        return;
      }
      setState({ status: 'authorized', name: data.staff.name });
    } catch {
      setState({ status: 'guest' });
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return { state, refresh };
}

function CenteredMessage({ children }: { children: React.ReactNode }) {
  return <div className="flex min-h-screen items-center justify-center bg-app-bg p-6">{children}</div>;
}

function LoginScreen({ onLoggedIn }: { onLoggedIn: () => Promise<void> }) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    try {
      await login(String(form.get('email')), String(form.get('password')));
      await onLoggedIn();
    } catch (err) {
      setError(err instanceof ApiError ? err.messageFromApi ?? 'Giriş başarısız oldu.' : 'Bağlantı hatası.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <CenteredMessage>
      <div className="w-full max-w-sm rounded-md border border-border bg-card p-6 shadow-sm">
        <div className="mb-5 flex items-center gap-3">
          <img src={rivaifyLogoUrl} alt="Rivaify" className="h-9 w-auto" />
          <div>
            <p className="text-sm font-bold text-dark">Rivaify Internal</p>
            <p className="text-xs text-muted">Yetkili ekip erişimi</p>
          </div>
        </div>
        <form onSubmit={(event) => void handleSubmit(event)} className="flex flex-col gap-3">
          <div>
            <label className="mb-1 block text-xs font-semibold text-muted">E-posta</label>
            <input
              name="email"
              type="email"
              required
              autoComplete="email"
              className="w-full rounded-md border border-border bg-app-bg px-3 py-2 text-sm outline-none focus:border-primary"
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-semibold text-muted">Şifre</label>
            <input
              name="password"
              type="password"
              required
              autoComplete="current-password"
              className="w-full rounded-md border border-border bg-app-bg px-3 py-2 text-sm outline-none focus:border-primary"
            />
          </div>
          {error && <p className="text-sm text-red-600">{error}</p>}
          <button
            type="submit"
            disabled={submitting}
            className="mt-1 flex items-center justify-center gap-2 rounded-md bg-dark px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
          >
            {submitting && <Loader2 size={16} className="animate-spin" />} Giriş Yap
          </button>
        </form>
      </div>
    </CenteredMessage>
  );
}

function UnauthorizedScreen({ email, onLoggedOut }: { email: string; onLoggedOut: () => Promise<void> }) {
  return (
    <CenteredMessage>
      <div className="max-w-sm rounded-md border border-border bg-card p-6 text-center shadow-sm">
        <ShieldAlert className="mx-auto h-10 w-10 text-red-500" />
        <h1 className="mt-3 text-lg font-semibold text-dark">Yetkin yok</h1>
        <p className="mt-2 text-sm text-muted">
          <strong className="text-dark">{email}</strong> hesabının Rivaify Internal'e erişim yetkisi yok. Yetki
          gerekiyorsa bir Rivaify admin'inden hesabını işaretlemesini iste.
        </p>
        <button
          onClick={() => void logout().finally(() => void onLoggedOut())}
          className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-dark"
        >
          <LogOut size={15} /> Çıkış yap
        </button>
      </div>
    </CenteredMessage>
  );
}

// ---- Shell -------------------------------------------------------

const navItems: { label: string; icon: LucideIcon; active?: boolean }[] = [
  { label: 'Overview', icon: Home, active: true },
  { label: 'Operation Queues', icon: ClipboardList },
  { label: 'Stores', icon: Store },
  { label: 'Doğrulamalar', icon: ShieldCheck, active: true },
  { label: 'Users', icon: Users },
  { label: 'Order Ops', icon: PackageCheck },
  { label: 'Payments', icon: CreditCard },
  { label: 'Shipping', icon: Truck },
  { label: 'Returns', icon: RefreshCcw },
  { label: 'Integrations', icon: Workflow },
  { label: 'Risk & Security', icon: AlertTriangle },
  { label: 'Support', icon: MessageSquare },
  { label: 'Audit Logs', icon: History },
  { label: 'System', icon: Database },
  { label: 'Settings', icon: Settings },
];

function Sidebar({ open, onClose, staffName }: { open: boolean; onClose: () => void; staffName: string }) {
  const content = (
    <div className="flex h-full flex-col bg-card">
      <div className="border-b border-border px-5 py-5">
        <div className="flex items-center gap-3">
          <img src={rivaifyLogoUrl} alt="Rivaify" className="h-9 w-auto" />
          <div>
            <p className="text-sm font-bold text-dark">Rivaify Internal</p>
            <p className="text-xs text-muted">{staffName}</p>
          </div>
        </div>
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        {navItems.map((item) => (
          <button
            key={item.label}
            disabled={!item.active}
            className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition ${
              item.active ? 'bg-surface-orange text-primary-hover' : 'cursor-not-allowed text-muted/55'
            }`}
          >
            <item.icon size={18} />
            {item.label}
            {!item.active && <span className="ml-auto text-[10px] font-semibold uppercase tracking-wide">Yakında</span>}
          </button>
        ))}
      </nav>
      <div className="border-t border-border p-4">
        <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3">
          <p className="text-xs font-semibold text-emerald-800">Staff-only güvenli oturum</p>
          <p className="mt-1 text-xs leading-5 text-emerald-700">
            Tüm işlemler yetkili ekip hesabı, güvenli oturum ve audit kaydıyla yürütülür.
          </p>
        </div>
        <button
          onClick={() => void logout().finally(() => window.location.reload())}
          className="mt-3 flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted hover:bg-app-bg hover:text-dark"
        >
          <LogOut size={16} /> Çıkış yap
        </button>
      </div>
    </div>
  );

  return (
    <>
      <aside className="hidden w-64 shrink-0 border-r border-border lg:block">{content}</aside>
      {open && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-dark/30" onClick={onClose} />
          <div className="relative h-full w-64 shadow-spectrum">
            <button onClick={onClose} className="absolute right-3 top-5 z-10 text-muted" aria-label="Menüyü kapat">
              <X size={20} />
            </button>
            {content}
          </div>
        </div>
      )}
    </>
  );
}

function Metric({ label, value, icon: Icon }: { label: string; value: string; icon: LucideIcon }) {
  return (
    <div className="rounded-md border border-border bg-card p-4 shadow-sm">
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium text-muted">{label}</p>
        <span className="rounded-md bg-app-bg p-2 text-primary-hover">
          <Icon size={17} />
        </span>
      </div>
      <p className="mt-3 text-2xl font-bold text-dark">{value}</p>
    </div>
  );
}

function actionTone(severity: InternalDashboardAction['severity']): string {
  if (severity === 'CRITICAL') return 'text-red-700 bg-red-50 border-red-200';
  if (severity === 'HIGH') return 'text-amber-800 bg-amber-50 border-amber-200';
  if (severity === 'MEDIUM') return 'text-primary-hover bg-surface-orange border-orange-200';
  return 'text-slate-700 bg-slate-50 border-slate-200';
}

function ActionCenter({ actions }: { actions: InternalDashboardAction[] }) {
  return (
    <section className="rounded-md border border-border bg-card">
      <div className="border-b border-border p-4">
        <h2 className="font-semibold text-dark">Dikkatinizi Gerektirenler</h2>
        <p className="text-sm text-muted">Sadece gerçek kayıtlar üzerinden oluşan operasyon uyarıları.</p>
      </div>
      {actions.length === 0 ? (
        <div className="p-6 text-sm text-muted">Şu anda aksiyon gerektiren açık bir operasyon uyarısı yok.</div>
      ) : (
      <div className="grid gap-3 p-4 lg:grid-cols-3">
        {actions.map((action) => (
          <div key={action.title} className={`rounded-md border p-3 ${actionTone(action.severity)}`}>
            <div className="flex items-start gap-3">
              <AlertTriangle size={18} className="mt-0.5 shrink-0" />
              <div>
                <p className="text-[11px] font-bold uppercase">{action.severity}</p>
                <p className="mt-1 text-sm font-semibold">{action.title}</p>
                <p className="mt-1 text-xs leading-5 opacity-85">{action.detail}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
      )}
    </section>
  );
}

function OperationCaseQueue({
  tabs,
  items,
  activeTab,
  onTabChange,
}: {
  tabs: OperationCaseTab[];
  items: OperationCaseSummary[];
  activeTab: string;
  onTabChange: (tab: string) => void;
}) {
  return (
    <section className="rounded-md border border-border bg-card">
      <div className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="font-semibold text-dark">Operation Cases</h2>
          <p className="text-sm text-muted">Problem bazlı operasyon kuyruğu.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              onClick={() => onTabChange(tab.key)}
              className={`rounded-md border px-3 py-1.5 text-xs font-semibold ${
                activeTab === tab.key ? 'border-primary bg-surface-orange text-primary-hover' : 'border-border text-muted hover:bg-app-bg'
              }`}
            >
              {tab.label} {tab.count}
            </button>
          ))}
        </div>
      </div>
      {items.length === 0 ? (
        <div className="p-6 text-sm text-muted">Bu görünümde açık case yok.</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[820px] text-left text-sm">
            <thead className="border-b border-border text-xs uppercase text-muted">
              <tr>
                <th className="px-4 py-3">Case</th>
                <th className="px-4 py-3">Tip</th>
                <th className="px-4 py-3">Merchant</th>
                <th className="px-4 py-3">Priority</th>
                <th className="px-4 py-3">Assigned</th>
                <th className="px-4 py-3">Age</th>
                <th className="px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-0 hover:bg-app-bg">
                  <td className="px-4 py-3">
                    <p className="font-semibold text-dark">{item.case_number}</p>
                    <p className="text-xs text-muted">{item.title}</p>
                  </td>
                  <td className="px-4 py-3 text-muted">{item.type}</td>
                  <td className="px-4 py-3 text-muted">{item.store?.name ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-md px-2 py-1 text-xs font-bold ${actionTone(item.priority === 'NORMAL' ? 'LOW' : item.priority)}`}>
                      {item.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-muted">{item.assigned_to?.name ?? 'Unassigned'}</td>
                  <td className="px-4 py-3 text-muted">{item.age ?? '—'}</td>
                  <td className="px-4 py-3 text-muted">{item.status}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

// ---- Queue -------------------------------------------------------

function Queue({
  items,
  selectedId,
  onSelect,
}: {
  items: VerificationRequestSummary[];
  selectedId: string | null;
  onSelect: (id: string) => void;
}) {
  const [search, setSearch] = useState('');
  const filtered = items.filter((item) => {
    const q = search.trim().toLowerCase();
    if (!q) return true;
    return (
      item.id.toLowerCase().includes(q) ||
      item.store.name.toLowerCase().includes(q) ||
      (item.business?.legal_name ?? '').toLowerCase().includes(q)
    );
  });

  return (
    <section className="rounded-md border border-border bg-card">
      <div className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="font-semibold text-dark">Bekleyen Başvurular</h2>
          <p className="text-sm text-muted">Gönderim sırasına göre (en eski önce).</p>
        </div>
        <div className="relative w-full lg:w-72">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="w-full rounded-md border border-border bg-app-bg py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"
            placeholder="İşletme, mağaza veya başvuru no ara"
          />
        </div>
      </div>
      {filtered.length === 0 ? (
        <div className="p-8 text-center text-sm text-muted">
          {items.length === 0 ? 'Bekleyen başvuru yok — kuyruk temiz.' : 'Aramayla eşleşen başvuru yok.'}
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-left text-sm">
            <thead className="border-b border-border text-xs uppercase text-muted">
              <tr>
                <th className="px-4 py-3">Başvuru</th>
                <th className="px-4 py-3">İşletme</th>
                <th className="px-4 py-3">Tip</th>
                <th className="px-4 py-3">Gönderim</th>
                <th className="px-4 py-3">Belgeler</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((item) => (
                <tr
                  key={item.id}
                  onClick={() => onSelect(item.id)}
                  className={`cursor-pointer border-b border-border last:border-0 hover:bg-app-bg ${
                    selectedId === item.id ? 'bg-surface-orange/60' : ''
                  }`}
                >
                  <td className="px-4 py-3 font-semibold text-dark">{item.id}</td>
                  <td className="px-4 py-3">
                    <p className="font-medium text-dark">{item.business?.legal_name ?? item.store.name}</p>
                    <p className="text-xs text-muted">{item.store.slug}.rivaify.com</p>
                  </td>
                  <td className="px-4 py-3 text-muted">{MERCHANT_TYPE_LABELS[item.merchant.type] ?? item.merchant.type}</td>
                  <td className="px-4 py-3 text-muted">{formatDate(item.submitted_at)}</td>
                  <td className="px-4 py-3 text-muted">{item.documents.length}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

// ---- Dossier -------------------------------------------------------

type DossierTab = 'overview' | 'business' | 'documents';
const dossierTabs: { key: DossierTab; label: string }[] = [
  { key: 'overview', label: 'Genel Bakış' },
  { key: 'business', label: 'İşletme & Vergi' },
  { key: 'documents', label: 'Belgeler' },
];

const dossierComingSoonTabs = ['Orders', 'Payments', 'Shipping', 'Returns', 'Integrations', 'Risk', 'Support', 'Timeline'];

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="rounded-md border border-border bg-app-bg p-3">
      <p className="text-xs font-semibold text-muted">{label}</p>
      <p className="mt-1 truncate text-sm font-semibold text-dark">{value ?? '—'}</p>
    </div>
  );
}

function SensitiveField({
  label,
  maskedValue,
  revealedValue,
  loading,
  onReveal,
}: {
  label: string;
  maskedValue: React.ReactNode;
  revealedValue: React.ReactNode;
  loading: boolean;
  onReveal: () => void;
}) {
  return (
    <div className="rounded-md border border-border bg-app-bg p-3">
      <p className="text-xs font-semibold text-muted">{label}</p>
      <div className="mt-1 flex items-center justify-between gap-2">
        <p className="min-w-0 truncate text-sm font-semibold text-dark">{revealedValue ?? maskedValue ?? '—'}</p>
        <button
          type="button"
          onClick={onReveal}
          disabled={loading}
          className="shrink-0 rounded-md border border-border px-2 py-1 text-xs font-semibold text-muted hover:bg-card hover:text-dark disabled:opacity-60"
        >
          {loading ? <Loader2 size={13} className="animate-spin" /> : 'Göster'}
        </button>
      </div>
    </div>
  );
}

function Dossier({
  item,
  onDecided,
}: {
  item: VerificationRequestSummary;
  onDecided: (id: string) => void;
}) {
  const [tab, setTab] = useState<DossierTab>('overview');
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirmingApprove, setConfirmingApprove] = useState(false);
  const [revealed, setRevealed] = useState<Partial<Record<SensitiveVerificationField, string | null>>>({});
  const [revealing, setRevealing] = useState<SensitiveVerificationField | null>(null);

  useEffect(() => {
    setTab('overview');
    setRejecting(false);
    setReason('');
    setError(null);
    setConfirmingApprove(false);
    setRevealed({});
    setRevealing(null);
  }, [item.id]);

  async function handleReveal(field: SensitiveVerificationField) {
    setRevealing(field);
    setError(null);
    try {
      const { data } = await revealSensitiveField(item.id, field);
      setRevealed((current) => ({ ...current, [data.field]: data.value }));
    } catch (err) {
      setError(err instanceof ApiError ? err.messageFromApi ?? 'Alan gösterilemedi.' : 'Bağlantı hatası.');
    } finally {
      setRevealing(null);
    }
  }

  async function handleApprove() {
    setSubmitting(true);
    setError(null);
    try {
      await approveVerificationRequest(item.id);
      onDecided(item.id);
    } catch (err) {
      setError(err instanceof ApiError ? err.messageFromApi ?? 'Onay başarısız oldu.' : 'Bağlantı hatası.');
      setSubmitting(false);
    }
  }

  async function handleReject() {
    if (reason.trim().length === 0) {
      setError('Red için bir sebep yazmalısın.');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await rejectVerificationRequest(item.id, reason.trim());
      onDecided(item.id);
    } catch (err) {
      setError(err instanceof ApiError ? err.messageFromApi ?? 'Red işlemi başarısız oldu.' : 'Bağlantı hatası.');
      setSubmitting(false);
    }
  }

  return (
    <section className="rounded-md border border-border bg-card">
      <div className="border-b border-border p-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-muted">{item.id}</p>
            <h2 className="mt-1 text-xl font-bold text-dark">{item.business?.legal_name ?? item.store.name}</h2>
            <p className="mt-1 text-sm text-muted">{item.store.slug}.rivaify.com</p>
          </div>
        </div>
      </div>
      <div className="flex gap-2 overflow-x-auto border-b border-border px-4 py-3">
        {dossierTabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`shrink-0 rounded-md px-3 py-1.5 text-xs font-semibold ${
              tab === t.key ? 'bg-surface-orange text-primary-hover' : 'text-muted hover:bg-app-bg hover:text-dark'
            }`}
          >
            {t.label}
          </button>
        ))}
        {dossierComingSoonTabs.map((label) => (
          <button
            key={label}
            disabled
            className="shrink-0 cursor-not-allowed rounded-md px-3 py-1.5 text-xs font-semibold text-muted/55"
          >
            {label}
          </button>
        ))}
      </div>
      <div className="grid gap-4 p-4 xl:grid-cols-[1fr_320px]">
        <div className="space-y-4">
          {tab === 'overview' && (
            <div className="grid gap-3 md:grid-cols-2">
              <Field label="Merchant" value={item.merchant.owner?.name} />
              <Field label="E-posta" value={item.merchant.owner?.email} />
              <Field label="Mağaza" value={item.store.name} />
              <Field label="İşletme Türü" value={MERCHANT_TYPE_LABELS[item.merchant.type] ?? item.merchant.type} />
              <Field label="Gönderim" value={formatDate(item.submitted_at)} />
              <Field label="Belge Sayısı" value={String(item.documents.length)} />
            </div>
          )}
          {tab === 'business' && (
            <div className="grid gap-3 md:grid-cols-2">
              <Field label="Ticari Unvan" value={item.business?.legal_name} />
              <Field label="Marka Adı" value={item.business?.trade_name} />
              <SensitiveField
                label="Ticaret Sicil No"
                maskedValue={item.business?.registration_number}
                revealedValue={revealed.registration_number}
                loading={revealing === 'registration_number'}
                onReveal={() => void handleReveal('registration_number')}
              />
              <Field label="İşletme E-posta" value={item.business?.contact_email} />
              <Field label="İşletme Telefon" value={item.business?.contact_phone} />
              {item.business?.address && (
                <Field
                  label="Adres"
                  value={`${item.business.address.line1}, ${item.business.address.city}${item.business.address.country_code ? ' / ' + item.business.address.country_code : ''}`}
                />
              )}
              <Field label="Vergi Dairesi" value={item.tax?.tax_office} />
              <SensitiveField
                label="Vergi No"
                maskedValue={item.tax?.tax_number}
                revealedValue={revealed.tax_number}
                loading={revealing === 'tax_number'}
                onReveal={() => void handleReveal('tax_number')}
              />
              <Field label="Vergi Unvanı" value={item.tax?.legal_entity_name} />
            </div>
          )}
          {tab === 'documents' && (
            <div className="divide-y divide-border rounded-md border border-border">
              {item.documents.length === 0 && <p className="p-4 text-sm text-muted">Henüz belge yüklenmemiş.</p>}
              {item.documents.map((doc) => (
                <div key={doc.id} className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-3">
                    <span className="rounded-md bg-app-bg p-2 text-primary-hover">
                      <FileText size={17} />
                    </span>
                    <div>
                      <p className="text-sm font-semibold text-dark">{DOCUMENT_LABELS[doc.type]}</p>
                      <p className="text-xs text-muted">
                        {doc.original_filename} · {formatBytes(doc.size_bytes)} · Public URL yok
                      </p>
                    </div>
                  </div>
                  {doc.view_url && (
                    <a
                      href={doc.view_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center justify-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-semibold text-dark hover:bg-app-bg"
                    >
                      <Eye size={16} /> Güvenli Görüntüle
                    </a>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
        <aside className="h-fit rounded-md border border-border bg-app-bg p-4 xl:sticky xl:top-20">
          <h3 className="font-semibold text-dark">Karar</h3>
          <p className="mt-1 text-sm text-muted">Onay merchant'ı ve mağazayı aktive eder; red merchant'ı belge adımına geri gönderir.</p>

          {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

          {!rejecting ? (
            <div className="mt-4 space-y-2">
              {confirmingApprove ? (
                <div className="space-y-2 rounded-md border border-emerald-200 bg-emerald-50 p-3">
                  <p className="text-xs font-medium text-emerald-800">
                    {item.business?.legal_name ?? item.store.name} onaylanacak. Emin misin?
                  </p>
                  <div className="flex gap-2">
                    <button
                      disabled={submitting}
                      onClick={() => void handleApprove()}
                      className="flex flex-1 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
                    >
                      {submitting ? <Loader2 size={16} className="animate-spin" /> : <BadgeCheck size={16} />} Evet, Onayla
                    </button>
                    <button
                      disabled={submitting}
                      onClick={() => setConfirmingApprove(false)}
                      className="rounded-md border border-border bg-card px-3 py-2 text-sm font-semibold text-dark"
                    >
                      Vazgeç
                    </button>
                  </div>
                </div>
              ) : (
                <button
                  onClick={() => setConfirmingApprove(true)}
                  className="flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white"
                >
                  <BadgeCheck size={16} /> Onayla
                </button>
              )}
              <button
                disabled
                title="Yakında — needs_information state'i henüz backend'de yok"
                className="flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm font-semibold text-muted/60"
              >
                Ek Bilgi İste <span className="text-[10px] font-semibold uppercase">Yakında</span>
              </button>
              <button
                onClick={() => setRejecting(true)}
                className="flex w-full items-center justify-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700"
              >
                <AlertTriangle size={16} /> Reddet
              </button>
            </div>
          ) : (
            <div className="mt-4 space-y-2">
              <textarea
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="Red sebebi (merchant'a gösterilecek)"
                rows={3}
                className="w-full rounded-md border border-border bg-card p-2 text-sm outline-none focus:border-primary"
              />
              <div className="flex gap-2">
                <button
                  disabled={submitting}
                  onClick={() => void handleReject()}
                  className="flex flex-1 items-center justify-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                  {submitting ? <Loader2 size={16} className="animate-spin" /> : <AlertTriangle size={16} />} Reddi Onayla
                </button>
                <button
                  disabled={submitting}
                  onClick={() => {
                    setRejecting(false);
                    setError(null);
                  }}
                  className="rounded-md border border-border bg-card px-3 py-2 text-sm font-semibold text-dark"
                >
                  Vazgeç
                </button>
              </div>
            </div>
          )}
        </aside>
      </div>
    </section>
  );
}

// ---- App -------------------------------------------------------

function InternalAdminApp({ staffName }: { staffName: string }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [dashboard, setDashboard] = useState<InternalDashboard | null>(null);
  const [casesLoading, setCasesLoading] = useState(true);
  const [caseTab, setCaseTab] = useState('inbox');
  const [caseTabs, setCaseTabs] = useState<OperationCaseTab[]>([]);
  const [cases, setCases] = useState<OperationCaseSummary[]>([]);
  const [items, setItems] = useState<VerificationRequestSummary[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [selectedDetail, setSelectedDetail] = useState<VerificationRequestSummary | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [banner, setBanner] = useState<string | null>(null);

  const loadDashboard = useCallback(async () => {
    try {
      const { data } = await getInternalDashboard();
      setDashboard(data);
    } catch {
      setLoadError('Operasyon özeti yüklenemedi. Sayfayı yenilemeyi dene.');
    }
  }, []);

  const loadCases = useCallback(async (tab: string) => {
    setCasesLoading(true);
    try {
      const { data } = await listOperationCases(tab);
      setCaseTabs(data.tabs);
      setCases(data.items);
    } finally {
      setCasesLoading(false);
    }
  }, []);

  const loadQueue = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const { data } = await listVerificationRequests();
      setItems(data);
      return data;
    } catch {
      setLoadError('Kuyruk yüklenemedi. Sayfayı yenilemeyi dene.');
      return [];
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadDashboard();
    void loadCases(caseTab);
    void loadQueue().then((data) => {
      if (data.length > 0) setSelectedId(data[0].id);
    });
  }, [caseTab, loadCases, loadDashboard, loadQueue]);

  useEffect(() => {
    if (!selectedId) {
      setSelectedDetail(null);
      return;
    }
    let cancelled = false;
    setDetailLoading(true);
    void getVerificationRequest(selectedId)
      .then(({ data }) => {
        if (!cancelled) setSelectedDetail(data);
      })
      .catch(() => {
        if (!cancelled) setSelectedDetail(null);
      })
      .finally(() => {
        if (!cancelled) setDetailLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [selectedId]);

  async function handleDecided(decidedId: string) {
    setBanner(
      selectedDetail
        ? `${selectedDetail.business?.legal_name ?? selectedDetail.store.name} için karar kaydedildi.`
        : 'Karar kaydedildi.',
    );
    const remaining = items.filter((i) => i.id !== decidedId);
    setItems(remaining);
    setSelectedId(remaining[0]?.id ?? null);
    void loadDashboard();
    void loadCases(caseTab);
    setTimeout(() => setBanner(null), 4000);
  }

  const metrics = dashboard?.metrics;

  return (
    <div className="flex min-h-screen bg-app-bg">
      <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} staffName={staffName} />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex items-center justify-between border-b border-border bg-card/95 px-4 py-3 backdrop-blur lg:px-6">
          <div className="flex items-center gap-3">
            <button onClick={() => setSidebarOpen(true)} className="text-muted hover:text-dark lg:hidden" aria-label="Menüyü aç">
              <Menu size={20} />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-dark">Rivaify Operations</h1>
              <p className="text-xs text-muted">{dashboard?.date ?? 'Operasyon özeti yükleniyor'}</p>
            </div>
          </div>
          <div className="h-8 w-8 rounded-full bg-dark text-center text-xs font-bold leading-8 text-white">
            {staffName.slice(0, 2).toUpperCase()}
          </div>
        </header>
        <main className="flex-1 space-y-5 overflow-y-auto p-4 lg:p-6">
          {banner && (
            <div className="flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
              <CheckCircle2 size={16} /> {banner}
            </div>
          )}
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Metric label="Yeni Başvuru" value={String(metrics?.new_verifications ?? '—')} icon={Store} />
            <Metric label="İncelemede" value={String(metrics?.verification_in_review ?? '—')} icon={ShieldCheck} />
            <Metric label="Aktif Mağaza" value={String(metrics?.active_stores ?? '—')} icon={BadgeCheck} />
            <Metric label="Bugünkü Sipariş" value={String(metrics?.orders_today ?? '—')} icon={PackageCheck} />
            <Metric label="İşlem Gerektiren Ödeme" value={String(metrics?.payment_issues ?? '—')} icon={CreditCard} />
            <Metric label="Refund Hatası" value={String(metrics?.refund_failures ?? '—')} icon={RefreshCcw} />
            <Metric label="Kargo Hatası" value={String(metrics?.shipping_failures ?? '—')} icon={Truck} />
            <Metric label="Kritik Alert" value={String(metrics?.critical_alerts ?? '—')} icon={Activity} />
          </div>
          <ActionCenter actions={dashboard?.action_center ?? []} />
          {casesLoading ? (
            <div className="flex items-center gap-2 rounded-md border border-border bg-card p-6 text-sm text-muted">
              <Loader2 size={16} className="animate-spin" /> Case kuyruğu yükleniyor…
            </div>
          ) : (
            <OperationCaseQueue
              tabs={caseTabs}
              items={cases}
              activeTab={caseTab}
              onTabChange={(tab) => setCaseTab(tab)}
            />
          )}
          {loadError && <p className="text-sm text-red-600">{loadError}</p>}
          {loading ? (
            <div className="flex items-center gap-2 rounded-md border border-border bg-card p-6 text-sm text-muted">
              <Loader2 size={16} className="animate-spin" /> Kuyruk yükleniyor…
            </div>
          ) : (
            <Queue items={items} selectedId={selectedId} onSelect={setSelectedId} />
          )}
          {detailLoading && (
            <div className="flex items-center gap-2 rounded-md border border-border bg-card p-6 text-sm text-muted">
              <Loader2 size={16} className="animate-spin" /> Başvuru detayı yükleniyor…
            </div>
          )}
          {!detailLoading && selectedDetail && <Dossier item={selectedDetail} onDecided={handleDecided} />}
        </main>
      </div>
    </div>
  );
}

function Root() {
  const { state, refresh } = useAuthGate();

  if (state.status === 'loading') {
    return (
      <CenteredMessage>
        <Loader2 className="h-6 w-6 animate-spin text-muted" />
      </CenteredMessage>
    );
  }

  if (state.status === 'guest') {
    return <LoginScreen onLoggedIn={refresh} />;
  }

  if (state.status === 'unauthorized') {
    return <UnauthorizedScreen email={state.email} onLoggedOut={refresh} />;
  }

  return <InternalAdminApp staffName={state.name} />;
}

const root = document.getElementById('root');

if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <Root />
    </React.StrictMode>,
  );
}

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, Check, Eye, Loader2, Monitor, PackageCheck, Palette, Rocket, ShieldCheck, Smartphone, Upload, X } from 'lucide-react';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import { ApiError } from '../../../lib/api';
import { getThemeLibrary, getThemePreviewRuntime, installThemePackage, publishTheme, uploadThemePackage } from '../api/onlineStoreApi';
import { StorefrontRenderer } from '../../../../storefront/renderer/StorefrontRenderer';
import type { StorefrontRuntime } from '../../../../storefront/types';
import type { BuilderDevice, StoreTheme, ThemePackageInspection } from '../types';

function formatDate(value: string | null): string {
  if (!value) return 'Henüz yayınlanmadı';
  return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}

function previewWidth(device: BuilderDevice): string {
  return device === 'desktop' ? '100%' : device === 'tablet' ? '768px' : '390px';
}

function ThemePreviewModal({ theme, runtime, loading, onClose }: { theme: StoreTheme; runtime: StorefrontRuntime | null; loading: boolean; onClose: () => void }) {
  const [device, setDevice] = useState<BuilderDevice>('desktop');

  return (
    <div className="fixed inset-0 z-50 bg-dark/45 p-4" role="dialog" aria-modal="true">
      <div className="mx-auto flex h-full max-w-6xl flex-col overflow-hidden rounded-lg border border-border bg-white shadow-2xl">
        <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
          <div>
            <h2 className="text-sm font-semibold text-dark">{theme.name} önizleme</h2>
            <p className="text-xs text-muted">Taslak doküman güvenli preview olarak gösteriliyor.</p>
          </div>
          <div className="flex items-center gap-2">
            <div className="flex items-center rounded-md bg-app-bg p-1">
              {[
                ['desktop', Monitor],
                ['mobile', Smartphone],
              ].map(([value, Icon]) => (
                <button type="button" key={value as string} className={`rounded p-2 ${device === value ? 'bg-white text-dark shadow-sm' : 'text-muted'}`} onClick={() => setDevice(value as BuilderDevice)}>
                  <Icon className="h-4 w-4" />
                </button>
              ))}
            </div>
            <button type="button" onClick={onClose} className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" aria-label="Önizlemeyi kapat"><X className="h-4 w-4" /></button>
          </div>
        </div>
        <div className="min-h-0 flex-1 overflow-auto bg-zinc-100 p-5">
          {loading ? (
            <div className="flex h-full items-center justify-center text-sm text-muted"><Loader2 className="mr-2 h-4 w-4 animate-spin" />Önizleme hazırlanıyor</div>
          ) : (
            <div className="mx-auto overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-zinc-200 transition-all" style={{ width: previewWidth(device), maxWidth: '100%' }}>
              {runtime ? <StorefrontRenderer store={runtime.store} document={runtime.document} products={runtime.products} mode="preview" /> : null}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function ThemeUploadModal({ onClose, onInstalled }: { onClose: () => void; onInstalled: (theme: StoreTheme) => void }) {
  const [packageReport, setPackageReport] = useState<ThemePackageInspection | null>(null);
  const [uploading, setUploading] = useState(false);
  const [installing, setInstalling] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  async function handleFile(file: File | null) {
    if (!file) return;
    setUploading(true);
    setMessage(null);
    setPackageReport(null);

    try {
      const response = await uploadThemePackage(file);
      setPackageReport(response.data);
    } catch (error) {
      if (error instanceof ApiError && error.body && typeof error.body === 'object' && 'data' in error.body) {
        setPackageReport((error.body as { data: ThemePackageInspection }).data);
      } else {
        setMessage('Tema paketi okunamadı. Lütfen .rivtheme dosyasını kontrol edin.');
      }
    } finally {
      setUploading(false);
    }
  }

  async function handleInstall() {
    if (!packageReport || packageReport.status !== 'validated') return;
    setInstalling(true);
    setMessage(null);
    try {
      const response = await installThemePackage(packageReport.id);
      onInstalled(response.data.theme);
      setPackageReport(response.data.package);
      setMessage('Tema taslak olarak eklendi.');
    } catch {
      setMessage('Tema kurulamadı. Raporu kontrol edip tekrar deneyin.');
    } finally {
      setInstalling(false);
    }
  }

  const report = packageReport?.report;
  const passed = packageReport?.status === 'validated' && report?.status === 'passed';

  return (
    <div className="fixed inset-0 z-50 bg-dark/45 p-4" role="dialog" aria-modal="true">
      <div className="mx-auto flex h-full max-w-4xl flex-col overflow-hidden rounded-lg border border-border bg-white shadow-2xl">
        <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-dark">Tema Yükle</h2>
            <p className="text-sm text-muted">Yalnızca güvenli .rivtheme paketleri karantinada doğrulanır ve taslak olarak eklenir.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-2 text-muted hover:bg-app-bg hover:text-dark" aria-label="Kapat"><X className="h-4 w-4" /></button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          <label className="flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-border bg-app-bg px-6 py-8 text-center hover:border-primary hover:bg-primary/5">
            {uploading ? <Loader2 className="h-8 w-8 animate-spin text-primary" /> : <Upload className="h-8 w-8 text-primary" />}
            <span className="mt-3 text-sm font-semibold text-dark">{uploading ? 'Paket inceleniyor...' : '.rivtheme paketi seç'}</span>
            <span className="mt-1 text-xs text-muted">Manifest, RivaLang, asset ve güvenlik kontrolleri çalışır.</span>
            <input type="file" accept=".rivtheme" className="sr-only" disabled={uploading} onChange={(event) => void handleFile(event.target.files?.[0] ?? null)} />
          </label>

          {message ? <p className={`mt-4 text-sm font-medium ${message.includes('eklendi') ? 'text-emerald-700' : 'text-red-600'}`}>{message}</p> : null}

          {packageReport ? (
            <div className="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
              <Card className="p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-dark">{packageReport.manifest?.name ?? packageReport.filename}</p>
                    <p className="mt-1 text-xs text-muted">v{packageReport.manifest?.version ?? 'n/a'} · Engine {packageReport.manifest?.engine ?? 'n/a'}</p>
                  </div>
                  <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold ${passed ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
                    {passed ? <ShieldCheck className="h-3.5 w-3.5" /> : <AlertTriangle className="h-3.5 w-3.5" />}
                    {passed ? 'Doğrulandı' : 'Engellendi'}
                  </span>
                </div>

                <div className="mt-4 grid gap-2 sm:grid-cols-2">
                  {report?.stages.map((stage) => (
                    <div key={stage.key} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
                      <span className="font-medium text-dark">{stage.label}</span>
                      <span className={stage.status === 'failed' ? 'text-red-600' : stage.status === 'warning' ? 'text-amber-600' : 'text-emerald-700'}>
                        {stage.status === 'passed' ? <Check className="h-4 w-4" /> : stage.status}
                      </span>
                    </div>
                  ))}
                </div>

                {(report?.errors.length ?? 0) > 0 ? (
                  <div className="mt-4 rounded-md border border-red-100 bg-red-50 p-3">
                    <p className="text-sm font-semibold text-red-700">Hatalar</p>
                    <div className="mt-2 space-y-2">
                      {report?.errors.slice(0, 6).map((issue, index) => (
                        <p key={`${issue.stage}-${index}`} className="text-xs text-red-700">
                          {issue.file ? `${issue.file}${issue.line ? `:${issue.line}:${issue.column ?? 1}` : ''} - ` : ''}{issue.message}
                          {issue.suggestion ? <span className="block text-red-600">{issue.suggestion}</span> : null}
                        </p>
                      ))}
                    </div>
                  </div>
                ) : null}

                {(report?.warnings.length ?? 0) > 0 ? (
                  <div className="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3">
                    <p className="text-sm font-semibold text-amber-700">Uyarılar</p>
                    <div className="mt-2 space-y-2">
                      {report?.warnings.slice(0, 4).map((issue, index) => <p key={`${issue.stage}-${index}`} className="text-xs text-amber-700">{issue.message}</p>)}
                    </div>
                  </div>
                ) : null}
              </Card>

              <Card className="p-4">
                <PackageCheck className="h-6 w-6 text-primary" />
                <p className="mt-3 text-sm font-semibold text-dark">Compatibility Report</p>
                <div className="mt-3 grid gap-2 text-xs text-muted">
                  <span>Templates: {report?.summary.templates ?? 0}</span>
                  <span>Sections: {report?.summary.sections ?? 0}</span>
                  <span>Languages: {report?.summary.languages ?? 0}</span>
                  <span>Assets: {report?.summary.assets ?? 0}</span>
                </div>
                <Button type="button" className="mt-5" disabled={!passed || installing} onClick={() => void handleInstall()}>
                  {installing ? <Loader2 className="h-4 w-4 animate-spin" /> : <PackageCheck className="h-4 w-4" />}
                  Temayı Ekle
                </Button>
              </Card>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export function ThemeLibraryPage() {
  const [themes, setThemes] = useState<StoreTheme[]>([]);
  const [loading, setLoading] = useState(true);
  const [publishingId, setPublishingId] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [previewTheme, setPreviewTheme] = useState<StoreTheme | null>(null);
  const [previewRuntime, setPreviewRuntime] = useState<StorefrontRuntime | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [uploadOpen, setUploadOpen] = useState(false);

  useEffect(() => {
    getThemeLibrary().then((response) => setThemes(response.data.themes)).finally(() => setLoading(false));
  }, []);

  async function handlePreview(theme: StoreTheme) {
    setPreviewTheme(theme);
    setPreviewRuntime(null);
    setPreviewLoading(true);
    try {
      const response = await getThemePreviewRuntime(theme.id);
      setPreviewRuntime(response.data);
    } finally {
      setPreviewLoading(false);
    }
  }

  async function handlePublish(themeId: string) {
    setPublishingId(themeId);
    setMessage(null);
    try {
      const response = await publishTheme(themeId);
      setThemes((items) => items.map((item) => item.id === themeId ? response.data.theme : { ...item, status: item.status === 'live' ? 'draft' : item.status }));
      setMessage(`v${response.data.version.number} yayına alındı.`);
    } catch {
      setMessage('Tema yayınlanamadı. Lütfen tekrar deneyin.');
    } finally {
      setPublishingId(null);
    }
  }

  if (loading) {
    return <div className="grid gap-4"><div className="h-28 animate-pulse rounded-lg bg-card" /><div className="h-44 animate-pulse rounded-lg bg-card" /></div>;
  }

  return (
    <div className="mx-auto max-w-6xl space-y-5">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-dark">Tema kitaplığı</h1>
          <p className="mt-1 text-sm text-muted">Kurulu temaları önizle, düzenle ve güvenli yayın akışına al.</p>
          {message ? <p className={`mt-2 text-sm font-medium ${message.includes('yayına') ? 'text-emerald-700' : 'text-red-600'}`}>{message}</p> : null}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" fullWidth={false} onClick={() => setUploadOpen(true)}><Upload className="h-4 w-4" />Tema Yükle</Button>
          <Link to="/online-store">
            <Button type="button" variant="secondary" fullWidth={false}>Online mağaza</Button>
          </Link>
        </div>
      </div>

      <div className="grid gap-4">
        {themes.map((theme) => (
          <Card className="p-0" key={theme.id}>
            <div className="grid gap-0 lg:grid-cols-[17rem_minmax(0,1fr)]">
              <button type="button" onClick={() => void handlePreview(theme)} className="group min-h-44 overflow-hidden rounded-l-lg bg-zinc-950 text-left">
                <div className="h-full bg-[linear-gradient(135deg,#111827,#0f766e_55%,#f8fafc)] p-4 text-white">
                  <div className="flex h-full flex-col justify-between">
                    <Palette className="h-6 w-6 opacity-90" />
                    <div>
                      <p className="text-xs uppercase tracking-[.18em] text-white/70">{theme.base_key}</p>
                      <p className="mt-2 text-2xl font-semibold">{theme.name}</p>
                    </div>
                  </div>
                </div>
              </button>
              <div className="flex flex-col justify-between gap-5 p-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="text-lg font-semibold text-dark">{theme.name}</h2>
                      <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${theme.status === 'live' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600'}`}>{theme.status === 'live' ? 'Canlı' : 'Taslak'}</span>
                      <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-600">{theme.trust_level === 'official' ? 'Official' : 'Custom Theme'}</span>
                    </div>
                    <p className="mt-1 text-sm text-muted">v{theme.release_version ?? theme.base_version} · {theme.publisher ?? theme.base_key} · Son yayın: {formatDate(theme.published_at)}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" fullWidth={false} onClick={() => void handlePreview(theme)}><Eye className="h-4 w-4" />Önizle</Button>
                    <Link to={`/online-store/themes/${theme.id}/editor`}><Button type="button" fullWidth={false}><Palette className="h-4 w-4" />Özelleştir</Button></Link>
                    <Button type="button" variant="secondary" fullWidth={false} disabled={publishingId === theme.id} onClick={() => handlePublish(theme.id)}>
                      {publishingId === theme.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />}Yayınla
                    </Button>
                  </div>
                </div>
                <div className="grid gap-2 text-xs text-muted sm:grid-cols-3">
                  <span className="rounded-md bg-app-bg px-3 py-2">Draft/live ayrımı aktif</span>
                  <span className="rounded-md bg-app-bg px-3 py-2">Revision koruması aktif</span>
                  <span className="rounded-md bg-app-bg px-3 py-2">Manifest publish hazır</span>
                </div>
              </div>
            </div>
          </Card>
        ))}
      </div>

      {previewTheme ? <ThemePreviewModal theme={previewTheme} runtime={previewRuntime} loading={previewLoading} onClose={() => setPreviewTheme(null)} /> : null}
      {uploadOpen ? <ThemeUploadModal onClose={() => setUploadOpen(false)} onInstalled={(theme) => setThemes((items) => [theme, ...items])} /> : null}
    </div>
  );
}

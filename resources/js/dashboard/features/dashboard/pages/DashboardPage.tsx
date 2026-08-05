import { useAuth } from '../../../app/providers/AuthProvider';
import { logout } from '../../auth/api/authApi';

export function DashboardPage() {
  const { user, store, refresh } = useAuth();

  async function handleLogout() {
    await logout();
    await refresh();
  }

  return (
    <div className="mx-auto mt-16 w-full max-w-2xl">
      <div className="mb-8 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">{store?.name}</h1>
        <button onClick={() => void handleLogout()} className="text-sm text-neutral-600 underline">
          Çıkış yap
        </button>
      </div>
      <p className="text-neutral-600">
        Hoş geldin, {user?.name}. Mağazan aktif — ürün ve sipariş yönetimi Sprint 02&apos;de (Commerce
        Core) burada olacak.
      </p>
    </div>
  );
}

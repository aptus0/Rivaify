import { useNavigate } from 'react-router-dom';
import { logout } from '../../features/auth/api/authApi';
import { useAuth } from '../../app/providers/AuthProvider';
import { Avatar } from '../ui/Avatar';
import { Dropdown } from '../ui/Dropdown';
import type { CurrentUser } from '../../types';

export function UserMenu({ user }: { user: CurrentUser }) {
  const { refresh } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    await refresh();
    navigate('/login');
  }

  return (
    <Dropdown
      align="right"
      trigger={({ toggle }) => (
        <button onClick={toggle} aria-label="Hesap menüsü">
          <Avatar name={user.name} />
        </button>
      )}
    >
      {({ close }) => (
        <>
          <div className="px-3 py-2">
            <p className="text-sm font-medium text-dark">{user.name}</p>
            <p className="text-xs text-muted">{user.email}</p>
          </div>
          <div className="my-1 border-t border-border" />
          {['Profil', 'Hesap Ayarları', 'Mağaza Ayarları'].map((label) => (
            <button
              key={label}
              disabled
              title="Yakında"
              className="block w-full cursor-not-allowed px-3 py-2 text-left text-sm text-muted"
            >
              {label}
            </button>
          ))}
          <div className="my-1 border-t border-border" />
          <button
            onClick={() => {
              close();
              void handleLogout();
            }}
            className="block w-full px-3 py-2 text-left text-sm text-dark hover:bg-app-bg"
          >
            Çıkış Yap
          </button>
        </>
      )}
    </Dropdown>
  );
}

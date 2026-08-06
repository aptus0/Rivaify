import { Outlet } from 'react-router-dom';

export function AdminLayout() {
  return (
    <div className="flex min-h-screen bg-gray-100">
      <div className="w-64 bg-gray-900 text-white p-6">
        <h1 className="text-xl font-bold mb-8">Rivaify Admin</h1>
        <nav className="space-y-4">
          <a href="/admin/queue" className="block text-gray-300 hover:text-white">Verification Queue</a>
          <a href="/admin/merchants" className="block text-gray-300 hover:text-white">Merchants</a>
        </nav>
      </div>
      <div className="flex-1 p-10">
        <Outlet />
      </div>
    </div>
  );
}

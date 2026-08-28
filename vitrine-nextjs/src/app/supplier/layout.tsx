'use client';

import { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';

export default function SupplierLayout({ children }: { children: React.ReactNode }) {
    const router = useRouter();
    const pathname = usePathname();
    const [user, setUser] = useState<any>(null);
    const [loading, setLoading] = useState(true);

    const isLoginPage = pathname ? pathname.startsWith('/supplier/login') : false;

    useEffect(() => {
        if (isLoginPage) {
            setLoading(false);
            return;
        }

        const token = localStorage.getItem('supplier_token');
        const storedUser = localStorage.getItem('supplier_user');

        if (!token || !storedUser) {
            router.push('/supplier/login');
        } else {
            try {
                setUser(JSON.parse(storedUser));
            } catch (e) {
                console.error("Failed to parse supplier user", e);
            }
            setLoading(false);
        }
    }, [isLoginPage, router]);

    const handleLogout = () => {
        localStorage.removeItem('supplier_token');
        localStorage.removeItem('supplier_user');
        router.push('/supplier/login');
    };

    if (isLoginPage) {
        return <>{children}</>;
    }

    if (loading) {
        return (
            <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center">
                <div className="flex flex-col items-center gap-3">
                    <div className="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin" />
                    <p className="text-slate-400 text-sm">Chargement de la console...</p>
                </div>
            </div>
        );
    }

    const navigationItems = [
        { name: 'Tableau de bord', href: '/supplier', icon: '📊' },
        { name: 'Mon Catalogue', href: '/supplier/catalog', icon: '📦' },
        { name: 'Commandes Client', href: '/supplier/orders', icon: '🛒' },
        { name: 'Litiges & Plaintes', href: '/supplier/litiges', icon: '⚖️' },
    ];

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
            {/* Topbar */}
            <header className="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
                <div className="flex items-center gap-3">
                    <span className="bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-extrabold px-3 py-1 rounded text-xs tracking-wider uppercase">
                        PROSARTISAN
                    </span>
                    <span className="text-slate-400 font-semibold text-lg hidden sm:inline">
                        Espace Fournisseur
                    </span>
                </div>

                <div className="flex items-center gap-4">
                    <div className="text-right hidden sm:block">
                        <div className="font-semibold text-sm text-slate-200">{user?.name || user?.phone}</div>
                        <div className="text-[10px] text-amber-500 font-bold uppercase tracking-wider">Quincaillerie Agréée</div>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="bg-slate-800 hover:bg-rose-950/40 hover:text-rose-300 text-slate-300 font-bold px-4 py-2 rounded-lg text-xs border border-slate-700 hover:border-rose-900 transition active:scale-[0.98]"
                    >
                        Déconnexion
                    </button>
                </div>
            </header>

            <div className="flex-1 flex flex-col md:flex-row">
                {/* Sidebar Navigation */}
                <aside className="w-full md:w-64 bg-slate-900/60 backdrop-blur-md md:border-r border-b md:border-b-0 border-slate-800 p-4 flex flex-row md:flex-col gap-1.5 overflow-x-auto md:overflow-x-visible">
                    <div className="text-[10px] text-slate-500 font-bold tracking-wider uppercase px-3 py-2 hidden md:block">
                        Navigation
                    </div>
                    {navigationItems.map((item) => {
                        const isActive = pathname === item.href;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-semibold transition whitespace-nowrap md:w-full ${
                                    isActive
                                        ? 'bg-amber-500/10 text-amber-500 border-l-2 border-amber-500'
                                        : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                                }`}
                            >
                                <span>{item.icon}</span>
                                <span>{item.name}</span>
                            </Link>
                        );
                    })}
                </aside>

                {/* Main Content Area */}
                <main className="flex-1 bg-slate-950 p-6 md:p-8 overflow-y-auto">
                    {children}
                </main>
            </div>
        </div>
    );
}

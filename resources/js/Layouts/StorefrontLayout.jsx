import ApplicationLogo from '@/Components/ApplicationLogo';
import ThemeSwitcher from '@/Components/ThemeSwitcher';
import { cn } from '@/lib/utils';
import { Head, Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Menu, PackageSearch, ReceiptText, UserRound, X } from 'lucide-react';
import { useState } from 'react';

const staffRoles = ['admin', 'operator', 'accounting'];

function NavItem({ active, children, href, onClick }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className={cn(
                'rounded-full px-3 py-2 text-sm font-medium transition-colors',
                active
                    ? 'bg-orange-500/10 text-orange-600 dark:text-orange-400'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
        >
            {children}
        </Link>
    );
}

function AccountLinks({ isMember, isStaff, onNavigate, user }) {
    if (!user) {
        return (
            <>
                <Link
                    href={route('login')}
                    onClick={onNavigate}
                    className="inline-flex h-10 items-center justify-center rounded-full px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                >
                    Masuk
                </Link>
                <Link
                    href={route('register')}
                    onClick={onNavigate}
                    className="inline-flex h-10 items-center justify-center rounded-full bg-orange-500 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-orange-600"
                >
                    Daftar
                </Link>
            </>
        );
    }

    return (
        <>
            {isMember && (
                <Link
                    href={route('front.loans.index')}
                    onClick={onNavigate}
                    className="inline-flex h-10 items-center gap-2 rounded-full px-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                >
                    <ReceiptText className="size-4" />
                    <span>Transaksi</span>
                </Link>
            )}
            {isStaff && (
                <Link
                    href={route('dashboard')}
                    onClick={onNavigate}
                    className="inline-flex h-10 items-center gap-2 rounded-full px-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                >
                    <LayoutDashboard className="size-4" />
                    <span>Dashboard</span>
                </Link>
            )}
            <Link
                href={route('profile.edit')}
                onClick={onNavigate}
                className="inline-flex h-10 items-center gap-2 rounded-full px-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
            >
                <UserRound className="size-4" />
                <span className="max-w-28 truncate">{user.name}</span>
            </Link>
            <Link
                href={route('logout')}
                method="post"
                as="button"
                onClick={onNavigate}
                className="inline-flex h-10 items-center justify-center rounded-full border border-border px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
            >
                Keluar
            </Link>
        </>
    );
}

export default function StorefrontLayout({ children, title = 'HMW Rent' }) {
    const { props, url } = usePage();
    const user = props.auth?.user ?? null;
    const roles = Array.isArray(user?.role) ? user.role : [];
    const isMember = roles.includes('member');
    const isStaff = roles.some((role) => staffRoles.includes(role));
    const [menuOpen, setMenuOpen] = useState(false);
    const currentPath = url.split('?')[0];

    const closeMenu = () => setMenuOpen(false);

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-40 border-b border-border/80 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/75">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                        <ApplicationLogo url={route('home')} />

                        <nav className="hidden items-center gap-1 md:flex" aria-label="Navigasi utama">
                            <NavItem href={route('front.products.index')} active={currentPath === '/' || currentPath === '/products'}>
                                Katalog
                            </NavItem>
                            <NavItem href={route('front.categories.index')} active={currentPath.startsWith('/categories')}>
                                Kategori
                            </NavItem>
                        </nav>

                        <div className="flex shrink-0 items-center gap-2">
                            <div className="hidden items-center gap-1 md:flex">
                                <AccountLinks user={user} isMember={isMember} isStaff={isStaff} />
                            </div>
                            <ThemeSwitcher className="size-10 shrink-0 rounded-full" />

                            <button
                                type="button"
                                onClick={() => setMenuOpen((open) => !open)}
                                className="inline-flex size-10 items-center justify-center rounded-full border border-border text-foreground transition-colors hover:bg-muted md:hidden"
                                aria-label={menuOpen ? 'Tutup menu' : 'Buka menu'}
                                aria-expanded={menuOpen}
                            >
                                {menuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                            </button>
                        </div>
                    </div>

                    {menuOpen && (
                        <div className="border-t border-border bg-background px-4 py-3 md:hidden">
                            <nav className="mx-auto flex max-w-7xl flex-col gap-1" aria-label="Navigasi utama mobile">
                                <NavItem href={route('front.products.index')} active={currentPath === '/' || currentPath === '/products'} onClick={closeMenu}>
                                    Katalog
                                </NavItem>
                                <NavItem href={route('front.categories.index')} active={currentPath.startsWith('/categories')} onClick={closeMenu}>
                                    Kategori
                                </NavItem>
                                <div className="mt-2 flex flex-wrap items-center gap-2 border-t border-border pt-3">
                                    <AccountLinks user={user} isMember={isMember} isStaff={isStaff} onNavigate={closeMenu} />
                                </div>
                            </nav>
                        </div>
                    )}
                </header>

                <main>
                    {props.flash_message?.message && (
                        <div className="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div role="status" className={cn('rounded-xl border p-4 text-sm',
                                props.flash_message.type === 'error'
                                    ? 'border-red-300 bg-red-50 text-red-800'
                                    : 'border-emerald-300 bg-emerald-50 text-emerald-800')}>
                                {props.flash_message.message}
                            </div>
                        </div>
                    )}
                    {children}
                </main>

                <footer className="border-t border-border bg-muted/30">
                    <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <div className="flex items-center gap-2">
                            <PackageSearch className="size-4 text-orange-500" />
                            <span>HMW Rent — sewa barang untuk kebutuhanmu.</span>
                        </div>
                        <span>© {new Date().getFullYear()} HMW Rent</span>
                    </div>
                </footer>
            </div>
        </>
    );
}

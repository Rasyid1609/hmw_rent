import BookCard from '@/Components/BookCard';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { ArrowRight, PackageOpen, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useState } from 'react';

function paginationText(label) {
    return String(label).replace('&laquo;', '←').replace('&raquo;', '→');
}

function PaginationControls({ meta }) {
    const links = meta?.links ?? [];

    if ((!meta?.has_pages && Number(meta?.last_page ?? 1) <= 1) || links.length === 0) {
        return null;
    }

    return (
        <nav className="mt-10 flex justify-center" aria-label="Paginasi katalog">
            <div className="flex flex-wrap justify-center gap-2">
                {links.map((link, index) => {
                    const disabled = !link.url;
                    const classes = cn(
                        'inline-flex min-h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold transition-colors',
                        link.active
                            ? 'bg-orange-500 text-white'
                            : disabled
                              ? 'cursor-not-allowed text-muted-foreground/50'
                              : 'border border-border bg-background text-foreground hover:bg-muted',
                    );

                    return disabled ? (
                        <span key={`${link.label}-${index}`} className={classes} aria-disabled="true">
                            {paginationText(link.label)}
                        </span>
                    ) : (
                        <Link key={`${link.label}-${index}`} href={link.url} preserveScroll className={classes}>
                            {paginationText(link.label)}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}

export default function Index({ categories: categoryResource, filters = {}, page_settings: pageSettings, products: productResource }) {
    const categories = Array.isArray(categoryResource) ? categoryResource : categoryResource?.data ?? [];
    const products = productResource?.data ?? (Array.isArray(productResource) ? productResource : []);
    const meta = productResource?.meta ?? {};
    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState(filters.category ?? '');

    useEffect(() => {
        setSearch(filters.search ?? '');
        setCategory(filters.category ?? '');
    }, [filters.search, filters.category]);

    const applyFilters = (nextSearch = search, nextCategory = category) => {
        router.get(
            route('front.products.index'),
            {
                ...(nextSearch.trim() ? { search: nextSearch.trim() } : {}),
                ...(nextCategory ? { category: nextCategory } : {}),
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setCategory('');
        applyFilters('', '');
    };

    const activeCategory = categories.find((item) => item.slug === category);
    const resultCount = meta.total ?? products.length;
    const hasFilters = Boolean(search.trim() || category);

    return (
        <div>
            <section className="border-b border-border bg-[radial-gradient(circle_at_top_right,_rgba(249,115,22,0.18),_transparent_32rem)]">
                <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">
                    <div>
                        <p className="mb-4 inline-flex rounded-full border border-orange-500/25 bg-orange-500/10 px-3 py-1 text-sm font-semibold text-orange-600 dark:text-orange-400">
                            Sewa lebih mudah, pilih lebih leluasa
                        </p>
                        <h1 className="max-w-3xl text-4xl font-black tracking-tight text-foreground sm:text-5xl">
                            Barang yang kamu butuhkan, tersedia untuk disewa.
                        </h1>
                        <p className="mt-5 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg">
                            Jelajahi katalog HMW Rent sebelum membuat akun. Pilih barang, tentukan jadwal, lalu lanjutkan sewa saat kamu siap.
                        </p>
                        <a
                            href="#katalog"
                            className="mt-8 inline-flex h-11 items-center gap-2 rounded-full bg-orange-500 px-5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600"
                        >
                            Lihat katalog
                            <ArrowRight className="size-4" />
                        </a>
                    </div>
                    <div className="grid grid-cols-2 gap-3 self-end sm:max-w-md lg:justify-self-end">
                        <div className="rounded-2xl border border-border bg-card p-5 shadow-sm">
                            <p className="text-3xl font-black text-orange-500">{categories.length}</p>
                            <p className="mt-1 text-sm text-muted-foreground">kategori untuk dijelajahi</p>
                        </div>
                        <div className="rounded-2xl border border-border bg-card p-5 shadow-sm">
                            <p className="text-3xl font-black text-orange-500">{meta.total ?? products.length}</p>
                            <p className="mt-1 text-sm text-muted-foreground">barang dalam katalog</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="katalog" className="mx-auto max-w-7xl scroll-mt-24 px-4 py-12 sm:px-6 lg:px-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">Katalog</p>
                        <h2 className="mt-2 text-3xl font-bold tracking-tight">Temukan barang untuk rencanamu</h2>
                    </div>
                    <Link href={route('front.categories.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300">
                        Lihat semua kategori
                        <ArrowRight className="size-4" />
                    </Link>
                </div>

                {categories.length > 0 && (
                    <div className="mt-7 flex gap-2 overflow-x-auto pb-2" aria-label="Filter kategori cepat">
                        <Link
                            href={route('front.products.index')}
                            className={cn(
                                'shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
                                !category ? 'border-orange-500 bg-orange-500 text-white' : 'border-border bg-background hover:bg-muted',
                            )}
                        >
                            Semua barang
                        </Link>
                        {categories.map((item) => (
                            <Link
                                key={item.id ?? item.slug}
                                href={route('front.products.index', { category: item.slug })}
                                className={cn(
                                    'shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
                                    category === item.slug
                                        ? 'border-orange-500 bg-orange-500 text-white'
                                        : 'border-border bg-background hover:bg-muted',
                                )}
                            >
                                {item.name}
                            </Link>
                        ))}
                    </div>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters();
                    }}
                    className="mt-7 grid gap-3 rounded-2xl border border-border bg-card p-3 shadow-sm md:grid-cols-[1fr_15rem_auto]"
                >
                    <label className="relative block">
                        <span className="sr-only">Cari barang</span>
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama atau deskripsi barang"
                            className="h-11 w-full rounded-xl border-border bg-background pl-10 text-sm placeholder:text-muted-foreground focus:border-orange-500 focus:ring-orange-500"
                        />
                    </label>
                    <label className="relative block">
                        <span className="sr-only">Pilih kategori</span>
                        <SlidersHorizontal className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <select
                            value={category}
                            onChange={(event) => setCategory(event.target.value)}
                            className="h-11 w-full rounded-xl border-border bg-background pl-10 pr-8 text-sm focus:border-orange-500 focus:ring-orange-500"
                        >
                            <option value="">Semua kategori</option>
                            {categories.map((item) => (
                                <option key={item.id ?? item.slug} value={item.slug}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex gap-2">
                        {hasFilters && (
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-border px-4 text-sm font-semibold transition-colors hover:bg-muted"
                            >
                                <X className="size-4" />
                                Bersihkan
                            </button>
                        )}
                        <button type="submit" className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 text-sm font-bold text-white transition-colors hover:bg-orange-600">
                            Cari
                            <Search className="size-4" />
                        </button>
                    </div>
                </form>

                <div className="mt-8 flex items-center justify-between gap-4">
                    <p className="text-sm text-muted-foreground">
                        Menampilkan <span className="font-semibold text-foreground">{resultCount}</span> barang
                        {activeCategory && <> dalam kategori <span className="font-semibold text-foreground">{activeCategory.name}</span></>}.
                    </p>
                </div>

                {products.length > 0 ? (
                    <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {products.map((product) => (
                            <BookCard key={product.id ?? product.slug} item={product} />
                        ))}
                    </div>
                ) : (
                    <div className="mt-6 flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/30 px-6 text-center">
                        <PackageOpen className="size-10 text-orange-500" />
                        <h3 className="mt-4 text-xl font-bold">Barang belum ditemukan</h3>
                        <p className="mt-2 max-w-md text-sm leading-relaxed text-muted-foreground">Coba gunakan kata kunci lain atau hapus filter kategori untuk melihat seluruh katalog.</p>
                        {hasFilters && (
                            <button type="button" onClick={resetFilters} className="mt-5 rounded-full bg-orange-500 px-4 py-2 text-sm font-bold text-white hover:bg-orange-600">
                                Tampilkan semua barang
                            </button>
                        )}
                    </div>
                )}

                <PaginationControls meta={meta} />
            </section>
        </div>
    );
}

Index.layout = (page) => <StorefrontLayout title={page.props.page_settings?.title}>{page}</StorefrontLayout>;

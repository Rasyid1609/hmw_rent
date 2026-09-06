import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight, Store } from 'lucide-react';

export default function Index({ page_settings: pageSettings, category, brands: brandResource }) {
    const brands = brandResource?.data ?? [];
    const meta = brandResource?.meta ?? {};

    return (
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <Link href={route('front.categories.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-orange-600">
                <ArrowLeft className="size-4" />
                Semua kategori
            </Link>

            <section className="mt-8 flex flex-col justify-between gap-5 rounded-3xl border border-border bg-[radial-gradient(circle_at_top_right,_rgba(249,115,22,0.18),_transparent_30rem)] px-6 py-10 sm:flex-row sm:items-end sm:px-10">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">Kategori / {category.name}</p>
                    <h1 className="mt-2 text-4xl font-black tracking-tight sm:text-5xl">{pageSettings.title}</h1>
                    <p className="mt-4 max-w-2xl leading-relaxed text-muted-foreground">{pageSettings.subtitle}</p>
                </div>
                <p className="shrink-0 text-sm text-muted-foreground"><span className="font-semibold text-foreground">{meta.total ?? brands.length}</span> brand</p>
            </section>

            {brands.length > 0 ? (
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {brands.map((brand) => (
                        <Link
                            key={brand.id}
                            href={route('front.brands.show', { category: category.slug, brand: brand.slug })}
                            className="group overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                        >
                            <div className="relative flex aspect-[4/3] items-center justify-center bg-muted/50">
                                <Store className="absolute size-12 text-muted-foreground/50" aria-hidden="true" />
                                {brand.logo && (
                                    <img
                                        src={brand.logo}
                                        alt=""
                                        loading="lazy"
                                        onError={(event) => event.currentTarget.classList.add('hidden')}
                                        className="relative size-full bg-white object-contain"
                                    />
                                )}
                            </div>
                            <div className="flex items-center justify-between gap-3 p-5">
                                <div>
                                    <h2 className="text-xl font-bold group-hover:text-orange-600">{brand.name}</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">{brand.products_count} pilihan barang</p>
                                </div>
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white">
                                    <ArrowUpRight className="size-5" aria-hidden="true" />
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            ) : (
                <div className="mt-8 flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-border p-8 text-center">
                    <Store className="size-10 text-orange-500" aria-hidden="true" />
                    <h2 className="mt-4 text-xl font-bold">Belum ada brand dalam kategori ini</h2>
                    <p className="mt-2 text-sm text-muted-foreground">Barang untuk kategori {category.name} belum tersedia. Jelajahi kategori lainnya.</p>
                    <Link href={route('front.categories.index')} className="mt-5 rounded-full bg-orange-500 px-4 py-2 text-sm font-bold text-white hover:bg-orange-600">Lihat kategori lain</Link>
                </div>
            )}

            {meta.last_page > 1 && (
                <nav className="mt-10 flex flex-wrap justify-center gap-2" aria-label="Paginasi brand">
                    {meta.links.map((link, index) => {
                        const label = String(link.label).replace('&laquo;', '←').replace('&raquo;', '→');
                        const classes = cn('inline-flex min-h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold',
                            link.active ? 'bg-orange-500 text-white' : link.url ? 'border border-border hover:bg-muted' : 'text-muted-foreground/50');

                        return link.url ? (
                            <Link key={index} href={link.url} preserveScroll className={classes} aria-current={link.active ? 'page' : undefined}>{label}</Link>
                        ) : (
                            <span key={index} className={classes} aria-disabled="true">{label}</span>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}

Index.layout = (page) => <StorefrontLayout title={page.props.page_settings?.title}>{page}</StorefrontLayout>;

import BookCard from '@/Components/BookCard';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowLeft, PackageOpen } from 'lucide-react';

function paginationText(label) {
    return String(label).replace('&laquo;', '←').replace('&raquo;', '→');
}

function PaginationControls({ meta }) {
    const links = meta?.links ?? [];

    if ((!meta?.has_pages && Number(meta?.last_page ?? 1) <= 1) || links.length === 0) {
        return null;
    }

    return (
        <nav className="mt-10 flex justify-center" aria-label="Paginasi barang brand">
            <div className="flex flex-wrap justify-center gap-2">
                {links.map((link, index) => {
                    const classes = cn(
                        'inline-flex min-h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold transition-colors',
                        link.active ? 'bg-orange-500 text-white' : !link.url ? 'cursor-not-allowed text-muted-foreground/50' : 'border border-border bg-background hover:bg-muted',
                    );

                    return link.url ? (
                        <Link key={`${link.label}-${index}`} href={link.url} preserveScroll className={classes}>
                            {paginationText(link.label)}
                        </Link>
                    ) : (
                        <span key={`${link.label}-${index}`} className={classes} aria-disabled="true">
                            {paginationText(link.label)}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}

export default function Show({ page_settings: pageSettings, products: productResource, category, brand }) {
    const products = productResource?.data ?? (Array.isArray(productResource) ? productResource : []);
    const meta = productResource?.meta ?? {};

    return (
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <Link href={route('front.categories.show', category.slug)} className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-orange-600 dark:hover:text-orange-400">
                <ArrowLeft className="size-4" />
                Brand dalam {category.name}
            </Link>

            <section className="mt-8 flex flex-col justify-between gap-5 border-b border-border pb-8 sm:flex-row sm:items-end">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">{category.name} / {brand.name}</p>
                    <h1 className="mt-2 text-4xl font-black tracking-tight sm:text-5xl">{pageSettings?.title ?? 'Barang sewaan'}</h1>
                    <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted-foreground">{pageSettings?.subtitle ?? 'Pilih barang yang tersedia pada kategori ini.'}</p>
                </div>
                <p className="text-sm text-muted-foreground"><span className="font-semibold text-foreground">{meta.total ?? products.length}</span> pilihan barang</p>
            </section>

            {products.length > 0 ? (
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {products.map((product) => (
                        <BookCard key={product.id ?? product.slug} item={product} />
                    ))}
                </div>
            ) : (
                <div className="mt-8 flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/30 px-6 text-center">
                    <PackageOpen className="size-10 text-orange-500" />
                    <h2 className="mt-4 text-xl font-bold">Tidak ada barang di halaman ini</h2>
                    <p className="mt-2 text-sm text-muted-foreground">Jelajahi brand lain dalam kategori {category.name}.</p>
                    <Link href={route('front.categories.show', category.slug)} className="mt-5 rounded-full bg-orange-500 px-4 py-2 text-sm font-bold text-white hover:bg-orange-600">
                        Lihat brand lain
                    </Link>
                </div>
            )}

            <PaginationControls meta={meta} />
        </div>
    );
}

Show.layout = (page) => <StorefrontLayout title={page.props.page_settings?.title}>{page}</StorefrontLayout>;

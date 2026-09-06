import CategoryCard from '@/Components/CategoryCard';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Layers3 } from 'lucide-react';

function paginationText(label) {
    return String(label).replace('&laquo;', '←').replace('&raquo;', '→');
}

function PaginationControls({ meta }) {
    const links = meta?.links ?? [];

    if ((!meta?.has_pages && Number(meta?.last_page ?? 1) <= 1) || links.length === 0) {
        return null;
    }

    return (
        <nav className="mt-10 flex justify-center" aria-label="Paginasi kategori">
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

export default function Index({ categories: categoryResource, page_settings: pageSettings }) {
    const categories = categoryResource?.data ?? (Array.isArray(categoryResource) ? categoryResource : []);
    const meta = categoryResource?.meta ?? {};

    return (
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <Link href={route('front.products.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-orange-600 dark:hover:text-orange-400">
                <ArrowLeft className="size-4" />
                Kembali ke katalog
            </Link>

            <section className="mt-8 rounded-3xl border border-border bg-[radial-gradient(circle_at_top_right,_rgba(249,115,22,0.18),_transparent_30rem)] px-6 py-10 sm:px-10">
                <Layers3 className="size-9 text-orange-500" />
                <p className="mt-6 text-sm font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">Jelajahi kategori</p>
                <h1 className="mt-2 max-w-2xl text-4xl font-black tracking-tight sm:text-5xl">{pageSettings?.title ?? 'Kategori'}</h1>
                <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg">{pageSettings?.subtitle ?? 'Temukan barang yang kamu perlukan berdasarkan kategori.'}</p>
            </section>

            {categories.length > 0 ? (
                <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {categories.map((category) => (
                        <CategoryCard key={category.id ?? category.slug} item={category} />
                    ))}
                </div>
            ) : (
                <div className="mt-10 rounded-2xl border border-dashed border-border p-12 text-center text-muted-foreground">Kategori belum tersedia.</div>
            )}

            <PaginationControls meta={meta} />
        </div>
    );
}

Index.layout = (page) => <StorefrontLayout title={page.props.page_settings?.title}>{page}</StorefrontLayout>;

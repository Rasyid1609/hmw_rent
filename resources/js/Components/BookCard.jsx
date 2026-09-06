import { formatToRupiah } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, ImageOff } from 'lucide-react';

export default function BookCard({ item }) {
    const available = Number(item.stock?.available ?? 0);
    const inStock = available > 0;

    return (
        <article className="group overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <Link href={route('front.products.show', item.slug)} className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                <div className="relative aspect-[4/3] overflow-hidden bg-muted">
                    <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-orange-500/15 via-muted to-sky-500/10 text-muted-foreground">
                        <ImageOff className="size-9" aria-hidden="true" />
                    </div>
                    {item.cover && (
                        <img
                            src={item.cover}
                            alt={item.title}
                            loading="lazy"
                            onError={(event) => event.currentTarget.classList.add('hidden')}
                            className="relative size-full object-cover transition duration-300 group-hover:scale-105"
                        />
                    )}
                    <span
                        className={`absolute left-3 top-3 rounded-full px-2.5 py-1 text-xs font-semibold ${
                            inStock ? 'bg-emerald-500 text-white' : 'bg-foreground text-background'
                        }`}
                    >
                        {inStock ? `${available} tersedia` : 'Stok habis'}
                    </span>
                </div>
            </Link>

            <div className="flex min-h-48 flex-col p-4">
                <p className="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">
                    {item.category?.name ?? 'Barang sewaan'}
                </p>
                <h3 className="line-clamp-2 text-lg font-bold leading-snug text-card-foreground">
                    <Link href={route('front.products.show', item.slug)} className="transition-colors hover:text-orange-600 dark:hover:text-orange-400">
                        {item.title}
                    </Link>
                </h3>
                <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-muted-foreground">{item.description || 'Informasi barang akan segera tersedia.'}</p>

                <div className="mt-auto flex items-end justify-between gap-3 pt-5">
                    <div>
                        <p className="text-xs text-muted-foreground">Mulai dari</p>
                        <p className="text-base font-bold text-card-foreground">{formatToRupiah(item.price ?? 0)}</p>
                        <p className="text-xs text-muted-foreground">per hari</p>
                    </div>
                    <Link
                        href={route('front.products.show', item.slug)}
                        aria-label={`Lihat ${item.title}`}
                        className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white transition-colors hover:bg-orange-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                    >
                        <ArrowUpRight className="size-4" />
                    </Link>
                </div>
            </div>
        </article>
    );
}

import { Link } from '@inertiajs/react';
import { ArrowUpRight, Layers3 } from 'lucide-react';

export default function CategoryCard({ item }) {
    return (
        <Link
            href={route('front.categories.show', item.slug)}
            className="group relative flex aspect-[4/3] overflow-hidden rounded-2xl border border-border bg-muted p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
        >
            <div className="absolute inset-0 bg-gradient-to-br from-orange-500/75 via-orange-600/40 to-slate-950/80" />
            {item.cover && (
                <img
                    src={item.cover}
                    alt=""
                    loading="lazy"
                    onError={(event) => event.currentTarget.classList.add('hidden')}
                    className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105"
                />
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent" />

            <div className="relative flex w-full flex-col justify-between text-white">
                <span className="flex size-10 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm">
                    <Layers3 className="size-5" />
                </span>
                <div className="flex items-end justify-between gap-3">
                    <h3 className="text-2xl font-bold leading-tight">{item.name}</h3>
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-slate-950 transition-transform group-hover:translate-x-1 group-hover:-translate-y-1">
                        <ArrowUpRight className="size-5" />
                    </span>
                </div>
            </div>
        </Link>
    );
}

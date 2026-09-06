import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { formatToRupiah } from '@/lib/utils';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CheckCircle2, ImageOff, LogIn, Minus, PackageCheck, Plus, ShieldCheck } from 'lucide-react';

const staffRoles = ['admin', 'operator', 'accounting'];

function localDateValue(date = new Date()) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function initialDuration(value) {
    const duration = Number(value);

    return Number.isInteger(duration) && duration >= 1 && duration <= 365 ? duration : 1;
}

export default function Show({ page_settings: pageSettings, product }) {
    const { props, url } = usePage();
    const query = new URLSearchParams(url.split('?')[1] ?? '');
    const today = localDateValue();
    const requestedDate = query.get('rent_start_date');
    const rentStartDate = requestedDate && requestedDate >= today ? requestedDate : today;
    const user = props.auth?.user ?? null;
    const roles = Array.isArray(user?.role) ? user.role : [];
    const isMember = roles.includes('member');
    const isStaff = roles.some((role) => staffRoles.includes(role));
    const available = Number(product.stock?.available ?? 0);
    const inStock = available > 0;
    const { data, setData, post, processing, errors } = useForm({
        rent_duration: initialDuration(query.get('rent_duration')),
        rent_start_date: rentStartDate,
    });
    const total = Number(product.price ?? 0) * data.rent_duration;

    const updateDuration = (nextValue) => {
        const duration = Math.min(365, Math.max(1, Number(nextValue) || 1));
        setData('rent_duration', duration);
    };

    const rentAsMember = (event) => {
        event.preventDefault();

        post(route('front.loans.store', product.slug), {
            preserveScroll: true,
        });
    };

    const continueAsGuest = () => {
        router.get(
            route('login', {
                product: product.slug,
                rent_duration: data.rent_duration,
                rent_start_date: data.rent_start_date,
            }),
        );
    };

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <Link href={route('front.products.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-orange-600 dark:hover:text-orange-400">
                <ArrowLeft className="size-4" />
                Kembali ke katalog
            </Link>

            <div className="mt-7 grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.85fr)] lg:items-start">
                <div className="overflow-hidden rounded-3xl border border-border bg-muted shadow-sm">
                    <div className="relative aspect-[4/3]">
                        <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-orange-500/15 via-muted to-sky-500/10 text-muted-foreground">
                            <ImageOff className="size-12" aria-hidden="true" />
                        </div>
                        {product.cover && (
                            <img
                                src={product.cover}
                                alt={product.title}
                                onError={(event) => event.currentTarget.classList.add('hidden')}
                                className="relative size-full object-cover"
                            />
                        )}
                        <span className={`absolute left-5 top-5 rounded-full px-3 py-1.5 text-sm font-bold ${inStock ? 'bg-emerald-500 text-white' : 'bg-foreground text-background'}`}>
                            {inStock ? `${available} unit tersedia` : 'Stok sedang habis'}
                        </span>
                    </div>
                </div>

                <div>
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">{product.category?.name ?? 'Barang sewaan'}</p>
                    <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{product.title}</h1>
                    {product.created_at && <p className="mt-3 text-sm text-muted-foreground">Ditambahkan pada {product.created_at}</p>}
                    <p className="mt-6 whitespace-pre-line text-base leading-relaxed text-muted-foreground">{product.description || 'Informasi barang belum tersedia.'}</p>

                    <div className="mt-8 grid gap-3 border-y border-border py-6 sm:grid-cols-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Harga</p>
                            <p className="mt-1 text-lg font-black text-orange-600 dark:text-orange-400">{formatToRupiah(product.price ?? 0)}</p>
                            <p className="text-xs text-muted-foreground">per hari</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kategori</p>
                            <p className="mt-1 font-semibold">{product.category?.name ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Merek</p>
                            <p className="mt-1 font-semibold">{product.brand?.name ?? '—'}</p>
                        </div>
                    </div>

                    <form onSubmit={rentAsMember} className="mt-8 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-xl font-bold">Atur jadwal sewa</h2>
                                <p className="mt-1 text-sm text-muted-foreground">Pilih durasi dan tanggal mulai sebelum melanjutkan.</p>
                            </div>
                            <CalendarDays className="mt-1 size-5 shrink-0 text-orange-500" />
                        </div>

                        <div className="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label htmlFor="rent_duration" className="text-sm font-semibold">Durasi sewa</label>
                                <div className="mt-2 flex items-center rounded-xl border border-border bg-background p-1">
                                    <button
                                        type="button"
                                        onClick={() => updateDuration(data.rent_duration - 1)}
                                        disabled={data.rent_duration <= 1}
                                        className="inline-flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="Kurangi durasi"
                                    >
                                        <Minus className="size-4" />
                                    </button>
                                    <input
                                        id="rent_duration"
                                        type="number"
                                        min="1"
                                        max="365"
                                        value={data.rent_duration}
                                        onChange={(event) => updateDuration(event.target.value)}
                                        className="h-10 min-w-0 flex-1 border-0 bg-transparent p-0 text-center text-lg font-bold focus:ring-0"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => updateDuration(data.rent_duration + 1)}
                                        disabled={data.rent_duration >= 365}
                                        className="inline-flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="Tambah durasi"
                                    >
                                        <Plus className="size-4" />
                                    </button>
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">hari</p>
                                {errors.rent_duration && <p className="mt-2 text-sm text-destructive">{errors.rent_duration}</p>}
                            </div>
                            <div>
                                <label htmlFor="rent_start_date" className="text-sm font-semibold">Tanggal mulai sewa</label>
                                <input
                                    id="rent_start_date"
                                    type="date"
                                    min={today}
                                    value={data.rent_start_date}
                                    onChange={(event) => setData('rent_start_date', event.target.value)}
                                    className="mt-2 h-12 w-full rounded-xl border-border bg-background px-3 text-sm focus:border-orange-500 focus:ring-orange-500"
                                />
                                {errors.rent_start_date && <p className="mt-2 text-sm text-destructive">{errors.rent_start_date}</p>}
                            </div>
                        </div>

                        <div className="mt-6 flex items-end justify-between gap-4 rounded-xl bg-muted/60 p-4">
                            <div>
                                <p className="text-sm text-muted-foreground">Estimasi total sewa</p>
                                <p className="mt-1 text-2xl font-black text-orange-600 dark:text-orange-400">{formatToRupiah(total)}</p>
                                <p className="mt-1 text-xs text-muted-foreground">{data.rent_duration} hari × {formatToRupiah(product.price ?? 0)}</p>
                            </div>
                        </div>

                        <div className="mt-6">
                            {!inStock ? (
                                <button type="button" disabled className="inline-flex h-12 w-full items-center justify-center rounded-xl bg-muted px-5 text-sm font-bold text-muted-foreground sm:w-auto">
                                    Barang sedang tidak tersedia
                                </button>
                            ) : !user ? (
                                <button type="button" onClick={continueAsGuest} className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 text-sm font-bold text-white transition-colors hover:bg-orange-600 sm:w-auto">
                                    <LogIn className="size-5" />
                                    Masuk untuk menyewa
                                </button>
                            ) : isMember ? (
                                <button type="submit" disabled={processing || !data.rent_start_date} className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 text-sm font-bold text-white transition-colors hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                                    <PackageCheck className="size-5" />
                                    {processing ? 'Memproses…' : 'Sewa sekarang'}
                                </button>
                            ) : isStaff ? (
                                <Link href={route('dashboard')} className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-foreground px-5 text-sm font-bold text-background transition-opacity hover:opacity-90 sm:w-auto">
                                    Buka dashboard
                                </Link>
                            ) : (
                                <Link href={route('profile.edit')} className="inline-flex h-12 w-full items-center justify-center rounded-xl bg-orange-500 px-5 text-sm font-bold text-white transition-colors hover:bg-orange-600 sm:w-auto">
                                    Lengkapi akses akun
                                </Link>
                            )}
                        </div>
                    </form>

                    <div className="mt-6 grid gap-3 text-sm text-muted-foreground sm:grid-cols-2">
                        <p className="flex gap-2"><CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" /> Stok dikonfirmasi saat pesanan dibuat.</p>
                        <p className="flex gap-2"><ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-500" /> Data sewa hanya diproses setelah masuk.</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

Show.layout = (page) => <StorefrontLayout title={page.props.page_settings?.title}>{page}</StorefrontLayout>;

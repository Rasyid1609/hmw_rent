import { Button } from '@/Components/ui/button';
import { Calendar } from '@/Components/ui/calendar';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import AppLayout from '@/Layouts/AppLayout';
import { flashMessage } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { ChevronDownIcon } from 'lucide-react';
import { use, useState } from 'react';
import { toast } from 'sonner';

export default function Show(props) {
    const [duration, setDuration] = useState(1);
    const totalPrice = props.product.price * duration;

    const [open, setOpen] = useState (false);
    const [date, setDate] = useState(undefined);

    const { data, setData, reset, post, processing, errors } = useForm({
            rent_duration: 1,
            rent_start_date: '',
        });
    return (
        <div className="flex w-full flex-col space-y-12 pb-32">
            <div className="lg:grid-row lg:grid lg:grid-cols-12 lg:gap-x-8 lg:gap-y-10">
                <div className="lg:col-span-4 lg:row-end-1">
                    <div className="aspect-h-3 aspect-w-4 max-w-sm overflow-hidden rounded-lg bg-gray-100">
                        <img src={props.product.cover} alt={props.product.title} />
                    </div>
                </div>

                <div className="mt-14 lg:col-span-8 lg:row-span-2 lg:row-end-2 lg:mt-0 lg:max-w-none">
                    <div className="flex flex-col-reverse">
                        <div className="mt-4">
                            <h2 className="text-xl font-bold tracking-tighter text-foreground">{props.product.title}</h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                (Ditambahkan pada <time dateTime={props.product.created_at}>{props.product.created_at}</time>)
                            </p>
                        </div>
                    </div>
                    <p className="mt-6 text-sm leading-relaxed text-muted-foreground">{props.product.description}</p>

                    <div className="mt-6">
                        <h3 className="text-sm font-medium text-foreground">Durasi Sewa</h3>
                        <div className="mt-3 flex items-center gap-4">
                            <Button
                             variant="outline"
                             size="icon"
                             onClick={() =>
                                setDuration((prev) => {
                                    const newValue = Math.max(1, prev - 1);
                                    setData('rent_duration', newValue);
                                    return newValue;
                                })
                            }
                            >
                                -
                            </Button>
                            <span className="text-lg font-semibold">{duration}</span>
                            <Button
                             variant="outline"
                             size="icon"
                             onClick={() => setDuration((prev) => {
                                    const newValue = Math.min(30, prev +1);
                                    setData('rent_duration', newValue);
                                    return newValue;
                                })
                             }
                            >
                                +
                            </Button>
                        </div>
                    </div>

                    <div className="mt-6 flex flex-col gap-3">
                        <Label htmlFor='rent_start_date'>Tanggal Mulai Sewa</Label>
                        <Popover open={open} onOpenChange={setOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                variant="outline"
                                id="date"
                                className="w-48 justify-between font-normal"
                                >
                                {date ? date.toLocaleDateString() : "Select date"}
                                <ChevronDownIcon/>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                                <Calendar
                                    mode="single"
                                    selected={date}
                                    captionLayout="dropdown"
                                    onSelect={(date) => {
                                    if (!date) return;
                                    setDate(date);
                                    setData('rent_start_date', date.toISOString().split('T')[0]);
                                    setOpen(false);
                                    }}
                                />
                            </PopoverContent>
                        </Popover>
                    </div>

                    <div className="mt-10 flex">
                        {props.product.stock.available > 0 ? (
                            <Button
                                size="lg"
                                disabled={processing || !data.rent_start_date}
                                onClick={() =>
                                    post(
                                        route('front.loans.store', props.product.slug),
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            onSuccess: (page) => {
                                                const flash = flashMessage(page);
                                                if (flash) toast[flash.type](flash.message);
                                            },
                                        }
                                    )
                            }
                            >
                                Sewa Sekarang
                            </Button>
                        ) : (
                            <Button size="lg" disabled>
                                Barang Habis
                            </Button>
                        )}
                    </div>

                    <div className="mt-10 flex flex-col justify-start gap-10 border-t border-gray-200 pt-10 lg:flex-row">
                        <div>
                            <h3 className="text-sm font-medium text-foreground">Brand</h3>
                            <p className="mt-4 text-sm text-muted-foreground">{props.product?.brand?.name ?? '-'}</p>
                        </div>
                        <div>
                            <h3 className="text-sm font-medium text-foreground">Kategori</h3>
                            <p className="mt-4 text-sm text-muted-foreground">{props.product.category.name}</p>
                        </div>
                        <div>
                            <h3 className="text-sm font-medium text-foreground">Harga</h3>
                            <p className="mt-4 text-sm text-muted-foreground">
                                Rp {totalPrice.toLocaleString('id-ID')}
                                <span className="block text-xs text-muted-foreground">
                                    ({duration} hari x Rp {props.product.price.toLocaleString('id-ID')} )
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

Show.layout = (page) => <AppLayout children={page} title={page.props.page_settings.title} />;

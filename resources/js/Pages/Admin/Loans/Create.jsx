
import ComboBox from '@/Components/ComboBox';
import HeaderTitle from '@/Components/HeaderTitle';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { flashMessage } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { IconArrowLeft, IconCreditCardPay } from '@tabler/icons-react';
import { toast } from 'sonner';


export default function Create(props) {
    const { data, setData, reset, post, processing, errors } = useForm({
        user_id: null,
        product_id: null,
        rent_start_date: props.page_data.date.rent_start_date,
        rent_end_date: props.page_data.date.rent_end_date,
        _method: props.page_settings.method,
    });

    const onHandleSubmit = (e) => {
        e.preventDefault();
        post(props.page_settings.action, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (success) => {
                const flash = flashMessage(success);
                if (flash) toast[flash.type](flash.message);
            },
        });
    };

    const onHandleReset = () => {
        reset();
    };

    return (
        <div className="flex w-full flex-col pb-32">
            <div className="gp-y-4 mb-8 flex flex-col items-start justify-between lg:flex-row lg:items-center">
                <HeaderTitle
                    title={props.page_settings.title}
                    subTitle={props.page_settings.subtitle}
                    icon={IconCreditCardPay}
                />
                <Button variant="orange" size="lg" asChild>
                    <Link href={route('admin.loans.index')}>
                        <IconArrowLeft className="size-4" />
                        Kembali
                    </Link>
                </Button>
            </div>
            <Card>
                <CardContent className="p-6">
                    <form className="space-y-6" onSubmit={onHandleSubmit}>
                        <div className="grid w-full items-center gap-1.5">
                            <Label htmlFor="user">Nama</Label>
                            <ComboBox
                                items={props.page_data.users}
                                selectedItem={data.user_id}
                                onSelect={(currentValue) => setData('user_id', currentValue)}
                            />
                            {errors.user_id && <InputError message={errors.user_id} />}
                        </div>

                        <div className="grid w-full items-center gap-1.5">
                            <Label htmlFor="product">Produk</Label>
                            <ComboBox
                                items={props.page_data.products}
                                selectedItem={data.product_id}
                                onSelect={(currentValue) => setData('product_id', currentValue)}
                            />
                            {errors.product_id && <InputError message={errors.product_id} />}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="rent_start_date">Mulai sewa</Label>
                                <Input id="rent_start_date" type="date" value={data.rent_start_date}
                                    onChange={(event) => setData('rent_start_date', event.target.value)} />
                                <InputError message={errors.rent_start_date} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="rent_end_date">Batas pengembalian</Label>
                                <Input id="rent_end_date" type="date" value={data.rent_end_date}
                                    onChange={(event) => setData('rent_end_date', event.target.value)} />
                                <InputError message={errors.rent_end_date || errors.rent_duration} />
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="ghost" size="lg" onClick={onHandleReset}>
                                Reset
                            </Button>
                            <Button type="submit" variant="orange" size="lg">
                                Save
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

Create.layout = (page) => <AppLayout children={page} title={page.props.page_settings.title} />;

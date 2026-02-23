import ComboBox from '@/Components/ComboBox';
import HeaderTitle from '@/Components/HeaderTitle';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AppLayout from '@/Layouts/AppLayout';
import { flashMessage } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { IconArrowLeft, IconCreditCardPay } from '@tabler/icons-react';
import { toast } from 'sonner';

export default function Edit(props) {
    const { data, setData, reset, post, processing, errors } = useForm({
        payment_status: props.page_data.loan.payment_status,
        _method: props.page_settings.method,
    });

    const onHandleSubmit = (e) => {
    e.preventDefault();

    post(props.page_settings.action, {
        preserveScroll: true,
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
                            <Input value={props.page_data.loan.user.name} disabled />
                        </div>

                        <div className="grid w-full items-center gap-1.5">
                            <Label htmlFor="product">Produk</Label>
                            <Input value={props.page_data.loan.product.title} disabled />
                        </div>

                        <div className="grid w-full items-center gap-1.5">
                            <Label htmlFor="loan">Total Harga</Label>
                            <Input value={props.page_data.loan.rent_price} disabled />
                        </div>

                        <div className="grid w-full items-center gap-1.5">
                            <Label htmlFor="product">Durasi Hari</Label>
                            <Input value={`${props.page_data.loan.rent_duration} hari`} disabled />
                        </div>

                        <div>
                            <Label>Bukti Pembayaran</Label>

                            {props.page_data.loan.proof_image ? (
                                <img
                                src={`/storage/${props.page_data.loan.proof_image}`}
                                className="mt-2 w-64 rounded border"
                                alt="Bukti Pembayaran"
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                Belum ada bukti pembayaran
                                </p>
                            )}
                        </div>

                        <div>
                            <Label>Status Pembayaran</Label>

                            <select
                                className="w-full rounded border p-2"
                                value={data.payment_status}
                                onChange={(e) =>
                                setData('payment_status', e.target.value)
                                }
                            >
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>

                            {errors.payment_status && (
                                <InputError message={errors.payment_status} />
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="ghost" size="lg" onClick={onHandleReset}>
                                Reset
                            </Button>
                            <Button type="submit" variant="orange" size="lg" disabled={processing}>
                                {processing ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

Edit.layout = (page) => <AppLayout children={page} title={page.props.page_settings.title} />;

import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AppLayout from '@/Layouts/AppLayout';
import { formatToRupiah } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function Checkout({ page_settings, loan, props }) {
    const product = loan.product;
    const fileInput = useRef(null);

    const { data, setData, post, processing, errors } = useForm({
        proof_image: null,
    });

    const submit = (e) => {
        post(route('front.loans.payment', loan.loan_code), {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <AppLayout title={page_settings.title}>
            <div className="max-w-4xl mx-auto space-y-6">

                <div>
                    <h1 className="text-2xl font-bold">{page_settings.title}</h1>
                    <p className="text-muted-foreground">{page_settings.subtitle}</p>
                </div>

                {/* PRODUCT INFO */}
                <Card>
                    <CardHeader>
                        <CardTitle>Detail Barang</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div>Nama Barang</div>
                        <div className="font-medium">{product.title}</div>

                        <div>Kategori</div>
                        <div>{product.category?.name}</div>

                        <div>Brand</div>
                        <div>{product.brand?.name}</div>
                    </CardContent>
                </Card>

                {/* RENT INFO */}
                <Card>
                    <CardHeader>
                        <CardTitle>Detail Sewa</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div>Tanggal Mulai</div>
                        <div>{loan.rent_start_date}</div>

                        <div>Tanggal Selesai</div>
                        <div>{loan.rent_end_date}</div>

                        <div>Durasi</div>
                        <div>{loan.rent_duration} hari</div>

                        <div>Total Harga</div>
                        <div className="font-semibold text-lg">
                            {formatToRupiah(loan.rent_price)}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pembayaran</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form>
                            <div>
                                <Label>Rekening Pembayaran</Label>
                                <p className='space-y-4'>BCA - 1200939222</p>
                            </div>
                            <div>
                                <Label>Bukti Pembayaran</Label>
                                <Input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) =>
                                        setData('proof_image', e.target.files[0])
                                    }
                                />
                                {errors.proof_image && (
                                    <p className="text-sm text-red-500">
                                        {errors.proof_image}
                                    </p>
                                )}
                            </div>

                            <div className="flex justify-end mt-4">
                                <Button
                                    type="submit"
                                    disabled={processing || !data.proof_image}
                                    onClick={submit}
                                >
                                    {processing
                                        ? 'Mengirim...'
                                        : 'Kirim Bukti Pembayaran'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}


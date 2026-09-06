import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AppLayout from '@/Layouts/AppLayout';
import { formatToRupiah } from '@/lib/utils';
import { useForm } from '@inertiajs/react';

export default function Checkout({ page_settings, loan, can_upload_payment_proof, payment_status_label, proof_url }) {
    const product = loan.product;

    const { data, setData, post, processing, errors } = useForm({
        proof_image: null,
    });

    const submit = (e) => {
        e.preventDefault();

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
                        <p role="status" className="font-medium">{payment_status_label}</p>
                        {proof_url && <a href={proof_url} target="_blank" rel="noreferrer" className="inline-block text-sm font-medium text-orange-600 underline">Lihat bukti pembayaran</a>}
                        {loan.payment_status === 'failed' && can_upload_payment_proof && (
                            <p className="text-sm text-muted-foreground">Bukti sebelumnya ditolak. Periksa pembayaran dan unggah bukti yang benar.</p>
                        )}
                        {!can_upload_payment_proof && (
                            <p className="text-sm text-muted-foreground">
                                {loan.payment_status === 'paid'
                                    ? 'Pembayaran sudah diterima. Tidak perlu mengirim bukti lagi.'
                                    : 'Unggah bukti dinonaktifkan selama pemeriksaan atau setelah proses pengembalian dimulai.'}
                            </p>
                        )}
                        {can_upload_payment_proof && <form onSubmit={submit}>
                            <div>
                                <Label>Rekening Pembayaran</Label>
                                <p className='space-y-4'>BCA - 1200939222</p>
                            </div>
                            <div>
                                <Label>Bukti Pembayaran</Label>
                                <Input
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    onChange={(e) =>
                                        setData('proof_image', e.target.files[0])
                                    }
                                />
                                <p className="mt-1 text-xs text-muted-foreground">JPG atau PNG, maksimal 2 MB.</p>
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
                                >
                                    {processing
                                        ? 'Mengirim...'
                                        : 'Kirim Bukti Pembayaran'}
                                </Button>
                            </div>
                        </form>}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

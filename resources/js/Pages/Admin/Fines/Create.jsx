import GetFineStatusBadge from '@/Components/GetFineStatusBadge';
import HeaderTitle from '@/Components/HeaderTitle';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { FINEPAYMENTSTATUS, flashMessage, formatToRupiah } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import { IconMoneybag } from '@tabler/icons-react';

export default function Index(props) {
    const { patch, processing } = useForm({});
    const fine = props.return_product.fine;
    const review = (action) => patch(route(`payments.${action}`, fine.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = flashMessage(page);
            if (flash?.message) toast[flash.type || 'success'](flash.message);
        },
    });
    return (
        <div className="flex w-full flex-col pb-32">
            <div className="mb-8 flex flex-col items-start justify-between gap-y-4 lg:flex-row lg:items-center">
                <HeaderTitle
                    title={props.page_settings.title}
                    subTitle={props.page_settings.subtitle}
                    icon={IconMoneybag}
                />
            </div>

            <Card>
                <CardContent className="space-y-20 p-6">
                    <div>
                        <div className="rounded-lg px-4 py-6">
                            <dl className="flex flex-col gap-x-12 gap-y-4 text-sm leading-relaxed text-foreground lg:flex-row">
                                <div className="flex flex-col">
                                    <dt className="font-semibold">Kode Peminjaman</dt>
                                    <dd>{props.return_product.loan.loan_code}</dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="font-semibold">Kode Pengembalian</dt>
                                    <dd>{props.return_product.return_product_code}</dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="font-semibold">Tanggal Peminjaman</dt>
                                    <dd>{props.return_product.loan.rent_start_date}</dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="font-semibold">Batas Pengembalian</dt>
                                    <dd>{props.return_product.loan.rent_end_date}</dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="font-semibold">Tanggal Pengembalian</dt>
                                    <dd>{props.return_product.return_date}</dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="font-semibold">Total Denda</dt>
                                    <dd>{formatToRupiah(props.return_product.fine.total_fee)}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <Table className="mt-6 w-full">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Pengguna</TableHead>
                                <TableHead>Barang</TableHead>
                                <TableHead>Denda Keterlambatan</TableHead>
                                <TableHead>Denda Lainnya</TableHead>
                                <TableHead>Total Denda</TableHead>
                                <TableHead>Status Pembayaran</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell>{props.return_product.user.name}</TableCell>
                                <TableCell>{props.return_product.product.title}</TableCell>
                                <TableCell>
                                    {formatToRupiah(props.return_product.fine.late_fee)}
                                    <span className="text-red-500">{props.return_product.dayslate}</span>
                                </TableCell>
                                <TableCell>
                                    {formatToRupiah(props.return_product.fine.other_fee)}
                                    <span className="text-red-500">
                                        ({props.return_product.return_product_check?.condition ?? '-'})
                                    </span>
                                </TableCell>
                                <TableCell>{formatToRupiah(props.return_product.fine.total_fee)}</TableCell>
                                <TableCell>
                                    <GetFineStatusBadge status={props.return_product.fine.payment_status} />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    {fine.proof_image && (
                        <div className="space-y-4">
                            <h2 className="font-semibold">Bukti pembayaran</h2>
                            <a href={fine.proof_image} target="_blank" rel="noreferrer">
                                <img src={fine.proof_image} alt="Bukti pembayaran denda" className="max-h-80 max-w-full rounded-lg border object-contain" />
                            </a>
                            {props.can_review && fine.payment_status === FINEPAYMENTSTATUS.WAITING_VERIFICATION && (
                                <div className="flex gap-2">
                                    <Button disabled={processing} onClick={() => review('approve')}>Setujui pembayaran</Button>
                                    <Button variant="outline" disabled={processing} onClick={() => review('reject')}>Tolak bukti</Button>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="mt-6 grid w-full items-center gap-1.5">
                        <Label>Catatan:</Label>
                        <Textarea
                            className="resize-none"
                            value={props.return_product.return_product_check?.notes ?? ''}
                            disabled
                        ></Textarea>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

Index.layout = (page) => <AppLayout children={page} title={page.props.page_settings.title} />;

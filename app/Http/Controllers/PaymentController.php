<?php

namespace App\Http\Controllers;

use App\Enums\FinePaymentStatus;
use App\Enums\ReturnProductStatus;
use App\Models\Fine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PaymentController extends Controller
{
    public function uploadProof(Request $request, Fine $fine): RedirectResponse
    {
        abort_unless((int) $fine->user_id === (int) $request->user()->id, 403);

        $request->validate([
            'proof_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $path = null;
        $previousPath = null;

        try {
            DB::transaction(function () use ($request, $fine, &$path, &$previousPath): void {
                $lockedFine = Fine::query()->lockForUpdate()->findOrFail($fine->id);

                abort_unless((int) $lockedFine->user_id === (int) $request->user()->id, 403);
                abort_unless($lockedFine->payment_status->canUploadProof(), 409, 'Bukti pembayaran sedang diverifikasi.');

                $path = $request->file('proof_image')->store('payment-proofs', 'local');

                if (! $path) {
                    throw new RuntimeException('Bukti pembayaran tidak dapat disimpan.');
                }

                $previousPath = $lockedFine->proof_image;

                $lockedFine->update([
                    'proof_image' => $path,
                    'payment_status' => FinePaymentStatus::WAITING_VERIFICATION,
                ]);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            report($exception);
            flashMessage('Bukti pembayaran tidak dapat dikirim. Silakan coba lagi.', 'error');

            return back();
        }

        if ($previousPath && $previousPath !== $path && str_starts_with($previousPath, 'payment-proofs/')) {
            Storage::disk('local')->delete($previousPath);
        }

        flashMessage('Bukti pembayaran berhasil dikirim, menunggu verifikasi admin');

        return back();
    }

    public function approve(Request $request, Fine $fine): RedirectResponse
    {
        $this->ensureReviewer($request);

        DB::transaction(function () use ($fine): void {
            $lockedFine = Fine::query()->lockForUpdate()->findOrFail($fine->id);

            abort_unless(
                $lockedFine->payment_status->canBeReviewed() && filled($lockedFine->proof_image),
                409,
                'Bukti pembayaran belum siap diverifikasi.'
            );

            $returnProduct = $lockedFine->returnProduct()->lockForUpdate()->firstOrFail();

            $lockedFine->update([
                'payment_status' => FinePaymentStatus::SUCCESS,
            ]);

            $returnProduct->update([
                'status' => ReturnProductStatus::RETURNED,
            ]);
        });

        flashMessage('Pembayaran berhasil diverifikasi');

        return back();
    }

    public function reject(Request $request, Fine $fine): RedirectResponse
    {
        $this->ensureReviewer($request);

        DB::transaction(function () use ($fine): void {
            $lockedFine = Fine::query()->lockForUpdate()->findOrFail($fine->id);

            abort_unless($lockedFine->payment_status->canBeReviewed(), 409, 'Bukti pembayaran belum siap diverifikasi.');

            $lockedFine->update([
                'payment_status' => FinePaymentStatus::FAILED,
            ]);
        });

        flashMessage('Pembayaran ditolak');

        return back();
    }

    public function success(): Response
    {
        return inertia('Payment/Success');
    }

    private function ensureReviewer(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'accounting']), 403);
    }
}

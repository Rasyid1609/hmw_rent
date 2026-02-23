<?php

namespace App\Http\Controllers;

use Exception;
use Throwable;
use Midtrans\Snap;
use App\Models\Fine;
use Midtrans\Config;
use App\Enums\MessageType;
use Illuminate\Http\Request;
use App\Models\ReturnProduct;
use App\Enums\FinePaymentStatus;
use Illuminate\Http\JsonResponse;
use App\Enums\ReturnProductStatus;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function uploadProof(Request $request, Fine $fine): RedirectResponse
    {
        try {
            $request->validate([
                'proof_image' => ['required|image|mimes:jpg,jpeg,png|max:2048'],
            ]);

            $path = $request->file('proof_image')->store(
                'payment-proofs',
                'public'
            );

            $fine->update([
                'proof_image' => $path,
                'payment_status' => FinePaymentStatus::WAITING_VERIFICATION->value,
            ]);

            flashMessage('Bukti pembayaran berhasil dikirim, menunggu verifikasi admin');

            return back();
        } catch (\Throwable $e) {
            flashMessage(MessageType::ERROR->message(error: $e->getMessage()));
            return back();
        }
    }

    /**
     * Admin menyetujui pembayaran
     */
    public function approve(Fine $fine): RedirectResponse
    {
        $fine->update([
            'payment_status' => FinePaymentStatus::SUCCESS->value,
        ]);

        $fine->returnProduct->update([
            'status' => 'returned',
        ]);

        flashMessage('Pembayaran berhasil diverifikasi');

        return back();
    }

    /**
     * Admin menolak pembayaran
     */
    public function reject(Fine $fine): RedirectResponse
    {
        $fine->update([
            'payment_status' => FinePaymentStatus::FAILED->value,
        ]);

        flashMessage('Pembayaran ditolak');

        return back();
    }

    public function success(): Response
    {
        return inertia('Payments/Success');
    }
}

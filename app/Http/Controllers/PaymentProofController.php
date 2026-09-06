<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function loan(Request $request, Loan $loan): StreamedResponse
    {
        abort_unless(
            (int) $request->user()->id === (int) $loan->user_id
            || $request->user()->hasAnyRole(['admin', 'operator']),
            403
        );

        return $this->image($loan->proof_image);
    }

    public function fine(Request $request, Fine $fine): StreamedResponse
    {
        abort_unless(
            (int) $request->user()->id === (int) $fine->user_id
            || $request->user()->hasAnyRole(['admin', 'operator', 'accounting']),
            403
        );

        return $this->image($fine->proof_image);
    }

    private function image(?string $path): StreamedResponse
    {
        // Only generated payment-proof filenames can be served, even if an
        // old database record contains a path to another private document.
        abort_unless($path && preg_match('#\Apayment-proofs/[a-z0-9_-]+\.(?:jpe?g|png)\z#i', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 404);

        return $disk->response($path, 'bukti-transfer.'.($mime === 'image/png' ? 'png' : 'jpg'), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProtectPaymentProofs extends Command
{
    protected $signature = 'payment-proofs:protect';

    protected $description = 'Pindahkan bukti transfer lama dari storage publik ke privat dengan pemeriksaan isi file';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        $failed = 0;

        foreach ($public->allFiles('payment-proofs') as $path) {
            try {
                $checksum = $this->checksum($public, $path);

                if (! $private->exists($path)) {
                    $stream = $public->readStream($path);
                    if (! is_resource($stream)) {
                        throw new RuntimeException('File asal tidak dapat dibaca.');
                    }

                    try {
                        if (! $private->put($path, $stream, ['visibility' => 'private'])) {
                            throw new RuntimeException('Salinan privat tidak dapat disimpan.');
                        }
                    } finally {
                        fclose($stream);
                    }
                }

                // Safe to rerun after interruption; never overwrite a different
                // private file or delete an unverified public original.
                if ($checksum !== $this->checksum($private, $path)
                    || $checksum !== $this->checksum($public, $path)) {
                    throw new RuntimeException('Isi file berbeda. File asal dipertahankan.');
                }

                if (! $public->delete($path)) {
                    throw new RuntimeException('Salinan publik belum berhasil dihapus.');
                }

                $moved++;
            } catch (Throwable $error) {
                $this->error($path.': '.$error->getMessage());
                $failed++;
            }
        }

        $this->info("Bukti dipindahkan: {$moved}. Gagal: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checksum(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('File tidak dapat diperiksa.');
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}

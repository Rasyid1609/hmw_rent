<?php

namespace App\Enums;

enum FinePaymentStatus: string
{
    case PENDING = 'Tertunda';
    case WAITING_VERIFICATION = 'Menunggu Verifikasi';
    case SUCCESS = 'Berhasil';
    case FAILED = 'Gagal';

    public function canUploadProof(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED], true);
    }

    public function canBeReviewed(): bool
    {
        return $this === self::WAITING_VERIFICATION;
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($item) => [
            'value' => $item->value,
            'label' => $item->value,
        ])->values()->toArray();
    }
}

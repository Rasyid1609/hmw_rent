<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable foreign keys outside a transaction when rebuilding
    // this enum column, otherwise existing child rows can be cascade-deleted.
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed'])
                ->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        // Do not discard rejection data when reverting to the narrower enum.
        if (DB::table('loans')->where('payment_status', 'failed')->exists()) {
            throw new RuntimeException('Migrasi tidak dapat dibatalkan selama masih ada pembayaran sewa berstatus ditolak.');
        }

        Schema::table('loans', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'pending', 'paid'])
                ->default('unpaid')->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('rent_start_date')->after('loan_date');
            $table->date('rent_end_date')->after('rent_start_date');
            $table->integer('rent_duration')->after('rent_end_date');
            $table->decimal('rent_price', 10, 2)->after('rent_duration');

            $table->enum('payment_status', [
                'unpaid',
                'pending',
                'paid'
            ])->default('unpaid')->after('rent_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'rent_start_date',
                'rent_end_date',
                'rent_duration',
                'rent_price',
                'payment_status',
            ]);
        });
    }
};

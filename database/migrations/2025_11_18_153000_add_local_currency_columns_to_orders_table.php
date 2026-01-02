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
        Schema::table('orders', function (Blueprint $table) {
            $table->double('local_currency_rate', 23, 8)->nullable()->after('delivery_charge');
            $table->double('local_currency_delivery_fees', 23, 8)->nullable()->after('local_currency_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['local_currency_rate', 'local_currency_delivery_fees']);
        });
    }
};
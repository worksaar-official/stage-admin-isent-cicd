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
        Schema::create('local_currency_conversion', function (Blueprint $table) {
            $table->id();
            $table->double('local_rate', 23, 8)->default(0);
            $table->timestamps();
        });

        if (!app()->runningUnitTests()) {
            \Illuminate\Support\Facades\DB::table('local_currency_conversion')->insert([
                'local_rate' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_currency_conversion');
    }
};
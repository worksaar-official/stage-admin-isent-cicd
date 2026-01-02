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
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->string('plarftormID_ndasenda')->nullable();
            $table->string('customerAcc_ndasenda')->nullable();
            $table->string('methodName_ndasenda')->nullable();
            $table->string('statusName_ndasenda')->nullable();
            $table->string('paymentReference_ndasenda')->nullable();
            $table->string('merchantReference_ndasenda')->nullable();
            $table->string('paymentDescription_ndasenda')->nullable();
            $table->string('merchantDescription_ndasenda')->nullable();
            $table->decimal('merchantFees_ndasenda', 24, 3)->nullable();
            $table->decimal('customerFees_ndasenda', 24, 3)->nullable();
            $table->timestamp('paidDate_ndasenda')->nullable();
            $table->timestamp('createdDate_ndasenda')->nullable();
            $table->string('correlator_ndasenda')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn([
                'plarftormID_ndasenda',
                'customerAcc_ndasenda',
                'methodName_ndasenda',
                'statusName_ndasenda',
                'paymentReference_ndasenda',
                'merchantReference_ndasenda',
                'paymentDescription_ndasenda',
                'merchantDescription_ndasenda',
                'merchantFees_ndasenda',
                'customerFees_ndasenda',
                'paidDate_ndasenda',
                'createdDate_ndasenda',
                'correlator_ndasenda',
            ]);
        });
    }
};


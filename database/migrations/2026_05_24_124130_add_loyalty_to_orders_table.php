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
            $table->decimal('bonus_used_amount', 10, 2)->default(0)->after('discount_total');
            $table->decimal('bonus_earned_amount', 10, 2)->default(0)->after('bonus_used_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bonus_used_amount', 'bonus_earned_amount']);
        });
    }
};

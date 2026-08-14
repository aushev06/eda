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
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('completed_at');
            $table->string('void_reason')->nullable()->after('voided_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('accepted_at');
            // An open table check has no payment method until it is closed.
            $table->string('payment_method')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('opened_at');
        });
    }
};

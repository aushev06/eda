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
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            // Amount applied to the bill via this method (sums to order.total).
            $table->decimal('amount', 10, 2);
            // For cash: what the guest physically handed (>= amount). Change is
            // received - amount. Null/equal for card.
            $table->decimal('received_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['method', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });

        Schema::dropIfExists('order_payments');
    }
};

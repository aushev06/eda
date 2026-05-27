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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('new');
            $table->string('delivery_type');
            $table->string('payment_method');
            $table->string('payment_status')->default('pending');

            // Снапшоты контактов
            $table->string('customer_name');
            $table->string('customer_phone');

            // Снапшот адреса (если delivery)
            $table->string('delivery_street')->nullable();
            $table->string('delivery_apartment')->nullable();
            $table->string('delivery_entrance')->nullable();
            $table->string('delivery_floor')->nullable();
            $table->string('delivery_intercom')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->text('delivery_instructions')->nullable();

            // Деньги
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('modifiers_total', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->text('customer_comment')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

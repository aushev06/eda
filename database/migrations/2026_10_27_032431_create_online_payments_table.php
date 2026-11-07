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
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Gateway key from config/payments.php, e.g. "yookassa".
            $table->string('gateway', 32);
            // Our unique id for this attempt, sent to the gateway as its order number.
            $table->string('reference', 64)->nullable()->unique();
            // The gateway's own payment id.
            $table->string('external_id', 128)->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 10, 2);
            $table->text('confirmation_url')->nullable();
            $table->string('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'external_id']);
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};

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
            $table->string('guest_pin', 4)->nullable()->after('opened_at');
            $table->string('guest_channel_token', 40)->nullable()->after('guest_pin');
        });

        Schema::create('table_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 40);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('table_guest_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_guest_id');
        });

        Schema::dropIfExists('table_guests');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['guest_pin', 'guest_channel_token']);
        });
    }
};

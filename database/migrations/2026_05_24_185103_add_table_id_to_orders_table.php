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
            $table->foreignId('table_id')
                ->nullable()
                ->after('delivery_zone_id')
                ->constrained('tables')
                ->nullOnDelete();
            $table->string('table_number_snapshot')->nullable()->after('table_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_id');
            $table->dropColumn('table_number_snapshot');
        });
    }
};

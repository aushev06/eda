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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('station', 20)->default('kitchen')->after('sort_order');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('station', 20)->nullable()->after('category_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('site')->after('number')->index();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('line_total');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('station');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('station');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

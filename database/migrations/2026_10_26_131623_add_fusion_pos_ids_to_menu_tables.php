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
        foreach (['categories', 'modifier_groups', 'modifiers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('fusion_pos_id')->nullable()->unique()->after('id');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('fusion_pos_category_id')->nullable()->index()->after('fusion_pos_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['fusion_pos_category_id']);
            $table->dropColumn('fusion_pos_category_id');
        });

        foreach (['categories', 'modifier_groups', 'modifiers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['fusion_pos_id']);
                $table->dropColumn('fusion_pos_id');
            });
        }
    }
};

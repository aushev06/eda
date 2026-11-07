<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Printers configured in the admin («Оборудование») and pulled by the POS app.
     * Column set follows FusionPOS `printers` minus the fiscal (ATOL) fields.
     */
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('name');
            $table->string('connection_type');
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedInteger('baud_rate')->nullable();
            $table->unsignedTinyInteger('tape_width')->default(80);
            $table->unsignedInteger('chunk_size')->default(1460);
            $table->unsignedSmallInteger('chunks_delay_ms')->default(25);
            $table->unsignedSmallInteger('tasks_delay_ms')->default(1000);
            $table->boolean('persist_connection')->default(true);
            $table->boolean('print_images')->default(true);
            $table->boolean('send_beep')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('comments')->nullable();
            $table->timestamps();

            $table->unique(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};

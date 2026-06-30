<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_remission_taxes')) {
            return;
        }

        Schema::create('commercial_remission_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commercial_remission_id');
            $table->unsignedBigInteger('commercial_remission_item_id')->nullable();
            $table->string('tax_name', 80);
            $table->string('tax_type', 20);
            $table->string('tax_mode', 20)->default('rate');
            $table->decimal('rate', 18, 6)->default(0);
            $table->decimal('base', 18, 6)->default(0);
            $table->decimal('amount', 18, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index('commercial_remission_id', 'crt_rem_idx');
            $table->index('commercial_remission_item_id', 'crt_item_idx');
            $table->index('tax_type', 'crt_type_idx');
            $table->index('tax_mode', 'crt_mode_idx');
            $table->index('sort_order', 'crt_sort_idx');

            $table->foreign('commercial_remission_id', 'crt_rem_fk')->references('id')->on('commercial_remissions')->cascadeOnDelete();
            $table->foreign('commercial_remission_item_id', 'crt_item_fk')->references('id')->on('commercial_remission_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_remission_taxes');
    }
};

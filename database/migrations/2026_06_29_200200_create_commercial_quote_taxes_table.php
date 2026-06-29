<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_quote_taxes')) {
            return;
        }

        Schema::create('commercial_quote_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->foreignId('commercial_quote_item_id')->nullable()->constrained('commercial_quote_items')->cascadeOnDelete();
            $table->string('tax_name', 80);
            $table->string('tax_type', 20);
            $table->decimal('rate', 18, 6)->default(0);
            $table->decimal('base', 18, 6)->default(0);
            $table->decimal('amount', 18, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index('commercial_quote_id', 'cqt_quote_idx');
            $table->index('commercial_quote_item_id', 'cqt_item_idx');
            $table->index('tax_type', 'cqt_type_idx');
            $table->index('sort_order', 'cqt_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_quote_taxes');
    }
};

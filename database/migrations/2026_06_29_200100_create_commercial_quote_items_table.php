<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_quote_items')) {
            return;
        }

        Schema::create('commercial_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->bigInteger('product_id')->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('snapshot_name', 200);
            $table->text('snapshot_description')->nullable();
            $table->string('snapshot_unit', 80)->nullable();
            $table->decimal('snapshot_unit_price', 18, 6)->default(0);
            $table->string('snapshot_tax_name', 80)->nullable();
            $table->string('snapshot_tax_type', 20)->nullable();
            $table->decimal('snapshot_tax_rate', 18, 6)->default(0);
            $table->decimal('quantity', 18, 6)->default(1);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('line_discount_amount', 18, 6)->default(0);
            $table->decimal('line_subtotal', 18, 6)->default(0);
            $table->decimal('line_base_before_global', 18, 6)->default(0);
            $table->decimal('global_discount_share', 18, 6)->default(0);
            $table->decimal('taxable_base', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('line_total', 18, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('commercial_quote_id', 'cqi_quote_idx');
            $table->index('product_id', 'cqi_product_idx');
            $table->index('sort_order', 'cqi_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_quote_items');
    }
};

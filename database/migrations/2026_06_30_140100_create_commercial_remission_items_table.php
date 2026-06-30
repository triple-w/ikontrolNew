<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_remission_items')) {
            return;
        }

        Schema::create('commercial_remission_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commercial_remission_id');
            $table->unsignedBigInteger('commercial_quote_item_id')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('snapshot_name', 200);
            $table->text('snapshot_description')->nullable();
            $table->string('snapshot_unit', 80)->nullable();
            $table->decimal('snapshot_unit_price', 18, 6)->default(0);
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

            $table->index('commercial_remission_id', 'cri_rem_idx');
            $table->index('commercial_quote_item_id', 'cri_qitem_idx');
            $table->index('product_id', 'cri_product_idx');
            $table->index('sort_order', 'cri_sort_idx');

            $table->foreign('commercial_remission_id', 'cri_rem_fk')->references('id')->on('commercial_remissions')->cascadeOnDelete();
            $table->foreign('commercial_quote_item_id', 'cri_qitem_fk')->references('id')->on('commercial_quote_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_remission_items');
    }
};

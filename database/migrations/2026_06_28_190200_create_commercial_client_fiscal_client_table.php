<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_client_fiscal_client')) {
            return;
        }

        Schema::create('commercial_client_fiscal_client', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commercial_client_id');
            $table->bigInteger('fiscal_client_id');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['commercial_client_id', 'fiscal_client_id'], 'ccfc_unique_link');
            $table->index('commercial_client_id', 'ccfc_client_idx');
            $table->index('fiscal_client_id', 'ccfc_fiscal_idx');
            $table->index(['commercial_client_id', 'is_default'], 'ccfc_client_default_idx');

            $table->foreign('commercial_client_id', 'ccfc_client_fk')->references('id')->on('commercial_clients')->cascadeOnDelete();
            $table->foreign('fiscal_client_id', 'ccfc_fiscal_fk')->references('id')->on('clientes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_client_fiscal_client');
    }
};

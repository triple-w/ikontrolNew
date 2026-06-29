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
            $table->foreignId('commercial_client_id')->constrained('commercial_clients')->cascadeOnDelete();
            $table->bigInteger('fiscal_client_id');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['commercial_client_id', 'fiscal_client_id'], 'ccfc_commercial_fiscal_unique');
            $table->index('commercial_client_id');
            $table->index('fiscal_client_id');
            $table->index(['commercial_client_id', 'is_default']);

            $table->foreign('fiscal_client_id')->references('id')->on('clientes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_client_fiscal_client');
    }
};

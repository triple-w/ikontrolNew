<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_contacts')) {
            return;
        }

        Schema::create('commercial_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_client_id')->constrained('commercial_clients')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('position', 120)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_quotes')->default(true);
            $table->boolean('receives_documents')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('commercial_client_id');
            $table->index('email');
            $table->index('is_active');
            $table->index(['commercial_client_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_contacts');
    }
};

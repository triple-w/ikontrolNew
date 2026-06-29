<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_clients')) {
            return;
        }

        Schema::create('commercial_clients', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->bigInteger('assigned_user_id')->nullable();
            $table->string('name', 200);
            $table->string('business_name', 200)->nullable();
            $table->string('client_type', 20)->default('company');
            $table->string('email', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('street', 120)->nullable();
            $table->string('exterior_number', 30)->nullable();
            $table->string('interior_number', 30)->nullable();
            $table->string('neighborhood', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('country', 80)->nullable()->default('Mexico');
            $table->string('postal_code', 20)->nullable();
            $table->string('category', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('users_id');
            $table->index('assigned_user_id');
            $table->index('name');
            $table->index('email');
            $table->index('is_active');
            $table->index(['users_id', 'client_type']);
            $table->index(['users_id', 'category']);

            $table->foreign('users_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_clients');
    }
};

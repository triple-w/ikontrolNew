<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_remission_status_history')) {
            return;
        }

        Schema::create('commercial_remission_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commercial_remission_id');
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->bigInteger('user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('commercial_remission_id', 'crsh_rem_idx');
            $table->index('user_id', 'crsh_user_idx');
            $table->index('new_status', 'crsh_status_idx');
            $table->index('changed_at', 'crsh_changed_idx');

            $table->foreign('commercial_remission_id', 'crsh_rem_fk')->references('id')->on('commercial_remissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_remission_status_history');
    }
};

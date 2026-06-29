<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_quote_status_history')) {
            return;
        }

        Schema::create('commercial_quote_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('commercial_quote_id', 'cqsh_quote_idx');
            $table->index('new_status', 'cqsh_status_idx');
            $table->index('changed_at', 'cqsh_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_quote_status_history');
    }
};

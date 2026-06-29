<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_quotes')) {
            return;
        }

        Schema::create('commercial_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('users_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('commercial_client_id')->constrained('commercial_clients')->restrictOnDelete();
            $table->foreignId('commercial_contact_id')->nullable()->constrained('commercial_contacts')->nullOnDelete();
            $table->bigInteger('fiscal_client_id')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('folio_prefix', 20)->default('COT');
            $table->unsignedBigInteger('folio_number');
            $table->string('folio', 40);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('currency', 3)->default('MXN');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('commercial_terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->decimal('global_discount_amount', 18, 6)->default(0);
            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('line_discount_total', 18, 6)->default(0);
            $table->decimal('discount_total', 18, 6)->default(0);
            $table->decimal('tax_total', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['users_id', 'folio'], 'cq_user_folio_unique');
            $table->index(['users_id', 'folio_number'], 'cq_user_number_idx');
            $table->index('commercial_client_id', 'cq_client_idx');
            $table->index('commercial_contact_id', 'cq_contact_idx');
            $table->index('fiscal_client_id', 'cq_fiscal_idx');
            $table->index('status', 'cq_status_idx');
            $table->index('issued_at', 'cq_issued_idx');
            $table->index('expires_at', 'cq_expires_idx');
            $table->index('assigned_user_id', 'cq_assigned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_quotes');
    }
};
